<?php
## ################################################## ##
##                  MysteriousBot                     ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: socket.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/24/2011                            ##
##  [*] Last edit: 6/2/2011                           ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;

class Socket extends Singleton {
	public $lines_sent = 0;
	public $lines_read = 0;
	
	private $queue_write = array();
	private $_loop = true;
	private $_sockets = array();
	private $_sids = array();
	
	const LISTENER = 2;
	const CLIENT = 4;

	public function add_client($host, $port, $ssl=false, $callback, $name='') {
		if ( $ssl === true ) $host = 'ssl://'.$host;
		
		$logger = Logger::get_instance();
		
		if ( ($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Failed to create the socket!');
		}
		
		if ( (socket_connect($socket, $host, $port)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Failed to connect to '.$host.' on port '.$port.' using ssl: '.($ssl ? 'Yes' : 'No'));
		}
		
		socket_set_nonblock($socket);
		
		$id = uniqid('C');
		
		$this->_sockets[$id] = array(
			'socket'   => $socket,
			'host'     => $host,
			'port'     => $port,
			'ssl'      => $ssl,
			'callback' => $callback
		);
		
		if ( !empty($name) )
			$this->_sids[$name] = $id;
			
		$logger->debug(__FILE__, __LINE__, 'Added a new client. Name: '.$name.' Sid: '.$id);
			
		return $id;
	}
	
	public function add_listener($ip, $port, $callback, $on_accept, $name='') {
		$logger = Logger::get_instance();
		
		if ( ($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Failed to create the socket!');
		}
		
		if ( (socket_bind($socket, $ip, $port)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Failed to bind '.$ip.' on port '.$port.'!');
		}
		
		if ( (socket_listen($socket)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Failed to listen on socket!');
		}
		
		socket_set_nonblock($socket);
		
		$id = uniqid('L');
		
		$this->_sockets[$id] = array(
			'socket'    => $socket,
			'ip'        => $ip,
			'port'      => $port,
			'on_accept' => $on_accept,
			'callback'  => $callback
		);
		
		if ( !empty($name) )
			$this->_sids[$name] = $id;
		
		return $id;
	}
	
	public function loop() {
		// Get the logger instance
		$logger = Logger::get_instance();
		
		while ( $this->_loop === true ) {
			// First let's sleep a bit, to calm down...
			usleep(Config::get_instance()->get('usleep'));
			
			// Now start with the timers
			Timer::tik();
			
			// What do we need to read?
			$read = array();
			foreach ( $this->_sockets AS $id => $data )
				$read[$id] = $data['socket'];
			
			// What about writes?
			$write = array();
			foreach ( $this->queue_write AS $sid => $data ) {
				if ( !isset($this->_sockets[$sid]) ) {
					unset($this->queue_write[$sid]);
					continue;
				}
				
				$write[] = $this->_sockets[$sid]['socket'];
			}
				
			// It empty?
			if ( empty($read) && empty($write) )
				continue;
			
			$e = null;
			if ( socket_select($read, $write, $e, 1) < 1 )
				continue;
				
			// Do the writes
			if ( !empty($write) ) {
				foreach ($write AS $socket) {
					$sid = $this->_getSID($socket);

					if ( isset($this->queue_write[$sid]) )
						$length = strlen($this->queue_write[$sid]);
					else
						continue;

					if ( $length == 0 ) {
						unset($this->queue_write[$sid]);
						continue;
					}
					
					// Let's start writing!
					$writed = socket_write($socket, $this->queue_write[$sid]);
					
					foreach ( explode("\n", $this->queue_write[$sid]) AS $payload ) {
						if ( empty($payload) ) continue;
						$logger->debug(__FILE__, __LINE__, '[Socket] Write payload to '.$sid.' :'.$payload);
					}
					
					$this->lines_sent += count(explode("\n", $this->queue_write[$sid]));
					
					if ( ($writed < $length) && ($writed > 0) )
						$this->queue_write[$sid] = substr($this->queue_write[$sid], $writed);
					else if ($writed == $length)
						unset($this->queue_write[$sid]);
				}
			}
			
			if ( !empty($read) ) {
				foreach ($read AS $socket) {
					$sid = $this->_getSID($socket);

					// Oh hey, we have a connection
					if ( substr($sid, 0, 1) == 'L' ) {
						if (($client = @socket_accept($this->_sockets[$sid]['socket'])) === FALSE) continue;
						$cid = uniqid('c');
						$address = '';
						$port = 0;
						socket_getpeername($client, $address, $port);

						$this->_sockets[$cid] = array(
							'socket' => $client,
							'address' => $address,
							'port' => $port,
							'callback' => $this->_sockets[$sid]['callback'],
						);
						
						call_user_func($this->_sockets[$sid]['on_accept'], $cid, $address, $port);
					} else {
						$read_data = socket_read($this->_sockets[$sid]['socket'], 65536);
						
						// Socket died.
						if ( $read_data === false ) {
							$this->close($sid);
						}
						
						$data = explode("\n", $read_data);
						
						foreach ( $data AS $line ) {
							if ( strlen($line) == 0 ) continue;
							$logger->debug(__FILE__, __LINE__, '[Socket] Got raw data for socketid '.$sid.' :'.$line);
							
							call_user_func($this->_sockets[$sid]['callback'], $sid, $line);
							$this->lines_read++;
						}
					}

					unset($sid);
				}
			}
		}
	}
	
	public function close($id) {
		if ( isset($this->_sockets[$id]) ) {
			socket_close($this->_sockets[$id]['socket']);
			unset($this->_sockets[$id]);
			
			Logger::get_instance()->info(__FILE__, __LINE__, '[Socket] Closed socket id '.$id);
		}
	}
	
	private function _getSID($socket) {
		foreach ($this->_sockets AS $id => $data)
			if ($data['socket'] == $socket) return $id;
	}
	
	public function enable_crypto($socketid, $enable, $type) {
		if ( !isset($this->_sockets[$socketid]) ) return false;
		
		stream_socket_enable_crypto($this->_sockets[$socketid]['socket'], $enable, $type);
	}
	
	public function write($socketid, $payload) {
		if ( !is_array($payload) )
			$payload = array($payload);
		
		foreach ( $payload AS $pload ) {
			if ( isset($this->queue_write[$socketid]) )
				$this->queue_write[$socketid] .= $pload."\r\n";
			else
				$this->queue_write[$socketid] = $pload."\r\n";
		}
	}
	
	public function getSID($name) {
		return isset($this->_sids[$name]) ? $this->_sids[$name] : null;
	}
	
	public function stop_loop() {
		$this->_loop = false;
	}
}
