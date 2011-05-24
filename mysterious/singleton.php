<?php
## ################################################## ##
##                   MysteriousCode                   ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousCode                       ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: singleton.php                      ##
##  [?] File version: 1.0-GOLD                        ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

abstract class Singleton {
	private static $_instances = array();
	
	protected function __construct() { }
	final private function __clone() { }
	
	public static function get_instance() {
		$class = get_called_class();
		
		if ( !isset(self::$_instances[$class]) ) {
			self::$_instances[$class] = new $class;
		}
		
		return self::$_instances[$class];
	}
}
