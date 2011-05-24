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
## 	[?] File name: AbstractLogger.php                 ##
##                                                    ##
## 	[*] Author: debug <jtdroste@gmail.com>            ##
## 	[*] Created: 5/23/2011                            ##
## 	[*] Last edit: 5/23/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\Loggers;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

abstract class AbstractLogger {
	
	abstract public function debug($file, $line, $message);
	
	abstract public function info($file, $line, $message);
	
	abstract public function warning($file, $line, $message);
	
	abstract public function fatal($file, $line, $message);
}
