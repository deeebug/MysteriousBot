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
		if ( $ssl === true )
			$protocol = 'ssl';
		else
			$protocol = 'tcp';
		
		$logger = Logger::get_instance();
		$host = @gethostbyname($host);
		
		if ( ($socket = stream_socket_client($protocol.'://'.$host.':'.$port, $errno, $errstr, 30, STREAM_CLIENT_ASYNC_CONNECT)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Stream socket client failed - Error number: '.$errno.' - Error Text: '.$errstr);
			return;
		}
		
		stream_set_blocking($socket, false);
		
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
		
		if ( ($socket = stream_socket_server('tcp://'.$ip.':'.$port, $errno, $errstr)) === false ) {
			$logger->warning(__FILE__, __LINE__, 'Failed to stream the socket server - Error number: '.$errno.' - Error Text: '.$errstr);
			return;
		}
		
		stream_set_blocking($socket, false);
		
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
			if ( stream_select($read, $write, $e, 1) < 1 )
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
					$writed = fwrite($socket, $this->queue_write[$sid]);
					
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
						if (($client = @stream_socket_accept($this->_sockets[$sid]['socket'])) === FALSE) continue;
						$cid = uniqid('c');

						$this->_sockets[$cid] = array(
							'socket' => $client,
							'callback' => $this->_sockets[$sid]['callback'],
						);
						
						call_user_func($this->_sockets[$sid]['on_accept'], $cid);
					} else {
						$read_data = fread($this->_sockets[$sid]['socket'], 65536);
						
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
			fclose($this->_sockets[$id]['socket']);
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
