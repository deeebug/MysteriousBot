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
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;
#use Mysterious\Bot\Event;
#use Mysterious\Bot\Channels\Manager AS ChannelManager;

class Client {
	private $_connected = false;
	private $_curnick;
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
		
		// If its reply 001, update the nick to what the server gave us.
		if ( $data['command'] == '001' ) {
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Updating curnick from '.$this->curnick.' to '.$data['params'][0]);
			$this->curnick = $data['params'][0];
		}
		
		// If the person who joined the channel is us, zomg we joined a channel!
		if ( $data['command'] == 'JOIN' && $data['nick'] == $this->curnick ) {
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Sending who and mode +b on newly joined channel '.$data['channel']);
			//ChannelManager::add_channel($data['channel']);
			$this->raw('WHO '.$data['channel']);
			$this->raw('MODE '.$data['channel'].' +b');
		}
		
		// print_r 352
		if ( $data['command'] == '352' ) {
			print_r($data);
		}
		
		// print_r 353
		if ( $data['command'] == '353' ) {
			print_r($data);
		}
		
		if ( $data['command'] == '367' ) {
			print_r($data);
		}
		
		// Send it out to the Plugin System
		//Event::cast('irc.'.$data['type'], $data);
	}
	
	public function raw($payload) {
		Socket::get_instance()->write($this->_sid, $payload);
	}
	
	//=========================================================
	//=====================END "Public" Methods
	//=========================================================
	
	private function send_welcome() {
		$out = array();
		
		if ( isset($this->_settings['password']) && $this->_settings['password'] != '' )
			$out[] = 'PASS '.$this->_settings['password'];
		
		$out[] = 'USER '.$this->_settings['ident'].' 2 * :'.$this->_settings['name'];
		$out[] = 'NICK '.$this->_settings['nick'];
		
		if ( isset($this->_settings['oper']) && $this->_settings['oper']['use'] === true ) {
			$out[] = 'OPER '.$this->_settings['oper']['username'].' '.$this->_settings['oper']['password'];
		}
		
		$this->raw($out);
		$this->curnick = $this->_settings['nick'];
	}
}
