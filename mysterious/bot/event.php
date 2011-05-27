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
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Event {
	private static $_events = array();
	
	public static function cast($event, $data) {
		if ( !isset(self::$_events[$event]) ) return;
		
		foreach ( self::$_events[$event] AS $callback )
			call_user_func($callback);
	}
	
	public static function register($event, $callback) {
		self::$_events[$event][] = $callback;
	}
}
