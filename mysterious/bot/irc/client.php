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
##  [*] Last edit: 6/1/2011                           ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\Event;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;

class Client {
	public $channels = array();
	public $nicks = array();
	
	private $_connected = false;
	private $_curnick;
	private $_lastping;
	private $_settings;
	private $_sid;
	private $_symbol2mode = array(
		'~' => 'q',
		'&' => 'a',
		'@' => 'o',
		'%' => 'h',
		'+' => 'v',
	);
	
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
			
			$this->users[$this->_settings['nick']] = User::new_instance();
			$this->users[$this->_settings['nick']]->nick = $this->_settings['nick'];
			$this->users[$this->_settings['nick']]->ident = $this->_settings['ident'];
			$this->users[$this->_settings['nick']]->name = $this->_settings['name'];
			
			return;
		}
		
		// Do the ping. We won't plugins handle it, we want the ping out ASAP!
		if ( substr($data['raw'], 0, 4) == 'PING' ) {
			$this->_lastping = time();
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Ping? Pong!');
			$this->raw('PONG '.substr($data['raw'], 5));
			return;
		}
		
		// The internal magic
		$data = $this->_internal_parse($data);
		
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
	
	public function quit($message) {
		$this->raw('QUIT :'.$message);
	}
	
	public function topic($channel, $topic) {
		$this->raw(sprintf('TOPIC %s :%s', $channel, $topic));
		$this->raw('TOPIC '.$channel); // Get topic info.
	}
	
	public function mode($channel, $modes, $affects=null) {
		$this->raw('MODE '.$channel.' '.$modes.' '.$affects);
	}
	
	//=========================================================
	//=====================END "Public" Methods
	//=========================================================
	
	private function _internal_parse($data) {
		if ( substr($data['raw'], 0, 4) == 'PING' ) return $data;
		
		switch ( $data['command'] ) {
			case 001: // Connected
				Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Updating curnick from '.$this->curnick.' to '.$data['params'][0]);
				$this->curnick = $data['params'][0];
			break;
			
			case 433: // Nick in use
				$numbers = rand(1,50);
				Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Nick '.$this->curnick.' is in use, changing nick to '.$this->curnick.$numbers);
				$this->curnick .= $numbers;
				$this->raw('NICK '.$this->curnick);
			break;
			
			case 422: // No MOTD
			case 376: // End of MOTD
				$this->send_connected();
			break;
			
			case 'JOIN':
				if ( $data['nick'] == $this->curnick ) {
					Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Joined channel '.$data['channel'].' - Getting information');
					
					$this->raw(array(
						'WHO '.$data['channel'],
						'MODE '.$data['channel'],
						'MODE '.$data['channel'].' +b',
						'MODE '.$data['channel'].' +I'
					));
					
					$this->channels[$data['channel']] = Channel::new_instance();
					$this->channels[$data['channel']]->created = time();
					$this->channels[$data['channel']]->topic_set = time();
				} else {
					if ( !isset($this->users[$data['nick']]) )
						$this->_introduce_user($data);
			
					$this->users[$data['nick']]->channels[] = $data['channel'];
					$this->users[$data['nick']]->channelcount++;
				}
			break;
			
			case 329: // Channel created
				$this->channels[$data['params'][1]]->created = $data['params'][2];
			break;
			
			case 332: // Topic
				$this->channels[$data['params'][1]]->topic = $data['params'][2];
			break;
			
			case 333: // Topic information
				$this->channels[$data['params'][1]]->topicby = $data['params'][2];
				$this->channels[$data['params'][1]]->topicset = $data['params'][3];
			break;
			
			case 353: // NAMES
				$channel = $data['params'][2];
			
				foreach ( explode(' ', $data['params'][3]) AS $weirdnick ) {				
					if ( isset($this->_symbol2mode[substr($weirdnick, 0, 1)]) ) {
						if ( !isset($this->users[substr($weirdnick, 1)]) )
							$this->_introduce_user(array('nick' => substr($weirdnick, 1)));
					
						$this->channels[$channel]->nicks[substr($weirdnick, 1)] = array(
							'nick'  => substr($weirdnick, 1),
							'modes' => $this->_symbol2mode[substr($weirdnick, 0, 1)],
						);
						
						if ( array_search($channel, $this->users[substr($weirdnick, 1)]->channels) === false ) {
							$this->users[substr($weirdnick, 1)]->channels[] = $channel;
							$this->users[substr($weirdnick, 1)]->channelcount++;
						}
					} else {
						if ( !isset($this->users[$weirdnick]) )
							$this->_introduce_user(array('nick' => $weirdnick));
						
						$this->channels[$channel]->nicks[$weirdnick] = array(
							'nick'  => $weirdnick,
							'modes' => '',
						);
						
						if ( array_search($channel, $this->users[$weirdnick]->channels) === false ) {
							$this->users[$weirdnick]->channels[] = $channel;
							$this->users[$weirdnick]->channelcount++;
						}
					}
				
					$this->channels[$channel]->usercount++;
				}
			break;
			
			case 367: // Banlist
				list($nick, $identhost) = explode('!', $data['params'][2]);
				list($ident, $host) = explode('@', $identhost);
				
				$this->channels[$data['params'][1]]->banlist[] = array(
					'nick'     => $nick,
					'ident'    => $ident,
					'host'     => $host,
					'fullhost' => $data['params'][2],
					'bannedby' => $data['params'][3],
					'bantime'  => $data['params'][4],
				);
			break;
			
			case 346: // Invite list
				list($nick, $identhost) = explode('!', $data['params'][2]);
				list($ident, $host) = explode('@', $identhost);
				
				$this->channels[$data['params'][1]]->invites[] = array(
					'nick'     => $nick,
					'ident'    => $ident,
					'host'     => $host,
					'fullhost' => $data['params'][2],
					'setby'    => $data['params'][3],
					'settime'  => $data['params'][4],
				);
			break;
			
			case 352: // WHO
				$data['channel'] = $data['params'][1];
				$data['ident']   = $data['params'][2];
				$data['host']    = $data['params'][3];
				$data['server']  = $data['params'][4];
				$data['nick']    = $data['params'][5];
				$data['name']    = substr($data['params'][7], 1);
				
				if ( !isset($this->users[$data['params'][5]]) )
					$this->_introduce_user($data);
				
				if ( !isset($this->channels[$data['params'][1]]) )
					$this->_create_channel($data['params'][1]);
				
				$nick =& $this->users[$data['nick']];
				$channel =& $this->channels[$data['channel']];
				
				for ( $i=0,$s=strlen($data['params'][6]); $i<$s; $i++ ) {
					switch ( substr($data['params'][6], $i, 1) ) {
						case 'H':
							$nick->away = false;
						break;
						
						case 'G':
							$nick->away = true;
						break;
						
						case '@':
							if ( strpos($channel->nicks[$nick->nick]['modes'], 'o') === false )
								$channel->nicks[$nick->nick]['modes'] .= 'o';
						break;
						
						case '+':
							if ( strpos($channel->nicks[$nick->nick]['modes'], 'v') === false )
								$channel->nicks[$nick->nick]['modes'] .= 'v';
						break;
						
						case '*':
							$nick->ircop = true;
						break;
					}
				}
				
				// Here, we try to fill empty user information.
				foreach ( array('ident', 'host', 'name', 'server') AS $info ) {
					if ( empty($nick->$info) || $nick->$info != $data[$info] )
						$nick->$info = $data[$info];
				}
			break;
			
			case 324: // Channel modes
				if ( !isset($this->channels[$data['params'][1]]) ) 
					$this->_create_channel(array('channel' => $data['params'][1]));
			
				if ( strlen(substr($data['params'][2], 1)) != 0 )
					$this->channels[$data['params'][1]]->modes = substr($data['params'][2], 1);
			break;
			
			case 'CTCP':
				$config = Config::get_instance();
				
				if ( $config->get('ctcp.'.strtolower($data['args'][0])) !== false )
					$reply = $config->get('ctcp.'.strtolower($data['args'][0]));
				else if ( $config->get('ctcp.__default') !== false )
					$reply = $config->get('ctcp.__default');
			
				if ( isset($reply) )
					$this->notice($data['nick'], $reply, $data['args'][0].': '.$data['channel']);
			break;
			
			case 'MODE':
				$this->_parse_mode($data);
			break;
		}
		
		return $data;
	}
	
	private function _parse_mode($data) {
		$params = $data['params'];
		$chan = $affect = array_shift($params);
		
		if ( substr($affect, 0, 1) == '#' )
			$modify =& $this->channels[$affect]->modes;
		else
			$modify =& $this->users[$affect]->usermodes;
		
		$mode_change = array_shift($params);
		$add = $to = null;
		
		for ( $i=0; strlen($mode_change) >= $i; $i++ ) {
			$char = substr($mode_change, $i, 1);
			
			if ( $char == '+' ) {
				$add = true;
				
				$to = null;
				!empty($params) and $to = array_shift($params);
			} else if ( $char == '-' ) {
				$add = false;
				
				$to = null;
				!empty($params) and $to = array_shift($params);
			} else {
				if ( empty($char) ) continue;
				
				// User permission on channel mode
				if ( !empty($to) ) {
					if ( in_array($char, array_values($this->_symbol2mode)) )
						$modify =& $this->channels[$chan]->nicks[$to]['modes'];
					else
						$modify =& $this->channels[$chan]->modes;
				}
				
				if ( $char == 'b' ) {
					if ( $add === true ) {
						list($nick, $identhost) = explode('!', $to);
						list($ident, $host) = explode('@', $identhost);
						$this->channels[$chan]->banlist[] = array(
							'nick'     => $nick,
							'ident'    => $ident,
							'host'     => $host,
							'fullhost' => $to,
							'bannedby' => $data['nick'],
							'bantime'  => time(),
						);
					} else {
						foreach ( $this->channels[$chan]->banlist AS $key => $data ) {
							if ( $data['fullhost'] == $to )
								unset($this->channels[$chan]->banlist[$key]);
						}
					}
				} else if ( $char == 'I' ) {
					if ( $add === true ) {
						list($nick, $identhost) = explode('!', $to);
						list($ident, $host) = explode('@', $identhost);
						$this->channels[$chan]->invites[] = array(
							'nick'     => $nick,
							'ident'    => $ident,
							'host'     => $host,
							'fullhost' => $to,
							'setby'    => $data['nick'],
							'settime'  => time(),
						);
					} else {
						foreach ( $this->channels[$chan]->invites AS $key => $data ) {
							if ( $data['fullhost'] == $to )
								unset($this->channels[$chan]->invites[$key]);
						}
					}
				} else if ( $add === true ) {
					if ( strpos($modify, $char) === false )
						$modify .= $char;
				} else {
					$modify = str_replace($char, '', $modify);
				}	
			}
		}
	}
	
	private function _introduce_user($data) {
		if ( !isset($data['nick']) ) return;
		
		$this->users[$data['nick']] = User::new_instance();
		
		foreach ( array('nick', 'ident', 'host', 'name', 'connected', 'ircop', 'away', 'servername') AS $info ) {
			if ( !isset($data[$info]) ) continue;
			
			$this->users[$data['nick']]->$info = $data[$info];
			Logger::get_instance()->debug(__FILE__, __LINE__, __METHOD__ .'set '.$info.' = '.$data[$info]);
		}
	}
	
	private function _create_channel($data) {
		if ( !isset($data['channel']) ) return;
		
		$this->channels[$data['channel']] = Channel::new_instance();
		
		foreach ( array('modes', 'created', 'topic', 'by', 'set') AS $info ) {
			if ( !isset($data[$info]) ) continue;
			
			$this->channels[$data['channel']]->$info = $data[$info];
		}
	}
	
	//=========================================================
	//=====================END "SemiPrivate" Methods
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
