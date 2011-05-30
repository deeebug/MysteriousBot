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
##  [*] Last edit: 5/29/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\Event;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;

class Server {
	public $clients  = array(); // The Bot's Clients
	public $users    = array(); // The NETWORKS Clients
	public $uuids    = array(); // The NETWORK ID <--> Nick ID
	public $channels = array(); // The NETWORKS Channels
	public $botchans = array(); // The Bot's Clients Channels currently joined.
	
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
		if ( $this->_connected === false && $data['command'] == 'NOTICE' ) {
			$this->send_welcome();
			$this->_connected = true;
			$this->_lastping = time();
			
			return;
		}
		
		// Do the ping. We won't plugins handle it, we want the ping out ASAP!
		if ( substr($data['raw'], 0, 4) == 'PING' ) {
			$this->_lastping = time();
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Ping? Pong!');
			$this->raw('PONG '.substr($data['raw'], 5));
			return;
		}
		
		if ( $data['command'] == 'CTCP' ) {
			$config = Config::get_instance();
			if ( $config->get('ctcp.'.strtolower($data['args'][0])) !== false )
				$reply = $config->get('ctcp.'.strtolower($data['args'][0]));
			else if ( $config->get('ctcp.__default') !== false )
				$reply = $config->get('ctcp.__default');
			else
				$reply = 'Unknown command!';
			
			$this->notice($data['nick'], $reply, $data['args'][0].': '.$data['_bot_to']);
			return;
		}
		
		// Send it out to the Plugin System
		Event::cast_server('irc.'.strtolower($data['command']), $data);
	}
	
	public function raw($payload) {
		Socket::get_instance()->write($this->_sid, $payload);
	}
	
	public function privmsg($channel, $message, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' PRIVMSG '.$channel.' :'.$message);
	}
	
	public function notice($to, $message, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' NOTICE '.$to.' :'.$message);
	}
	
	public function ctcp($to, $message, $bot) {
		$this->privmsg($to, chr(1).$message.chr(1), $bot);
	}
	
	public function action($channel, $message, $bot) {
		$this->privmsg($channel, chr(1).$message.chr(1), $bot);
	}
	
	public function join($channel, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' JOIN '.$channel);
	}
	
	public function part($channel, $message, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' PART '.$channel.' '.$message);
	}
	
	public function quit($channel, $message, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' QUIT '.$channel.' '.$message);
	}
	
	public function _fixbotuuid($bot) {
		if ( substr($bot, 0, 2) == 'S_' && count(explode('-', $bot)) == 2 ) {
			$botuuid = explode('-', $bot);
			$botuuid = $botuuid[1];
			
			if ( isset($this->_settings['clients'][$botuuid]) )
				return $this->_settings['clients'][$botuuid]['nick'];
			else
				return $bot;
		}
		
		return $bot;
	}
	
	//=========================================================
	//=====================END "Public" Methods
	//=========================================================
	
	private function send_welcome() {
		$out = array();
		
		$out[] = 'PASS '.$this->_settings['linkpass'];
		$out[] = 'SERVER '.$this->_settings['linkname'].' 1 :'.$this->_settings['linkdesc'];
		$out[] = 'EOS';
		
		$this->raw($out);
		$out = array();
		
		// We're dicks. Kill anyone using one of out bot's nick.
		// First spawn a TEMP client to do the physical killing.
		$nick = 'Services['.uniqid().']';
		
		// Now KILL the nick, if it's being used, and SQLine that baby!
		$this->raw('NICK '.$nick.' 2 '.time().' services '.$this->_settings['linkname'].' '.$this->_settings['linkname'].' 0 :'.$this->_settings['linkdesc'].' Temp Service Bot');
		foreach ( $this->_settings['clients'] AS $botuuid => $settings ) {
			$out[] = ':'.$nick.' KILL '.$settings['nick'].' :Sorry, this nick is being used by the IRC Bot Services';
			$out[] = 'SQLINE '.$settings['nick'].' :Nick is being used for '.$this->_settings['linkname'].' services';
		}
		
		$out[] = ':'.$nick.' QUIT Good Bye!';
		
		$this->raw($out);
		
		$out = array();
		
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
			
			$welcome = 'NICK '.$settings['nick'].' 2 '.time().' '.$settings['ident'].' '.$host.' '.$this->_settings['linkname'].' 0 :'.$settings['name'];
			
			if ( isset($settings['mode']) && !empty($settings['mode']) )
				$mode = ':'.$settings['nick'].' MODE '.$settings['nick'].' +'.$settings['mode'];
			else
				$mode = ':'.$settings['nick'].' MODE '.$settings['nick'].' +B';
			
			$out[] = $welcome;
			$out[] = $mode;
			
			if ( isset($settings['autojoin']) && !empty($settings['autojoin']) ) {
				$out[] = ':'.$settings['nick'].' JOIN '.implode(',', $settings['autojoin']);
				$this->botchans[$botuuid] = $settings['autojoin'];
			}
			
			if ( isset($this->_settings['globalchan']) && !empty($this->_settings['globalchan']) ) {
				$out[] = ':'.$settings['nick'].' JOIN '.$this->_settings['globalchan'];
				$this->botchans[$botuuid][] = $this->_settings['globalchan'];
			}
			
			$this->_clients[] = $settings['nick'];
		}
		
		$this->raw($out);
	}
}
