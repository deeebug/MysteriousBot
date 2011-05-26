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
##  [?] File name: server.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/24/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;

class Server {
	private $_connected = false;
	private $_lastping;
	private $_sid;
	private $_settings;
	
	public function __construct($settings) {
		$this->_settings = $settings;
	}
	
	public function set_sid($sid) {
		$this->_sid = $sid;
	}
	
	public function on_raw($data) {
		// Are we even connected? Send out welcome message!
		if ( $this->_connected === false ) {
			$this->send_welcome();
			$this->_lastping = time();
			$this->_connected = true;
			
			return;
		}
		
		// Do the ping. We won't plugins handle it, we want the ping out ASAP!
		if ( substr($data['raw'], 0, 4) == 'PING' ) {
			$this->_lastping = time();
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Ping? Pong!');
			$this->raw('PONG '.substr($data['raw'], 5));
			return;
		}
		
		// Send it out to the Plugin System
		//Event::cast($data['type'], $data);
	}
	
	public function raw($payload) {
		Socket::get_instance()->write($this->_sid, $payload);
	}
	
	//=========================================================
	//=====================END "Public" Methods
	//=========================================================
	
	private function send_welcome() {
		$out = array();
		
		$out[] = 'PASS '.$this->_settings['linkpass'];
		$out[] = 'SERVER '.$this->_settings['linkname'].' 1 :'.$this->_settings['linkdesc'];
		$out[] = 'EOS';
		
		foreach ( $this->_settings['clients'] AS $botuuid => $settings ) {
			$required_settings = array(
				'nick', 'ident', 'name', 'mode'
			);
			foreach ( $required_settings AS $setting ) {
				if ( !isset($settings[$setting]) ) {
					Logger::get_instance()->warning(__FILE__, __LINE__, '[BOT SERVER] Could not spawn bot server client '.$uuid.' - Missing '.$setting);
				}
			}
			
			if ( isset($settings['host']) && !empty($settings['host']) )
				$host = $settings['host'];
			else
				$host = $this->_settings['linkname'];
			
			//this->send_data('NICK', 'Defcon 2 '.time().' Defcon '.$config['linkname'].' '.$config['linkname'].' 0 :Defcon');
			$welcome = 'NICK '.$settings['nick'].' 2 '.time().' '.$settings['ident'].' '.$host.' '.$this->_settings['linkname'].' 0 :'.$settings['name'];
			
			if ( isset($settings['mode']) && !empty($settings['mode']) )
				$mode = ':'.$settings['nick'].' MODE '.$settings['nick'].' +'.$settings['mode'];
			else
				$mode = ':'.$settings['nick'].' MODE '.$settings['nick'].' +B';
			
			$out[] = $welcome;
			$out[] = $mode;
			
			if ( isset($settings['autojoin']) && !empty($settings['autojoin']) )
				$out[] = ':'.$settings['nick'].' JOIN '.implode(',', $settings['autojoin']);
			
			if ( isset($this->_settings['globalchan']) && !empty($this->_settings['globalchan']) )
				$out[] = ':'.$settings['nick'].' JOIN '.$this->_settings['globalchan'];
		}
		
		$this->raw($out);
	}
}
