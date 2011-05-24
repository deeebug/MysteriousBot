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
##  [?] File name: stdout.php                         ##
##  [?] File description: Blank logger, does nada     ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\Loggers;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class STDOUT extends AbstractLogger {
	
	public function debug($file, $line, $message) {
		echo '[DEBUG] '.$file.'/'.$line.': '.$message."\n";
		return true;
	}
	
	public function info($file, $line, $message) {
		echo '[INFO] '.$file.'/'.$line.': '.$message."\n";
		return true;
	}
	
	public function warning($file, $line, $message) {
		echo '[WARNING] '.$file.'/'.$line.': '.$message."\n";
		return true;
	}
	
	public function fatal($file, $line, $message) {
		die('[FATAL] '.$file.'('.$line.'): '.$message."\n\n\n".'Good bye!');
		
		return false;
	}
}
