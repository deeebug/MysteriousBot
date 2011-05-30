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
##  [*] Last edit: 5/29/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\Event;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;

class Client {
	public $channels = array();
	
	private $_connected = false;
	private $_curnick;
	private $_lastping;
	private $_symbol2mode = array(
		'~' => 'q',
		'&' => 'a',
		'@' => 'o',
		'%' => 'h',
		'+' => 'v',
	);
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
		
		// Nickname is in use
		if ( $data['command'] == '433' ) {
			$numbers = rand(1,50);
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Nick '.$this->curnick.' is in use, changing nick to '.$this->curnick.$numbers);
			$this->curnick .= $numbers;
			$this->raw('NICK '.$this->curnick);
		}
		
		// Finished MOTD/No MOTD, send autojoin stuff
		if ( $data['command'] == '422' || $data['command'] == '376' ) {
			$this->send_connected();
		}
		
		if ( $data['command'] == 'CTCP' ) {
			$config = Config::get_instance();
			if ( $config->get('ctcp.'.strtolower($data['args'][0])) !== false )
				$reply = $config->get('ctcp.'.strtolower($data['args'][0]));
			else if ( $config->get('ctcp.__default') !== false )
				$reply = $config->get('ctcp.__default');
			else
				$reply = 'Unknown command!';
			
			$this->notice($data['nick'], $reply, $data['args'][0].': '.$data['channel']);
			return;
		}
		
		if ( $data['command'] == 'JOIN' ) print_r($data);
		
		// If the person who joined the channel is us, zomg we joined a channel!
		if ( $data['command'] == 'JOIN' && $data['nick'] == $this->curnick ) {
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Sending mode +b/+i on newly joined channel '.$data['channel']);
			$this->raw('MODE '.$data['channel'].' +b');
			$this->raw('MODE '.$data['channel'].' +I');
			
			$this->channels[$data['channel']] = array(
				'topic'     => '',
				'topicset'  => time(),
				'topicby'   => '',
				'usercount' => 0,
				'users'     => array(),
				'modes'     => '',
				'banlist'   => array(),
				'invites'   => array(),
			);
		}
		
		if ( $data['command'] == '332' ) {
			$this->channels[$data['params'][1]]['topic'] = $data['params'][2];
		}
		
		if ( $data['command'] == '333' ) {
			$this->channels[$data['params'][1]]['topicby'] = $data['params'][2];
			$this->channels[$data['params'][1]]['topicset'] = $data['params'][3];
		}
		
		if ( $data['command'] == '353' ) {
			$channel = $data['params'][2];
			
			foreach ( explode(' ', $data['params'][3]) AS $weirdnick ) {
				if ( isset($this->_symbol2mode[substr($weirdnick, 0, 1)]) ) {
					$this->channels[$channel]['nicks'][substr($weirdnick, 1)] = array(
						'nick'  => substr($weirdnick, 1),
						'modes' => $this->_symbol2mode[substr($weirdnick, 0, 1)],
					);
				} else {
					$this->channels[$channel]['nicks'][$weirdnick] = array(
						'nick'  => $weirdnick,
						'modes' => '',
					);
				}
				$this->channels[$channel]['usercount']++;
			}
		}
		
		if ( $data['command'] == '367' ) {
			list($nick, $identhost) = explode('!', $data['params'][2]);
			list($ident, $host) = explode('@', $identhost);
			
			$this->channels[$data['params'][1]]['banlist'][] = array(
				'nick'     => $nick,
				'ident'    => $ident,
				'host'     => $host,
				'fullhost' => $data['params'][2],
				'bannedby' => $data['params'][3],
				'bantime'  => $data['params'][4],
			);
		}
		
		if ( $data['command'] == '346' ) {
			list($nick, $identhost) = explode('!', $data['params'][2]);
			list($ident, $host) = explode('@', $identhost);
			
			$this->channels[$data['params'][1]]['invites'][] = array(
				'nick'     => $nick,
				'ident'    => $ident,
				'host'     => $host,
				'fullhost' => $data['params'][2],
				'setby'    => $data['params'][3],
				'settime'  => $data['params'][4],
			);
		}
		
		if ( $data['command'] == 'PRIVMSG' && $data['args'][0] == 'print' ) {
			print_r($this->channels);
		}
		
		// Send it out to the Plugin System
		Event::cast('irc.'.strtolower($data['command']), $data);
	}
	
	public function raw($payload) {
		Socket::get_instance()->write($this->_sid, $payload);
	}
	
	public function privmsg($channel, $message) {
		$this->raw('PRIVMSG '.$channel.' :'.$message);
	}
	
	public function notice($to, $message) {
		$this->raw('NOTICE '.$to.' :'.$message);
	}
	
	public function action($channel, $message) {
		$this->privmsg($channel, chr(1).$message.chr(1));
	}
	
	public function ctcp($to, $message) {
		$this->privmsg($to, chr(1).$message.chr(1));
	}
	
	public function join($channel, $key=null) {
		$this->raw('JOIN '.$channel.' '.$key);
	}
	
	public function part($channel, $message) {
		$this->raw('PART '.$channel.' :'.$message);
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
		
		$this->curnick = $this->_settings['nick'];
		$this->raw($out);
	}
	
	private function send_connected() {
		$out = array();
		if ( isset($this->_settings['nickserv']) && $this->_settings['oper']['use'] === true ) {
			if ( $this->_settings['nick'] != $this->curnick && $this->_settings['nickserv']['ghost'] === true) {
				$out[] = 'PRIVMSG '.$this->_settings['nickserv']['nick'].' :GHOST '.$this->_settings['nick'].' '.$this->_settings['nickserv']['password'];
				$this->raw($out);
				$out = array();
				sleep(2);
				
				$out[] = 'NICK '.$this->_settings['nick'];
			} else if ( $this->_settings['nick'] != $this->curnick && $this->_settings['nickserv']['ghost'] === false ) {
				// Do nothing
			} else {
				$out[] = 'PRIVMSG '.$this->_settings['nickserv']['nick'].' :IDENTIFY '.$this->_settings['nickserv']['password'];
			}
		}
		
		if ( isset($this->_settings['oper']) && $this->_settings['oper']['use'] === true ) {
			$out[] = 'OPER '.$this->_settings['oper']['username'].' '.$this->_settings['oper']['password'];
		}
		
		if ( isset($this->_settings['autojoin']) && !empty($this->_settings['autojoin']) ) {
			$out[] = 'JOIN '.implode(',', $this->_settings['autojoin']);
		}
		
		$this->raw($out);
	}
}
