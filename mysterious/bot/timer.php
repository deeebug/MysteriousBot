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
##  [?] File name: timer.php                          ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 6/16/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Timer {
	private static $_timers = array();
	
	public static function tik() {
		foreach ( self::$_timers AS $id => $timer ) {
			if ( time() >= $timer['last_called']+$timer['when'] ) {
				Logger::get_instance()->debug(__FILE__, __LINE__, '[Timer] Calling timer id '.$id);
				call_user_func_array($timer['callback'], $timer['args']);
				if ( $timer['once'] === true )
					unset(self::$_timers[$id]);
				else
					self::$_timers[$id]['last_called'] = time();
			}
		}
	}
	
	public static function register($when, $callback, $args = array(), $once=false) {
		$id = uniqid('TIMER');
		
		self::$_timers[$id] = array(
			'when' => $when,
			'last_called' => time(),
			'callback' => $callback,
			'args' => $args,
			'once' => $once
		);
		
		Logger::get_instance()->info(__FILE__, __LINE__, '[Timer] New timer '.$id.' registered for +'.$when.' seconds');
		return $id;
	}
	
	public static function register_plugin($when, $plugin, $function, $bot, $args, $once) {
		$id = uniqid('TIMER');
		
		self::$_timers[$id] = array(
			'when' => $when,
			'last_called' => time(),
			'callback' => array(__NAMESPACE__.'\Timer', 'call_plugin'),
			'args' => array($bot, $plugin, $function, $args),
			'once' => $once
		);
		
		Logger::get_instance()->debug(__FILE__, __LINE__, '[Timer] New timer '.$id.' registered for +'.$when.' seconds');
		return $id;
	}
	
	public static function call_plugin($bot, $plugin, $function, $args) {
		$plugin = PluginManager::get_instance()->get_plugin($plugin, $bot);
		
		if ( $plugin === false )
			return;
		
		call_user_func_array(array($plugin, $function), $args);
	}
	
	public static function delete($id) {
		unset(self::$_timers[$id]);
		
		Logger::get_instance()->debug(__FILE__, __LINE__, '[Timer] Removed timer id '.$id);
		return true;
	}
}
