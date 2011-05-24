<?php
## ################################################## ##
## 	                 MysteriousBot                    ##
## -------------------------------------------------- ##
## 	[*] Package: MysteriousBot                        ##
##                                                    ##
## 	[!] License: $LICENSE--------$                    ##
## 	[!] Registered to: $DOMAIN----------------------$ ##
## 	[!] Expires: $EXPIRES-$                           ##
##                                                    ##
## 	[?] File name: config.php                         ##
##                                                    ##
## 	[*] Author: debug <jtdroste@gmail.com>            ##
## 	[*] Created: 5/23/2011                            ##
## 	[*] Last edit: 5/23/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;

class Config extends Singleton {
	public static $last_error;
	private static $_config = array();
	private static $_flatconfig = array();
	
	public function import($file='default') {
		if ( file_exists(BASE_DIR.'config/'.$file.'.php') === false ) {
			self::$last_error = 'Config file for '.$file.' does not exist!: '.BASE_DIR.'config/'.$file.'.php';
			return false;
		}
		
		self::$_config[$file] = include BASE_DIR.'config/'.$file.'.php';
		
		foreach ( self::$_config AS $arr ) {
			self::$_flatconfig = array_merge(self::$_flatconfig, $arr);
		}
		
		return true;
	}
	
	public function get($key, $default=false) {
		$rtn = self::$_flatconfig;
		
		foreach ( explode('.', $key) AS $k ) {
			if ( !isset($rtn[$k]) ) {
				return $default;
			}
			
			$rtn = $rtn[$k];
		}
		
		return $rtn;
	}
}
