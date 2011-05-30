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
##  [?] File name: logger.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;

class Logger extends Singleton {
	private static $_logger;
	private static $_log_level = 0;
	
	public static function __initialize() {
		parent::get_instance();
		
		$cfg = Config::get_instance();
		
		$logger = 'STDOUT';
		if ( $cfg->get('logger.default') !== false ) {
			$logger = $cfg->get('logger.default');
		} else {
			$logger = 'STDOUT';
		}
		$logger = __NAMESPACE__.'\Loggers\\'.ucfirst($logger);
		
		self::$_logger = new $logger;
	}
	
	public function load_logger($logger) {
		$logger = __NAMESPACE__.'\Loggers\\'.ucfirst($logger);
		
		self::$_logger = new $logger;
	}
	
	public function __call($name, $args) {
		if ( empty(self::$_logger) ) {
			$this->__initialize();
		}
		
		if ( $name == 'debug' && Config::get_instance()->get('debug') !== true )
			return;
		
		if ( is_callable(array(self::$_logger, $name)) ) {
			if ( count($args) != 3 ) {
				return false;
			} else {
				$args[0] = str_replace(BASE_DIR, './', $args[0]);
				return call_user_func_array(array(self::$_logger, $name), $args);
			}
		} else {
			return false;
		}
	}
}
