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
##  [?] File name: event.php                          ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 5/27/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\IRC\BotManager;

class Event {
	const RESPOND_ALL = '__privateALL';
	private static $_botevents = array();
	private static $_commands  = array();
	private static $_events    = array();
	
	public static function cast($event, $data) {
		// Do the all respond event
		if ( isset(self::$_events[self::RESPOND_ALL][$event]) ) {
			foreach ( self::$_events[self::RESPOND_ALL][$event] AS $callback )
				call_user_func($callback, $data);
		}
		
		// Now we run the events for each per-bot
		if ( isset(self::$_events[$data['socketid']][$event]) ) {
			foreach ( self::$_events[$data['socketid']][$event] AS $info ) {
				if ( strpos($info[0], __NAMESPACE__) === false ) {
					$bot = BotManager::get_instance()->sid2bot($data['socketid']);
					$plugin = PluginManager::get_instance()->get_plugin($info[0], $bot);
					
					if ( $plugin === false ) {
						Logger::get_instance()->debug(__FILE__, __LINE__, '[Event] PluginManager returned false, plugin '.$info[0].' does not exist');
						break;
					}
				
					call_user_func(array($plugin, '__setbot'), $bot);
					call_user_func(array($plugin, $info[1]));
				} else {
					call_user_func($info, $data);
				}
			}
		}
		
		// Now for commands.
		if ( strtolower($event) == 'irc.privmsg' ) {
			if ( substr($data['channel'], 0, 1) == '#' )
				$key = 'irc.privmsg.channel';
			else
				$key = 'irc.privmsg.private';
			
			if ( isset(self::$_events[self::RESPOND_ALL][$key]) ) {
				foreach ( self::$_events[self::RESPOND_ALL][$key] AS $callback )
					call_user_func($callback, $data);
			}
			
			if ( isset(self::$_events[$data['socketid']][$key]) ) {
				foreach ( self::$_events[$data['socketid']][$key] AS $callback ) {
					call_user_func($callback, $data);
				}
			}
		}
	}
	
	public static function register($event, $callback, $plugin=null, $bot=null) {
		if ( !empty($bot) ) $bot = BotManager::get_instance()->bot2sid($bot);
		if ( empty($bot) )  $bot = self::RESPOND_ALL;
		
		if ( $bot == self::RESPOND_ALL )
			self::$_events[$bot][$event][] = $callback;
		else
			self::$_events[$bot][$event][] = array($plugin, $callback);
	}
	
	public static function register_command($event, $regex, $function, $plugin, $bot) {
		if ( empty($bot) ) throw new EventError('Register command is incorrectly called. Bot param is not passed');
		if ( !empty($bot) ) $bot = BotManager::get_instance()->bot2sid($bot);
		
		$plugin = explode('\\', $plugin);
		$plugin = array_pop($plugin);
		
		if ( !isset(self::$_events[$bot][$event]) || array_search(array(__NAMESPACE__.'\Event', 'handle_command'), self::$_events[$bot][$event]) === false )
			self::$_events[$bot][$event][] = array(__NAMESPACE__.'\Event', 'handle_command');
		
		self::$_commands[$bot][] = array(
			'regex'    => $regex,
			'plugin'   => $plugin,
			'function' => $function
		);
	}
	
	public static function handle_command($data) {
		foreach ( self::$_commands[$data['socketid']] AS $info ) {
			if ( preg_match($info['regex'], $data['message']) ) {
				$bot = BotManager::get_instance()->sid2bot($data['socketid']);
				$plugin = PluginManager::get_instance()->get_plugin($info['plugin'], $bot);
				
				if ( $plugin === false ) {
					Logger::get_instance()->debug(__FILE__, __LINE__, '[Event] PluginManager returned false, plugin '.$info['plugin'].' does not exist');
					break;
				}
				
				call_user_func(array($plugin, '__setbot'), $bot);
				call_user_func(array($plugin, $info['function']));
			}
		}
	}
}
