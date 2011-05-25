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
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;

class Socket extends Singleton {
	private $queue_write = array();
	private $_loop = true;
	private $_sockets = array();
	
	const LISTENER = 2;
	const CLIENT = 4;

	public function add_client($host, $port, $ssl, $callback) {
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
		
		return $id;
	}
	
	public function add_listener($ip, $port, $callback) {
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
			'socket'   => $socket,
			'ip'       => $ip,
			'port'     => $port,
			'callback' => $callback
		);
		
		return $id;
	}
	
	public function loop() {
		while ( $this->_loop === true ) {
			// Start with the timers
			// ---timers go here
			//Timers::get_instance()->tik();
			
			// Get the logger instance
			$logger = Logger::get_instance();
			
			// What do we need to read?
			$read = array();
			foreach ( $this->_sockets AS $id => $data )
				$read[$id] = $data['socket'];
			
			// What about writes?
			$write = array();
			foreach ( $this->queue_write AS $id => $data )
				$write[$id] = $this->_sockets[$data['socketid']]['socket'];
				
			// It empty?
			if ( empty($read) && empty($write) )
				continue;
			
			$e = null;
			if ( socket_select($read, $write, $e, 1) < 1 )
				continue;
				
			// Do the writes
			if ( !empty($write) ) {
				foreach ( $write AS $socket )
					$socketids[] = $this->_getSID($socket);
				
				foreach ( $this->queue_write AS $data ) {
					if ( in_array($data['socketid'], $socketids) ) {
						$length = strlen($data['payload']);
						$writed = 0;
						while ( $writed < $length ) {
							$writed += socket_write($this->_sockets[$data['socketid']]['socket'], $data['payload']);
							$logger->debug(__FILE__, __LINE__, '[Socket] Wrote payload to '.$data['socketid'].' :'.$data['payload']);
						}
						array_shift($this->queue_write);
					} else {
						break;
					}
				}
			}
			
			if ( !empty($read) ) {
				foreach ( $read AS $socket )
					$socketids[] = $this->_getSID($socket);
				
				foreach ( $socketids AS $id ) {
					switch ( substr($id, 0, 1) ) {
						case 'L':
							if ( ($client = @socket_accept($this->_sockets[$id]['socket']) ) === FALSE) continue;
							$cid = uniqid('C');
							$addr = '';
							$port = 0;
							socket_getpeername($client, $addr, $port);
							
							$this->_sockets[$cid] = array(
								'socket' => $client,
								'address' => $addr,
								'port' => $port,
								'callback' => $this->_sockets[$id]['callback']
							);
						break;
						
						default:
						case 'C':
							$data = explode("\n", socket_read($this->_sockets[$id]['socket'], 65536));
							
							foreach ( $data AS $line ) {
								if ( strlen($line) == 0 ) continue;
								
								$logger->debug(__FILE__, __LINE__, '[Socket] Got raw data for socketid '.$id.' :'.$line);
								call_user_func($this->_sockets[$id]['callback'], $id, $line);
							}
						break;
					}
				}
			}
			// Now that all the fuss is over, we finally...SLEEP!
			usleep(1000);
		}
	}
	
	private function _getSID($socket) {
		foreach ($this->_sockets AS $id => $data)
			if ($data['socket'] == $socket) return $id;
	}
	
	public function write($socketid, $payload) {
		if ( !is_array($payload) )
			$payload = array($payload);
		
		foreach ( $payload AS $pload ) {
			$this->queue_write[] = array(
				'socketid' => $socketid,
				'payload'  => $pload."\r\n"
			);
		}
	}
}
