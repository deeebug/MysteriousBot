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
##  [?] File name: parser.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/25/2011                            ##
##  [*] Last edit: 5/30/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Parser {
	const REGEXMSG = "^(?P<message>((?P<prefix>:((?P<nick>[A-Za-z][a-z0-9\-\[\]\`^{}]*)|(?P<servername>((?P<host>(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9]))|(?P<ip>(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])))))(!(?P<user>[^ |\r|\n]+?))?(@(?P<userhost>(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9]))?)?) )?(?<command>([a-zA-Z]+)|[0-9]{3}) (?P<params>.+)?)$";
	
	public static $_statusmodes = array('q', 'a', 'o', 'h', 'v');
	public static $prefix;
	public static $nick;
	public static $servername;
	public static $user;
	public static $userhost;
	public static $command;
	public static $params;
	public static $channel;
	public static $message;
	public static $is_action;
	
	public static function new_instance($data, $client=true) {
		if ( $client === true )
			return self::_parse_client($data);
		else
			return self::_parse_server($data);
	}
	
	public static function flush() {
		self::$prefix = self::$nick = self::$servername = self::$user = self::$userhost = self::$command = self::$params = self::$channel = self::$is_action = self::$message = null;
	}
	
	public static function format() {
		return trim_r(array(
			'prefix'     => self::$prefix,
			'servername' => self::$servername,
			'nick'       => self::$nick,
			'ident'      => self::$user,
			'host'       => self::$userhost,
			'fullhost'   => ((!empty(self::$nick) && !empty(self::$user) && !empty(self::$userhost)) ? self::$nick.'!'.self::$user.'@'.self::$userhost : ''),
			'command'    => self::$command,
			'params'     => self::$params,
			'channel'    => self::$channel,
			'is_action'  => self::$is_action,
			'message'    => self::$message,
			'args'       => explode(' ', self::$message)
		));
	}
	
	 /**
	 * PHP version 5
	 *
	 * Copyright (c) 2010 James Harrison (jjlharrison.me.uk),
	 * All rights reserved.
	 *
	 * Redistribution and use in source and binary forms, with or without
	 * modification, are permitted provided that the following conditions are met:
	 *
	 *     * Redistributions of source code must retain the above copyright
	 *       notice, this list of conditions and the following disclaimer.
	 *     * Redistributions in binary form must reproduce the above copyright
	 *       notice, this list of conditions and the following disclaimer in the
	 *       documentation and/or other materials provided with the distribution.
	 *
	 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
	 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
	 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
	 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
	 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	 * POSSIBILITY OF SUCH DAMAGE.
	 *
	 * @author James Harrison
	 * @copyright  2010 James Harrison (jjlharrison.me.uk)
	 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License (revised)
	 * @version    1.0
	 */
	public static function _parse_client($data) {
		self::flush();

		if ( preg_match("/".self::REGEXMSG."/", trim($data['raw']), $msg) ) {
			self::$prefix = $msg['prefix'];
			self::$nick = $msg['nick'];
			self::$servername = $msg['servername'];
			self::$user = $msg['user'];
			self::$userhost = $msg['userhost'];
			self::$command = $msg['command'];

			//Parse params:
			$params = explode(' ', $msg['params']);
			$t = false;
			$trailing = '';

			foreach($params as $param){
				if ( $t || substr($param, 0, 1)==':' ) { //Trailing param
					$trailing .= (($t===false)?substr($param, 1):' '.$param);
					$t = true;
				} else { //Middle param
					self::$params[] = $param;
				}
			}

			if ( $t ) {
				self::$params[] = $trailing;
			}

			// Get channel and stuff.
			if ( empty($msg['servername']) && count(self::$params) == 2 ) {
				$channel = self::$channel = self::$params[0];
				$message = self::$message = self::$params[1];
			}

			// Is it a join, well they are different!
			if ( $msg['command'] === 'JOIN' ) {
				$tmp = explode('!', substr(self::$prefix, 1));
				$nick = $tmp[0];
				$tmp = explode('@', $tmp[1]);
				$ident = $tmp[0];
				$host = $tmp[1];
				
				self::$nick = $nick;
				self::$user = $ident;
				self::$userhost = $host;
				self::$channel = self::$params[0];
				self::$servername = '';
			}

			if ( $msg['command'] === 'PART' ) {
				self::$channel = self::$params[0];
			}

			if ( $msg['command'] == 'PRIVMSG' && substr(self::$channel, 0, 1) != '#' ) {
				self::$channel = self::$nick;
			}
			
			// Check if it's a CTCP/Action
			$is_action = false;
			if ( isset($message) && isset($channel) && ($msg['command'] == 'PRIVMSG' || $msg['command'] == 'NOTICE') && substr($message, 0, 1) == chr(1) && substr($message, -1) == chr(1) ) {
				if ( substr($channel, 0, 1) == '#' ) //It's a ACTION
					$is_action = true;
				else // It's a CTCP
					self::$command = 'CTCP';
				
				self::$message = substr($message, 1, -1);
				self::$is_action = $is_action;
			}

			return self::format();
		} else {
			throw new IRCParserException('Message passed to IRCMessage constructor in invalid format ('.$data['raw'].').');
		}
	}
	
	// Begin long, custom parser for server
	// Very, very messy! I wrote this at ~1AM!
	public static function _parse_server($data) {
		if ( substr($data['raw'], 0, 4) == 'PING' ) return $data; // Don't parse a ping, no need.
		
		// The reason we get the bot instance, is to add channels/nick info to it directly.
		// Maybe sometime I'll move this to take place in the server.
		$bot = BotManager::get_instance()->get_bot($data['_botid']);
		
		$data['raw'] = trim($data['raw']);
		$parts = explode(' ', $data['raw']);
		
		if ( substr($data['raw'], 0, 1) == ':' ) {
			// Done by user
			$data['command'] = $parts[1];
			
			switch ( $parts[1] ) {
				case 'MODE':
					$nick = substr($parts[0], 1);
					if ( substr($parts[3], 0, 1) == ':' ) $parts[3] = substr($parts[3], 1);
					
					switch ( substr($parts[2], 0, 1) ) {
						case '#':
							if ( !isset($bot->channels[$parts[2]]) ) return $data; //wut?
							// Could be a channel mode, or a channel permission mode
							// First, we have to get all the users we're changing modes ON.
							// nick + mode + channel + mode itself is 4. Keep array_pop until we have 4
							$tmp = $parts;
							$nicks = array();
							while ( count($tmp) != 4 ) {
								$_nick = array_pop($tmp);
								if ( is_numeric($_nick) ) continue;
								$nicks[] = $_nick;
							}
							
							$modes =& $bot->channels[$parts[2]]->modes;
						break;
						
						// User mode.
						default:
							if ( !isset($bot->users[$parts[2]]) ) return $data; //wut?
							$modes =& $bot->users[$parts[2]]->modes;
						break;
					}
					
					$chan = $parts[2];
					
					$i = 0;
					$add = null;
					$to = null;
					
					for ( $i=0,$s=strlen($parts[3]); $s>=$i; $i++ ) {
						$char = substr($parts[3], $i, 1);
						
						if ( $char == '+' ) {
							$add = true;
							$to = null;
							isset($nicks) and $to = array_shift($nicks);
						} else if ( $char == '-' ) {
							$add = false;
							$to = null;
							isset($nicks) and $to = array_shift($nicks);
						} else {
							if ( empty($char) ) continue; // Can't do anything..
							
							// User permission on channel mode
							if ( !empty($to) ) {
								if ( in_array($char, self::$_statusmodes) )
									$modes =& $bot->channels[$parts[2]]->nicks[$to]['modes'];
								else
									$modes =& $bot->channels[$parts[2]]->modes;
							}
							
							if ( $char == 'b' ) {
								!empty($nicks) and $to = array_shift($nicks);
								if ( $add === true ) {
									list($_nick, $_identhost) = explode('!', $to);
									list($_ident, $_host) = explode('@', $_identhost);
									$bot->channels[$chan]->banlist[] = array(
										'nick'     => $_nick,
										'ident'    => $_ident,
										'host'     => $_host,
										'fullhost' => $to,
										'bannedby' => $nick,
										'bantime'  => time(),
									);
								} else {
									foreach ( $bot->channels[$chan]->banlist AS $key => $data ) {
										if ( $data['fullhost'] == $to )
											unset($bot->channels[$chan]->banlist[$key]);
									}
								}
							} else if ( $char == 'I' ) {
								!empty($nicks) and $to = array_shift($nicks);
								if ( $add === true ) {
									list($_nick, $_identhost) = explode('!', $to);
									list($_ident, $_host) = explode('@', $_identhost);
									$bot->channels[$chan]->invites[] = array(
										'nick'     => $_nick,
										'ident'    => $_ident,
										'host'     => $_host,
										'fullhost' => $to,
										'bannedby' => $nick,
										'bantime'  => time(),
									);
								} else {
									foreach ( $bot->channels[$chan]->invites AS $key => $data ) {
										if ( $data['fullhost'] == $to )
											unset($bot->channels[$chan]->banlist[$key]);
									}
								}
							} else if ( $add === true ) {
								if ( strpos($modes, $char) === false )
									$modes .= $char;
							} else {
								$modes = str_replace($char, '', $modes);
							}
						}
					}
					unset($add, $i, $modes, $to);
					
					if ( substr($parts[2], 0, 1) != '#' ) {
						if ( strpos($bot->users[$parts[2]]->modes, 'o') === false )
							$bot->users[$parts[2]]->ircop = false;
						else
							$bot->users[$parts[2]]->ircop = true;
					}
					
					$data['channel'] = $parts[2];
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = isset($bot->users[substr($parts[0], 1)]->ident) ?: null;
					$data['host'] = isset($bot->users[substr($parts[0], 1)]->host) ?: null;
					$data['modes'] = $parts[3];
				break;
				
				case 'TOPIC':
					if ( !isset($bot->channels[$parts[2]]) ) break; //Parser did an uh-oh.
					
					$topic = explode(' :', substr($data['raw'], 1));
					$topic = $topic[1];
					$oldtopic = $bot->channels[$parts[2]]->topic;
					
					$bot->channels[$parts[2]]->topic = $topic;
					$bot->channels[$parts[2]]->topicset = $parts[4];
					$bot->channels[$parts[2]]->topicby = $parts[3];
					
					if ( $bot->channels[$parts[2]]->created > $parts[4] )
						$bot->channels[$parts[2]]->created = $parts[4];
					
					unset($topic);
					
					$data['channel'] = $parts[2];
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = $bot->users[substr($parts[0], 1)]->ident;
					$data['host'] = $bot->users[substr($parts[0], 1)]>host;
					$data['topic'] = $topic;
					$data['topicby'] = $parts[3];
					$data['oldtopic'] = $oldtopic;
				break;
				
				case 'SETHOST':
					$nick = substr($parts[0], 1);
					if ( !isset($bot->users[$nick]) ) break; //Parser did an uh-oh.
					
					$bot->users[$nick]->host = $parts[2];
					$bot->users[$nick]->fullhost = $bot->users[$nick]->nick.'!'.$bot->users[$nick]->ident.'@'.$bot->users[$nick]->host;
				break;
				
				case 'SETIDENT':
					$nick = substr($parts[0], 1);
					if ( !isset($bot->users[$nick]) ) break; //Parser did an uh-oh.
					
					$bot->users[$nick]->ident = $parts[2];
					$bot->users[$nick]->fullhost = $bot->users[$nick]->nick.'!'.$bot->users[$nick]->ident.'@'.$bot->users[$nick]->host;
				break;
				
				case 'SETNAME':
					$nick = substr($parts[0], 1);
					if ( !isset($bot->users[$nick]) ) break; //Parser did an uh-oh.
					
					$name = explode(' :', substr($data['raw'], 1));
					$name = $name[1];
					
					$bot->users[$nick]->name = $name;
				break;
				
				case 'JOIN':
					if ( strpos($parts[2], ',') !== false ) {
						$channels = explode(',', $parts[2]);
					} else {
						$channels = array($parts[2]);
					}
					
					foreach ( $channels AS $channel ) {
						$nick = substr($parts[0], 1);
						
						$bot->users[$nick]->channels[] = $channel;
						$bot->users[$nick]->channelcount++;
						
						if ( isset($bot->channels[$channel]) ) {
							$bot->channels[$channel]->usercount++;
							$bot->channels[$channel]->nicks[$nick] = array(
								'nick'    => $nick,
								'modes'   => ''
							);
							continue;
						}
						
						$bot->channels[$channel] = Channel::new_instance();
						$bot->channels[$channel]->usercount++;
						$bot->channels[$channel]->nicks[$nick] = array(
							'nick'    => $nick,
							'modes'   => ''
						);
					}
					
					$data['channel'] = $parts[2];
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = $bot->users[substr($parts[0], 1)]->ident;
					$data['host'] = $bot->users[substr($parts[0], 1)]->host;
				break;
				
				case 'PRIVMSG':
					$msg = explode(' :', substr($data['raw'], 1));
					$msg = $msg[1];
					
					$data['message'] = $msg;
					$data['args'] = explode(' ', trim($msg));
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = isset($bot->users[substr($parts[0], 1)]->ident) ?: null;
					$data['host'] = isset($bot->users[substr($parts[0], 1)]->host) ?: null;
					$data['channel'] = substr($parts[2], 0, 1) == '#' ? $parts[2] : $data['nick'];
					$data['is_action'] = false;
					if ( substr($data['channel'], 0, 1) != '#' )
						$data['_bot_to'] = $parts[2];
				break;
				
				case 'NOTICE':
					$msg = explode(' :', substr($data['raw'], 1));
					$msg = $msg[1];
					
					$data['message'] = $msg;
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = isset($bot->users[substr($parts[0], 1)]->ident) ?: null;
					$data['host'] = isset($bot->users[substr($parts[0], 1)]->host) ?: null;
					$data['channel'] = $parts[2];
					$data['to'] = $parts[2];
					if ( substr($data['channel'], 0, 1) != '#' )
						$data['_bot_to'] = $parts[2];
				break;
				
				case 'NICK':
					$bot->users[$parts[2]] = $bot->users[substr($parts[0], 1)];
					$bot->users[$parts[2]]->nick = $parts[2];
					
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = $bot->users[substr($parts[0], 1)]->ident;
					$data['host'] = $bot->users[substr($parts[0], 1)]->host;
					$data['oldnick'] = key($bot->users[substr($parts[0], 1)]);
					
					unset($bot->users[substr($parts[0], 1)]);
				break;
				
				case 'PART':
					$nick = substr($parts[0], 1);
					$channel = $parts[2];
					
					if ( !isset($bot->channels[$channel]) ) break; //Parser did an uh-oh.
					
					unset($bot->channels[$channel]->nicks[$nick]);
					$bot->channels[$channel]->usercount--;
					
					unset($bot->users[$nick]->channels[array_search($channel, $bot->users[$nick]->channels)]);
					$bot->users[$nick]->channelcount--;
					
					// No one left, remove the channel
					if ( $bot->channels[$channel]->usercount == 0 )
						unset($bot->channels[$channel]);
					
					$data['nick'] = $nick;
					$data['ident'] = $bot->users[$nick]->ident;
					$data['host'] = $bot->users[$nick]->host;
					$data['channel'] = $channel;
				break;
				
				case 'QUIT':
					$nick = substr($parts[0], 1);
					$channel = $parts[2];
					
					foreach ( $bot->users[$nick]->channels AS $channel ) {
						if ( ($pos = array_search($nick, $bot->channels[$channel]->nicks)) !== false ) {
							$bot->channels[$channel]->usercount--;
							unset($bot->channels[$channel]->nicks[$pos]);
						}
					}
					
					unset($bot->users[$nick]);
					
					if ( $bot->channels[$channel]->usercount == 0 )
						unset($bot->channels[$channel]);
					
					
					$data['nick'] = $nick;
					$data['ident'] = $bot->users[$nick]->ident;
					$data['host'] = $bot->users[$nick]->host;
				break;
			}
		} else {
			// Done by server
			$data['command'] = $parts[0];
			
			switch ( $parts[0] ) {
				// Introducing a new user
				case 'NICK':
					$name = explode(' :', $data['raw']);
					$name = $name[1];
					
					$bot->users[$parts[1]] = User::new_instance();
					$bot->users[$parts[1]]->connected = $parts[3];
					$bot->users[$parts[1]]->nick = $parts[1];
					$bot->users[$parts[1]]->ident = $parts[4];
					$bot->users[$parts[1]]->host = $parts[5];
					$bot->users[$parts[1]]->servername = $parts[6];
					$bot->users[$parts[1]]->name = $name;
					$bot->users[$parts[1]]->ircop = false;
					$bot->users[$parts[1]]->modes = '';
					
					$bot->uuids[$parts[2]] = $parts[1];
				break;
				
				case 'TOPIC':
					if ( !isset($bot->channels[$parts[1]]) ) break; //Parser did an uh-oh.
					
					$topic = explode(' :', substr($data['raw'], 1));
					$topic = $topic[1];
					
					$bot->channels[$parts[1]]->topic = $topic;
					$bot->channels[$parts[1]]->topicset = $parts[3];
					$bot->channels[$parts[1]]->topicby = $parts[2];
					
					if ( $bot->channels[$parts[1]]->created > $parts[3] )
						$bot->channels[$parts[1]]->created = $parts[3];
					
					unset($topic);
				break;
			}
		}
		
		// Check if it's a CTCP/Action
		if ( ($data['command'] == 'PRIVMSG' || $data['command'] == 'NOTICE') && substr($data['message'], 0, 1) == chr(1) && substr($data['message'], -1, 1) == chr(1) ) {
			if ( substr($data['channel'], 0, 1) == '#' ) //It's a ACTION
				$data['is_action'] = true;
			else // It's a CTCP
				$data['command'] = 'CTCP';
			
			$data['message'] = substr($data['message'], 1, -1);
		}
		
		$data['fullhost'] = ((isset($data['nick']) && isset($data['ident']) && isset($data['host'])) ? $data['nick'].'!'.$data['ident'].'@'.$data['host'] : '');
		$data['args'] = (isset($data['message']) ? explode(' ', $data['message']) : null);
		$data['_fromserver'] = true;
		
		return $data;
	}
}

class IRCParserException extends \Exception { }

/*
 * @author: akarmenia at gmail dot com
 * @url: http://www.php.net/manual/en/function.trim.php#103278
 */
function trim_r($array) {
    if (is_string($array)) {
        return trim($array);
    } else if (!is_array($array)) {
        return '';
    }
    $keys = array_keys($array);
    for ($i=0; $i<count($keys); $i++) {
        $key = $keys[$i];
        if ( is_array($array[$key]) ) {
            $array[$key] = trim_r($array[$key]);
        } else if ( is_string($array[$key]) ) {
            $array[$key] = trim($array[$key]);
        }
    }
    return $array;
}
