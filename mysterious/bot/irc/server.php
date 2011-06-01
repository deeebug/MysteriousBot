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
##  [*] Last edit: 5/31/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\Event;
use Mysterious\Bot\Logger;
use Mysterious\Bot\Socket;

class Server {
	public $users    = array(); // The NETWORKS Clients
	public $channels = array(); // The NETWORKS Channels
	public $clients  = array(); // The Bot's Clients
	public $botchans = array(); // The Bot's Clients Channels currently joined.
	public $enforcer = false;   // The Bot's enforcer nick. Basically handles *, from a official-looking source Services[XXXXXXX]
	
	private $_connected = false;
	private $_lastping;
	private $_nick2uuid = array();
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
		
		// Do any internal magic
		$this->_internal_parse($data);
		
		// Got a quick debugging command.
		if ( $data['command'] == 'PRIVMSG' && $data['args'][0] == 'print' ) {
			print_r($this->users);
			print_r($this->channels);
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
		$this->privmsg($channel, chr(1).'ACTION '.$message.chr(1), $bot);
	}
	
	public function join($channel, $bot) {
		$bot = $this->_fixbotuuid($bot);
		
		if ( strpos($channel, ',') !== false )
			$channels = explode(',', $channel);
		else
			$channels = array($channel);
		
		foreach ( $channels AS $chan ) {
			if ( !isset($this->channels[$chan]) )
				$this->_create_channel(array('channel' => $chan));
			
			$this->channels[$chan]->usercount++;
			$this->channels[$chan]->nicks[$bot] = array(
				'nick'  => $bot,
				'modes' => '',
			);
			$this->botchans[$this->_nick2uuid[$bot]][] = $chan;
		}
		
		$this->raw(':'.$bot.' JOIN '.implode(',', $channels));
	}
	
	public function part($channel, $message, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' PART '.$channel.' '.$message);
		
		if ( ($pos = array_search($channel, $this->botchans[$this->_nick2uuid[$bot]])) !== false )
			unset($this->botchans[$this->_nick2uuid[$bot]][$pos]);
		
		if ( isset($this->channels[$channel]->nicks[$bot]) ) {
			$this->channels[$channel]->usercount--;
			unset($this->channels[$channel]->nicks[$bot]);
		}
	}
	
	public function quit($channel, $message, $bot) {
		$bot = $this->_fixbotuuid($bot);
		$this->raw(':'.$bot.' QUIT '.$channel.' '.$message);
		
		foreach ( $this->botchans[$this->_nick2uuid[$bot]] AS $channel ) {
			if ( isset($this->channels[$channel]) && isset($this->channels[$channel]->nicks[$bot]) ) {
				$this->channels[$channel]->usercount--;
				unset($this->channels[$channel]->nicks[$bot]);
			}
		}
		
		unset($this->clients[$bot], $this->botchans[$this->_nick2uuid[$bot]]);
	}
	
	public function topic($channel, $topic, $bot=null) {
		if ( empty($bot) )
			$topic = sprintf('TOPIC %s :%s', $channel, $topic);
		else
			$topic = sprintf(':%s TOPIC %s :%s', $bot, $channel, $topic);
		
		$this->raw($topic);
		$this->raw('TOPIC '.$channel); // Get topic info.
	}
	
	public function mode($channel, $modes, $affect, $bot) {
		$this->raw(':'.$bot.' MODE '.$channel.' '.$modes.' '.$affect);
		$this->_parse_mode(array('modes' => $modes.' '.$affect, 'channel' => $channel));
		// @@Note: Possibly send MODES chan, to update?
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
	
	public function introduce_client($uuid, $settings) {
		if ( $this->enforcer === false ) {
			// Spawning the enforcer!
			$this->enforcer = $nick = 'Services['.uniqid().']';
			
			$this->raw('NICK '.$nick.' 2 '.time().' services '.$this->_settings['linkname'].' '.$this->_settings['linkname'].' 0 :'.$this->_settings['linkdesc'].' Enforcer Bot');
		}
		
		foreach ( array('nick', 'ident', 'name') AS $setting ) {
			if ( !isset($settings[$setting]) ) {
				Logger::get_instance()->debug(__FILE__, __LINE__, __METHOD__.' - Could not spawn a new client - Missing setting '.$setting);
				return false;
			}
		}
		
		$this->clients[] = $settings['nick'];
		$this->_nick2uuid[$settings['nick']] = $uuid;
		
		if ( !isset($settings['host']) )
			$settings['host'] = $this->_settings['linkname'];
		
		if ( isset($settings['mode']) && !empty($settings['mode']) )
			$mode = $settings['mode'];
		else
			$mode = 'B';
			
		
		$out   = array();
		$out[] = ':'.$this->enforcer.' KILL '.$settings['nick'].' :Sorry, this nick is being used by the IRC Bot Services';
		$out[] = 'SQLINE '.$settings['nick'].' :Nick is being used for '.$this->_settings['linkname'].' services';
		$out[] = 'NICK '.$settings['nick'].' 2 '.time().' '.$settings['ident'].' '.$settings['host'].' '.$this->_settings['linkname'].' 0 :'.$settings['name'];
		$out[] = ':'.$settings['nick'].' MODE '.$settings['nick'].' +'.$mode;
		
		$this->raw($out);
		
		if ( isset($settings['autojoin']) )
			$this->join($settings['autojoin'], $settings['nick']);
		
		if ( isset($this->_settings['globalchan']) && !empty($this->_settings['globalchan']) )
			$this->join($this->_settings['globalchan'], $settings['nick']);
	}
	
	//=========================================================
	//=====================END "Semi-Public" Methods
	//=========================================================
	
	private function send_welcome() {
		$out = array();
		
		$out[] = 'PASS '.$this->_settings['linkpass'];
		$out[] = 'SERVER '.$this->_settings['linkname'].' 1 :'.$this->_settings['linkdesc'];
		$out[] = 'EOS';
		$this->raw($out);
		
		foreach ( $this->_settings['clients'] AS $botuuid => $settings ) 
			$this->introduce_client($botuuid, $settings);
	}
	
	private function _internal_parse($data) {
		switch ( $data['command'] ) {
			case 'MODE':
				$this->_parse_mode($data);
			break;
			
			case 'TOPIC':
				if ( !isset($this->channels[$data['channel']]) )
					$this->_create_channel($data);
				
				$channel =& $this->channels[$data['channel']];
				
				$channel->topic = $data['topic'];
				$channel->topicby = $data['by'];
				$channel->topicset = $data['set'];
				
				if ( $channel->created > $data['set'] )
					$channel->created = $data['set'];
				
				unset($channel);
			break;
			
			case 'NICKCONNECT':
				if ( !isset($this->users[$data['nick']]) )
					$this->_introduce_user($data);
			break;
			
			case 'NICK':
				if ( !isset($this->users[$data['nick']]) )
					$this->_introduce_user($data);
				
				$this->users[$data['nick']] = $this->users[$data['oldnick']];
				unset($this->users[$data['oldnick']]);
			break;
			
			case 'SETIDENT':
				if ( !isset($this->users[$data['nick']]) )
					$this->_introduce_user($data);
				
				$this->users[$data['nick']]->ident = $data['ident'];
			break;
			
			case 'SETHOST':
				if ( !isset($this->users[$data['nick']]) )
					$this->_introduce_user($data);
				
				$this->users[$data['nick']]->host = $data['host'];
			break;
			
			case 'SETNAME':
				if ( !isset($this->users[$data['nick']]) )
					$this->_introduce_user($data);
				
				$this->users[$data['nick']]->name = $data['name'];
			break;
			
			case 'JOIN':
				if ( strpos($data['channel'], ',') !== false )
					$channels = explode(',', $data['channel']);
				else
					$channels = array($data['channel']);
				
				foreach ( $channels AS $channel ) {
					if ( !isset($this->channels[$channel]) )
						$this->_create_channel($data);
					
					if ( !isset($this->users[$data['nick']]) )
						$this->_introduce_user($data);
					
					$user =& $this->users[$data['nick']];
					$channel =& $this->channels[$channel];
					
					$user->channels[] = $channel;
					$user->channelcount++;
					
					$channel->nicks[$data['nick']] = array(
						'nick'  => $data['nick'],
						'modes' => '',
					);
					$channel->usercount++;
					
					unset($user, $channel);
				}
			break;
			
			case 'PART':
				if ( !isset($this->channels[$data['channel']]) ) return; // Don't create channel.
				if ( !isset($this->users[$data['nick']]) )
					$this->_introduce_user($data);
				
				$user =& $this->users[$data['nick']];
				$channel =& $this->channels[$data['channel']];
				
				$pos_chan = array_search($data['channel'], $user->channels);
				
				unset($user->channels[$pos_chan], $channel->nicks[$data['nick']]);
				$user->channelcount--;
				$channel->usercount--;
				
				if ( $channel->usercount == 0 )
					unset($this->channels[$data['channel']]);
				
				unset($user, $channel);
			break;
			
			case 'QUIT':
				if ( !isset($this->users[$data['nick']]) ) return; // Who is he, then?
				
				foreach ( $this->users[$data['nick']]->channels AS $channel ) {
					if ( ($pos = array_search($data['nick'], $this->channels[$channel]->nicks)) !== false ) {
						$this->channels[$channel]->usercount--;
						unset($this->channels[$channel]->nicks[$pos]);
						
						if ( $this->channels[$channel]->usercount == 0 )
							unset($this->channels[$channel]);
					}
				}
				
				unset($this->users[$data['nick']]);
			break;
			
			case 'CTCP':
				$config = Config::get_instance();
				if ( $config->get('ctcp.'.strtolower($data['args'][0])) !== false )
					$reply = $config->get('ctcp.'.strtolower($data['args'][0]));
				else if ( $config->get('ctcp.__default') !== false )
					$reply = $config->get('ctcp.__default');
				
				if ( isset($reply) )
					$this->notice($data['nick'], $reply, $data['args'][0].': '.$data['_bot_to']);
			break;
		}
	}
	
	private function _parse_mode($data) {
		$chan = $data['channel'];
		$affects = $data['affects'];
		
		if ( substr($chan, 0, 1) == '#' )
			$modify =& $this->channels[$chan]->modes;
		else
			$modify =& $this->users[$chan]->usermodes;
		
		$mode_change = $data['modes'];
		$add = $to = null;
		
		for ( $i=0; strlen($mode_change) >= $i; $i++ ) {
			$char = substr($mode_change, $i, 1);
			
			if ( $char == '+' ) {
				$add = true;
				
				$to = null;
				!empty($affects) and $to = array_shift($affects);
			} else if ( $char == '-' ) {
				$add = false;
				
				$to = null;
				!empty($affects) and $to = array_shift($affects);
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
	
	private function _create_channel($data) {
		if ( !isset($data['channel']) ) return;
		
		$this->channels[$data['channel']] = Channel::new_instance();
		
		foreach ( array('modes', 'created', 'topic', 'by', 'set') AS $info ) {
			if ( !isset($data[$info]) ) continue;
			
			$this->channels[$data['channel']]->$info = $data[$info];
		}
	}
	
	private function _introduce_user($data) {
		if ( !isset($data['nick']) ) return;
		
		$this->users[$data['nick']] = User::new_instance();
		
		foreach ( array('ident', 'host', 'name', 'connected', 'ircop', 'away', 'servername') AS $info ) {
			if ( !isset($data[$info]) ) continue;
			
			$this->users[$data['nick']]->$info = $data[$info];
		}
	}
}
