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
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Timer {
	private static $_timers = array();
	
	public static function tik() {
		foreach ( self::$_timers AS $id => $timer ) {
			if ( time() >= $timer['when'] ) {
				call_user_func_array($timer['callback'], $timer['args']);
				unset(self::$_timers[$id]);
			}
		}
	}
	
	public static function register($when, $callback, $args = array(), $once=false) {
		$id = md5(uniqid('TIMER'));
		
		self::$_timers[$id] = array(
			'when' => time()+$when,
			'callback' => $callback,
			'args' => $args,
			'once' => $once
		);
		
		return $id;
	}
	
	public static function delete($id) {
		unset(self::$_timers[$id]);
		
		return true;
	}
}
