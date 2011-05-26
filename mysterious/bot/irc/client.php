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
#use Mysterious\Bot\Event;
#use Mysterious\Bot\Channels\Manager AS ChannelManager;

class Client extends Singleton {
	private $_connected = false;
	private $_lastping;
	private $_sid;
	private $_curnick;
	
	public function set_sid($sid) {
		$this->_sid = $sid;
	}
	
	public function handle_read($sid, $raw) {
		if ( $sid != $this->_sid ) return; // Why?! Only one instance ATM.
		
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
		
		// Are we even connected? Send out welcome message!
		if ( $this->_connected === false ) {
			$this->send_welcome();
			$this->_lastping = time();
			$this->_connected = true;
			
			return;
		}
		
		// Do the ping. We won't plugins handle it, we want the ping out ASAP!
		if ( substr($raw, 0, 4) == 'PING' ) {
			$this->_lastping = time();
			$this->raw('PONG '.substr($raw, 5));
			return;
		}
		
		// No type, then WTF are you?
		if ( (!isset($data['type']) || empty($data['type'])) && (!isset($data['command']) || empty($data['command'])) ) {
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Empty type - Printing data');
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
		
		if ( $data['command'] === 'JOIN' ) {
			print_r($data);
		}
		
		if ( $data['command'] === 'PART' ) {
			print_r($data);
		}
		
		if ( $data['command'] === 'QUIT' ) {
			print_r($data);
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
		$config = Config::get_instance();
		$out = array();
		
		if ( $config->get('connection.password') !== false && $config->get('connection.password') != '' )
			$out[] = 'PASS '.$config->get('connection.password');
		
		$out[] = 'USER '.$config->get('irc.ident').' 2 * :'.$config->get('irc.name');
		$out[] = 'NICK '.$config->get('irc.nick');
		
		if ( $config->get('irc.oper.use') !== false ) {
			$out[] = 'OPER '.$config->get('irc.oper.username').' '.$config->get('irc.oper.password');
		}
		
		$this->raw($out);
		$this->curnick = $config->get('irc.nick');
	}
}
