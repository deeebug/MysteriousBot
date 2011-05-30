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
##  [?] File name: message.php                        ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/27/2011                            ##
##  [*] Last edit: 5/27/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Message {
	private static $_data = array();
	
	public static function __newdata($data) {
		self::$_data = $data;
	}
	
	public static function __callStatic($name, $args) {
		return isset(self::$_data[$name]) ? self::$_data[$name] : null;
	}
}
