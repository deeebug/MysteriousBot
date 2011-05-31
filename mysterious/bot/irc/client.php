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
##  [*] Last edit: 5/30/2011                          ##
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
			
			
			$this->nicks[$this->_settings['nick']] = User::new_instance();
			$this->nicks[$this->_settings['nick']]->nick = $data['nick'];
			
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
		
		// Finished MOTD/No MOTD, send autojoin/onconnect stuff
		if ( $data['command'] == '422' || $data['command'] == '376' ) {
			$this->send_connected();
		}
		
		// Channel join. Gotta update tracked nicks, channels
		if ( $data['command'] == 'JOIN' && $data['nick'] == $this->curnick ) {
			Logger::get_instance()->debug(__FILE__, __LINE__, '[IRC] Sending mode +b/+i on newly joined channel '.$data['channel']);
			
			$this->raw(array(
			'WHO '.$data['channel'],
			'MODE '.$data['channel'],
			'MODE '.$data['channel'].' +b',
			'MODE '.$data['channel'].' +I'
			));
			
			$this->channels[$data['channel']] = Channel::new_instance();
			$this->channels[$data['channel']]->created = time();
			$this->channels[$data['channel']]->topic_set = time();
		}
		
		if ( $data['command'] == 'JOIN' ) {
			if ( !isset($this->nicks[$data['nick']]) ) {
				$this->nicks[$data['nick']] = User::new_instance();
				$this->nicks[$data['nick']]->nick = $data['nick'];
				$this->nicks[$data['nick']]->ident = $data['ident'];
				$this->nicks[$data['nick']]->host = $data['host'];
				$this->nicks[$data['nick']]->fullhost = $data['fullhost'];
			}
			
			$this->nicks[$data['nick']]->channels[] = $data['channel'];
			$this->nicks[$data['nick']]->channelcount++;
		}
		
		// Got the topic!
		if ( $data['command'] == '332' ) {
			$this->channels[$data['params'][1]]->topic = $data['params'][2];
		}
		
		// Who made the topic/when?
		if ( $data['command'] == '333' ) {
			$this->channels[$data['params'][1]]->topicby = $data['params'][2];
			$this->channels[$data['params'][1]]->topicset = $data['params'][3];
		}
		
		// NAMES reply.
		if ( $data['command'] == '353' ) {
			$channel = $data['params'][2];
			
			foreach ( explode(' ', $data['params'][3]) AS $weirdnick ) {				
				if ( isset($this->_symbol2mode[substr($weirdnick, 0, 1)]) ) {
					if ( !isset($this->nicks[substr($weirdnick, 1)]) ) {
						$this->nicks[substr($weirdnick, 1)] = User::new_instance();
						$this->nicks[substr($weirdnick, 1)]->nick = substr($weirdnick, 1);
					}
				
					$this->channels[$channel]->nicks[substr($weirdnick, 1)] = array(
						'nick'  => substr($weirdnick, 1),
						'modes' => $this->_symbol2mode[substr($weirdnick, 0, 1)],
					);
					
					if ( array_search($channel, $this->nicks[substr($weirdnick, 1)]->channels) === false ) {
						$this->nicks[substr($weirdnick, 1)]->channels[] = $channel;
						$this->nicks[substr($weirdnick, 1)]->channelcount++;
					}
				} else {
					if ( !isset($this->nicks[$weirdnick]) ) {
						$this->nicks[$weirdnick] = User::new_instance();
						$this->nicks[$weirdnick]->nick = $weirdnick;
					}
					
					$this->channels[$channel]->nicks[$weirdnick] = array(
						'nick'  => $weirdnick,
						'modes' => '',
					);
					
					if ( array_search($channel, $this->nicks[$weirdnick]->channels) === false ) {
						$this->nicks[$weirdnick]->channels[] = $channel;
						$this->nicks[$weirdnick]->channelcount++;
					}
				}
				
				$this->channels[$channel]->usercount++;
			}
		}
		
		// Channel banlist
		if ( $data['command'] == '367' ) {
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
		}
		
		// Channel invite list
		if ( $data['command'] == '346' ) {
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
		}
		
		// WHO command
		if ( $data['command'] == '352' ) {
			if ( !isset($this->nicks[$data['params'][5]]) ) {
				$this->nicks[$data['params'][5]] = User::new_instance();
				$this->nicks[$data['params'][5]]->nick = $data['params'][5];
			}
			
			if ( !isset($this->channels[$data['params'][1]]) ) {
				$this->channels[$data['params'][1]] = Channel::new_instance();
			}
			
			$nick =& $this->nicks[$data['params'][5]];
			$channel =& $this->channels[$data['params'][1]];
			
			$nick->nick = $data['params'][5];
			$nick->ident = $data['params'][2];
			$nick->name = substr($data['params'][7], 1);
			$nick->host = $data['params'][3];
			$nick->fullhost = $nick->nick.'!'.$nick->ident.'@'.$nick->host;
			$nick->server = $data['params'][4];
			
			$nick->away = false;
			$nick->ircop = false;
			
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
		}
		
		// Got the channel modes
		if ( $data['command'] == '324' ) {
			if ( !isset($this->channels[$data['params'][1]]) ) {
				$this->channels[$data['params'][1]] = Channel::new_instance();
				$this->channels[$data['params'][1]]->created = time();
				$this->channels[$data['params'][1]]->topic_set = time();
			}
			
			if ( strlen(substr($data['params'][2], 1)) != 0 )
				$this->channels[$data['params'][1]]->modes = substr($data['params'][2], 1);
		}
		
		// Handling the CTCP's
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
		
		// Handle the MODE for user/channel/channel permission
		if ( $data['command'] == 'MODE' ) {
			$params = $data['params'];
			$chan = $affect = array_shift($params);
			
			if ( substr($affect, 0, 1) == '#' )
				$modify =& $this->channels[$affect]->modes;
			else
				$modify =& $this->nicks[$affect]->usermodes;
			
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
