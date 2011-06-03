<?php
## ################################################## ##
##                   MysteriousBot                    ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: socketserver.php                   ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/29/2011                            ##
##  [*] Last edit: 6/1/2011                           ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\IRC\BotManager;

class SocketServer extends Singleton {
	const STATUS_WAITINGFORCHALLENGE = 2;
	const STATUS_CONNECTED = 4;
	const STATUS_EXIT = 8;
	
	private $_clients = array();
	private $_timerid = false;
	
	public function setup() {
		if ( $this->_timerid === false ) {
			$this->_timerid = Timer::register(60, array($this, 'cleanup'));
		}
	}
	
	public function cleanup() {
		foreach ( $this->_clients AS $socketid => $info ) {
			if ( time()-(5*60) >= $info['last_action'] ) {
				Socket::get_instance()->write($socketid, 'TIMEOUT');
				Socket::get_instance()->close($socketid);
			}
		}
	}
	
	public function new_connection($socketid) {
		if ( count($this->_clients) >= Config::get_instance()->get('socketserver.max_clients') ) {
			Socket::get_instance()->write($socketid, 'ERROR Too many clients are connected - Limit '.Config::get_instance()->get('socketserver.max_clients'));
			Socket::get_instance()->close($socketid);
			return;
		}
		
		$challenge = md5(uniqid().time().rand(1,9999));
		
		$this->_clients[$socketid] = array(
			'connected'   => time(),
			'last_action' => time(),
			'socketid'    => $socketid,
			'challenge'   => $challenge,
			'attempts'    => 0,
			'status'      => self::STATUS_WAITINGFORCHALLENGE,
		);
		
		Socket::get_instance()->write($socketid, 'CHALLENGE '.$challenge);
	}
	
	public function handle_read($socketid, $raw) {
		$raw = trim($raw);
		
		if ( $this->_clients[$socketid]['status'] == self::STATUS_WAITINGFORCHALLENGE ) {
			if ( strpos($raw, '|') !== false ) {
				$commands = explode('|', $raw);
				$authinfo = $commands[0];
			} else {
				$authinfo = $raw;
			}
			
			if ( empty($authinfo) || count(explode('-', $authinfo)) != 3 ) $authinfo = '---';
			list($password, $salt, $time) = explode('-', $authinfo);
			
			if ( empty($password) || empty($salt) || empty($time) ) {
				$this->_clients[$socketid]['attempts']++;
				
				if ( $this->_clients[$socketid]['attempts'] >= Config::get_instance()->get('socketserver.max_attempts', 10) ) {
					Socket::get_instance()->close($socketid);
					return;
				}
				
				$this->_clients[$socketid]['challenge'] = md5(uniqid().time().rand(1,9999));
				
				Socket::get_instance()->write($socketid, 'CHALLENGE '.$this->_clients[$socketid]['challenge'].' EMPTY_INFO');
				return;
			}
			
			// Failed attempt.
			if ( sha1($time.Config::get_instance()->get('socketserver.password').$this->_clients[$socketid]['challenge'].$salt) != $password ) {
				$this->_clients[$socketid]['attempts']++;
				
				if ( $this->_clients[$socketid]['attempts'] >= Config::get_instance()->get('socketserver.max_attempts', 10) ) {
					Socket::get_instance()->close($socketid);
					return;
				}
				
				$this->_clients[$socketid]['challenge'] = md5(uniqid().time().rand(1,9999));
				Socket::get_instance()->write($socketid, 'CHALLENGE '.$this->_clients[$socketid]['challenge'].' FAILED_ATTEMPT');
				return;
			}
			
			// Passed!
			if ( isset($commands) ) {
				// It's a connect-disconnect command. Just run EET.
				$return = array();
				$return[] = 'EXIT';
				for ( $i=1,$c=count($commands); $c > $i; $i++ )
					$return[] = $this->handle_command($commands[$i]);
				
				Socket::get_instance()->write($socketid, implode("\n", $return));
				Socket::get_instance()->close($socketid);
			} else {
				$this->_clients[$socketid]['status'] = self::STATUS_CONNECTED;
				Socket::get_instance()->write($socketid, 'CONNECTED');
				return;
			}
		}
		
		$this->_clients[$socketid]['last_action'] = time();
		$return = $this->handle_command($raw);
		if ( $return == self::STATUS_EXIT ) {
			Socket::get_instance()->close($socketid);
		} else {
			Socket::get_instance()->write($socketid, $return);
		}
	}
	
	private function handle_command($raw) {
		$parts = explode(' ', $raw);
		switch ( strtolower($parts[0]) ) {
			case 'logout':
				return self::STATUS_EXIT;
			break;
			
			case 'raw':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				$bot->raw(implode(' ', $parts));
			break;
			
			case 'privmsg':
			case 'message':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				if ( $bot instanceof Server ) {
					$botsubuuid = array_shift($parts);
					$channel = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->privmsg($channel, $message, $botsubuuid);
				} else {
					$channel = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->privmsg($channel, $message);
				}
				
				return 'OKAY SENT';
			break;
			
			case 'notice':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				if ( $bot instanceof Server ) {
					$botsubuuid = array_shift($parts);
					$channel = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->privmsg($channel, $message, $botsubuuid);
				} else {
					$channel = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->privmsg($channel, $message);
				}
				
				return 'OKAY SENT';
			break;
			
			case 'join':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				if ( $bot instanceof Server ) {
					$botsubuuid = array_shift($parts);
					$channel = array_shift($parts);
					
					$bot->join($channel, $botsubuuid);
				} else {
					$channel = array_shift($parts);
					$key = @array_shift($parts);;
					
					$bot->join($channel, $key);
				}
				
				return 'OKAY JOINED';
			break;
			
			case 'part':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				if ( $bot instanceof Server ) {
					$botsubuuid = array_shift($parts);
					$channel = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->part($channel, $message, $botsubuuid);
				} else {
					$channel = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->part($channel, $message);
				}
				
				return 'OKAY PARTED';
			break;
			
			case 'quit':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				if ( $bot instanceof Server ) {
					$botsubuuid = array_shift($parts);
					$message = implode(' ', $parts);
					
					$bot->quit($message, $botsubuuid);
				} else {
					$message = implode(' ', $parts);
					
					$bot->quit($message);
				}
				
				return 'OKAY QUIT';
			break;
			
			case 'get_nicks_object':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				return 'RESPONSE GET_NICKS_OBJECT '.sha1(serialize($bot->nicks)).' '.serialize($bot->nicks);
			break;
			
			case 'get_nicks':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				return 'RESPONSE GET_NICKS '.sha1(serialize(array_keys($bot->nicks))).' '.serialize(array_keys($bot->nicks));
			break;
			
			case 'get_channels':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				return 'RESPONSE GET_CHANNELS '.sha1(serialize(array_keys($bot->channels))).' '.serialize(array_keys($bot->channels));
			break;
			
			case 'get_channels_object':
				array_shift($parts);
				$botuuid = array_shift($parts);
				
				$bot = BotManager::get_instance()->get_bot($botuuid);
				if ( $bot === false )
					return 'ERROR Unknown Bot UUID.';
				
				return 'RESPONSE GET_CHANNELS_OBJECT '.sha1(serialize($bot->channels)).' '.serialize($bot->channels);
			break;
		}
	}
}
