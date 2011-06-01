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
##  [*] Last edit: 6/1/2011                           ##
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
	
	// Looks a lot better now! :)
	public static function _parse_server($data) {
		if ( substr($data['raw'], 0, 4) == 'PING' ) return $data; // Don't parse a ping, no need.
		
		// We use the bot array to fill in information, like ident when nick is only given.
		$bot = BotManager::get_instance()->get_bot($data['_botid']);
		
		$data['raw'] = trim($data['raw']);
		$parts = explode(' ', $data['raw']);
		
		if ( substr($data['raw'], 0, 1) == ':' ) {
			// Done by user
			$data['command'] = $parts[1];
			
			switch ( $parts[1] ) {
				case 'MODE':
					$tmp = $parts;
					array_shift($tmp); array_shift($tmp); array_shift($tmp);
					array_shift($tmp); array_pop($tmp);
					
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = isset($bot->users[substr($parts[0], 1)]->ident) ?: null;
					$data['host'] = isset($bot->users[substr($parts[0], 1)]->host) ?: null;
					$data['channel'] = $parts[2];
					$data['modes'] = $parts[3];
					$data['affects'] = $tmp;
				break;
				
				case 'TOPIC':
					if ( !isset($bot->channels[$parts[2]]) ) break; //Parser did an uh-oh.
					
					list(,$topic) = explode(' :', substr($data['raw'], 1));
				
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = $bot->users[substr($parts[0], 1)]->ident;
					$data['host'] = $bot->users[substr($parts[0], 1)]>host;
					
					$data['channel'] = $parts[2];
					$data['topic'] = $topic;
					$data['oldtopic'] = $bot->channels[$parts[2]]->topic;
					$data['by'] = $parts[3];
					$data['set'] = time();
				break;
				
				case 'SETHOST':
					$nick = substr($parts[0], 1);
					if ( !isset($bot->users[$nick]) ) break; //Parser did an uh-oh.
					
					$oldhost = $bot->users[$nick]->host;
					$bot->users[$nick]->host = $parts[2];
					$bot->users[$nick]->fullhost = $bot->users[$nick]->nick.'!'.$bot->users[$nick]->ident.'@'.$bot->users[$nick]->host;
					
					$data['nick'] = $nick;
					$data['ident'] = $bot->users[$nick]->ident;
					$data['host'] = $parts[2];
					$data['oldhost'] = $oldhost;
				break;
				
				case 'SETIDENT':
					$nick = substr($parts[0], 1);
					if ( !isset($bot->users[$nick]) ) break; //Parser did an uh-oh.
					
					$data['nick'] = $nick;
					$data['ident'] = $parts[2];
					$data['host'] = $bot->users[$nick]->host;
					$data['oldident'] = $bot->users[$nick]->ident;
				break;
				
				case 'SETNAME':
					$nick = substr($parts[0], 1);
					if ( !isset($bot->users[$nick]) ) break; //Parser did an uh-oh.
					
					list(,$name) = explode(' :', substr($data['raw'], 1));
					
					$data['nick'] = $nick;
					$data['ident'] = $bot->users[$nick]->ident;
					$data['host'] = $bot->users[$nick]->host;
					$data['name'] = $name;
					$data['oldname'] = $bot->users[$nick]->name;
				break;
				
				case 'JOIN':
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = $bot->users[substr($parts[0], 1)]->ident;
					$data['host'] = $bot->users[substr($parts[0], 1)]->host;
					$data['channel'] = $parts[2];
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
					$data['nick'] = substr($parts[0], 1);
					$data['ident'] = $bot->users[substr($parts[0], 1)]->ident;
					$data['host'] = $bot->users[substr($parts[0], 1)]->host;
					$data['oldnick'] = key($bot->users[substr($parts[0], 1)]);
				break;
				
				case 'PART':
					$nick = substr($parts[0], 1);
					
					$data['nick'] = $nick;
					$data['ident'] = $bot->users[$nick]->ident;
					$data['host'] = $bot->users[$nick]->host;
					$data['channel'] = $parts[2];
					$data['message'] = substr(implode(' ', $parts), strlen($parts[0].' '.$parts[1].' '.$parts[2].' :'));
				break;
				
				case 'QUIT':
					$nick = substr($parts[0], 1);
					
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
					list(,$name) = explode(' :', $data['raw']);
					
					$data['command']    = 'NICKCONNECT';
					$data['nick']       = $parts[1];
					$data['uuid']       = $parts[2];
					$data['connected']  = $parts[3];
					$data['ident']      = $parts[4];
					$data['host']       = $parts[5];
					$data['servername'] = $parts[6];
					$data['name']       = $name;
				break;
				
				case 'TOPIC':
					if ( !isset($bot->channels[$parts[1]]) ) break; //Parser did an uh-oh.
					
					list(,$topic) = explode(' :', substr($data['raw'], 1));
					
					$data['topic']    = $topic;
					$data['oldtopic'] = $bot->channels[$parts[1]]->topic;
					$data['channel']  = $parts[1];
					$data['by']       = $parts[2];
					$data['set']      = $parts[3];
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
