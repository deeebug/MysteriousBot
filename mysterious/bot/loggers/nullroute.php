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
##  [?] File name: nullroute.php                      ##
##  [?] File description: Blank logger, does nada     ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\Loggers;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Nullroute extends AbstractLogger {
	
	public function debug($file, $line, $message) {
		return true;
	}
	
	public function info($file, $line, $message) {
		return true;
	}
	
	public function warning($file, $line, $message) {
		return true;
	}
	
	public function fatal($file, $line, $message) {
		return true;
	}
}
