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
##  [?] File name: botmanager.php                     ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;
use Mysterious\Bot\Message;

class BotManager extends Singleton {
	private $_bots = array();
	private $_sid2bot = array();
	private $_bot2sid = array();
	
	public function create_client($uuid, $settings) {
		if ( isset($this->_bots[$uuid]) ) throw new BotManagerError('Bot UUID '.$uuid.' is already set! Maybe it is not so unique?');
		
		$this->_bots[$uuid] = new Client($settings);
	}
	
	public function create_server($uuid, $settings) {
		if ( isset($this->_bots[$uuid]) ) throw new BotManagerError('Bot UUID '.$uuid.' is already set! Maybe it is not so unique?');
		
		$this->_bots[$uuid] = new Server($settings);
	}
	
	public function set_sid($uuid, $sid) {
		if ( !isset($this->_bots[$uuid]) ) return false;
		
		$this->_sid2bot[$sid]  = $uuid;
		$this->_bot2sid[$uuid] = $sid;
		
		call_user_func(array($this->get_bot($this->_sid2bot[$sid]), 'set_sid'), $sid);
	}
	
	public function handle_read($sid, $raw) {
		if ( !isset($this->_sid2bot[$sid]) ) throw new BotManagerError('The SID provided is currently not tracked by the BotManager');
		
		// Lets parse the message
		$data = array(
			'raw' => $raw,
			'rawparts' => explode(' ', $raw),
			'socketid' => $sid
		);
		
		try {
			$parsed = Parser::new_instance($raw);
		} catch ( IRCParserException $e ) {
			Logger::get_instance()->warning(__FILE__, __LINE__, 'The IRC Parser threw an error! '.$e->getMessage());
			return;
		}
		$data = array_merge($data, $parsed);
		
		// Send it to the global Message class (for plugins, etc)
		Message::__newdata($data);
		
		// Pass it to the bot. :)
		call_user_func(array($this->get_bot($this->_sid2bot[$sid]), 'on_raw'), $data);
	}
	
	public function get_bot($uuid) {
		return isset($this->_bots[$uuid]) ? $this->_bots[$uuid] : null;
	}
	
	public function bot2sid($bot) {
		return isset($this->_bot2sid[$bot]) ? $this->_bot2sid[$bot] : null;
	}
	
	public function sid2bot($sid) {
		return isset($this->_sid2bot[$sid]) ? $this->_sid2bot[$sid] : null;
	}
	
	public function destroy_bot($uuid) {
		unset($this->_bots[$uuid]);
		return true;
	}
	
	public function write($bot, $payload) {
		Socket::get_instance()->write($this->_bot2sid[$bot], $payload);
	}
}

class BotManagerError extends \Exception { };
