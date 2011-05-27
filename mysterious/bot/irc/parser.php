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
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

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

/**
* Class to handle IRC protocol messages.
 * 
 * Note: This class was created based on information from RFC 1459 (http://tools.ietf.org/html/rfc1459). I do not guarantee
* that it is fully compliant with RFC 1459 but I did try.
*/
class Parser {
	
	/**
	 * The regular expression for parsing IRC protocol messages.
	 */
	const REGEXMSG = "^(?P<message>((?P<prefix>:((?P<nick>[A-Za-z][a-z0-9\-\[\]\`^{}]*)|(?P<servername>((?P<host>(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9]))|(?P<ip>(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])))))(!(?P<user>[^ |\r|\n]+?))?(@(?P<userhost>(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9]))?)?) )?(?<command>([a-zA-Z]+)|[0-9]{3}) (?P<params>.+)?)$";

	/**
	 * Property for storing the message as a raw string.
	 *
	 * @var string
	 */
	public static $prefix;
	public static $nick;
	public static $servername;
	public static $user;
	public static $userhost;
	public static $command;
	public static $params;
	public static $channel;
	public static $message;
	
	/**
	 * This function takes an IRC protocol message and parses it and constructs the
	 * IRCMessage object.
	 *
	 * @param string $message The IRC protocol message as a string.
	 * @author James Harrison
	 * @modified-by: debug <jtdroste@gmail.com>
	 */
	public static function new_instance($message) {
		self::flush();
		
		if ( preg_match("/".self::REGEXMSG."/", $message, $msg) ) {
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
				self::$channel = self::$params[0];
				self::$message = self::$params[1];
			}
			
			// Is it a join, well they are different!
			/*
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
			*/
			
			if ( $msg['command'] === 'PART' ) {
				self::$channel = self::$params[0];
			}
			
			return self::format();
		} else {
			throw new IRCParserException('Message passed to IRCMessage constructor in invalid format ('.$message.').');
		}
	}
	
	public static function flush() {
		self::$prefix = self::$nick = self::$servername = self::$user = self::$userhost = self::$command = self::$params = self::$channel = self::$message = null;
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
			'message'    => self::$message,
			'args'       => explode(' ', self::$message)
		));
	}
}

/**
* Exception for the IRCMessage class.
*/
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
