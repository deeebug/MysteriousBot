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
##  [?] File name: client.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/24/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\Config;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;

class Client extends Singleton {
	private $_connected = false;
	private $_lastping;
	private $_sid;
	
	public function set_sid($sid) {
		$this->_sid = $sid;
	}
	
	public function handle_read($sid, $data) {
		if ( $sid != $this->_sid ) return; // Why?! Only one instance ATM.
		
		if ( $this->_connected === false ) {
			$this->send_welcome();
			$this->_lastping = time();
			$this->_connected = true;
			
			return;
		}
		
		if ( substr($data, 0, 4) == 'PING' ) {
			$this->raw('PONG '.substr($data, 5));
			return;
		}
		
		//:debug!de@bug PRIVMSG #chan :text
		$parts = explode(' ', $data);
		if ( isset($parts[3]) && $parts[3] == ':!die' ) {
			Logger::get_instance()->fatal(__FILE__, __LINE__, 'We be dead by '.substr($parts[1], 1).' !');
			return;
		}
	}
	
	public function raw($payload) {
		Socket::get_instance()->write($this->_sid, $payload);
	}
	
	//=========================================================
	//=====================END "Public" Methods
	//=========================================================
	
	private function send_welcome() {
		$config = Config::get_instance();
		$out = array();
		$out[] = 'USER '.$config->get('irc.ident').' 2 * :'.$config->get('irc.name');
		$out[] = 'NICK '.$config->get('irc.nick');
		
		if ( $config->get('irc.oper.use') !== false ) {
			$out[] = 'OPER '.$config->get('irc.oper.username').' '.$config->get('irc.oper.password');
		}
		
		$this->raw($out);
	}
}
