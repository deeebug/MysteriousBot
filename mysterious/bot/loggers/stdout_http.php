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
##  [?] File name: stdout_http.php                    ##
##  [?] File description: Talks to STDOUT + HTTP      ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/16/2011                            ##
##  [*] Last edit: 6/16/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\Loggers;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Config;
use Mysterious\Bot\HTTPServer;

class STDOUT_HTTP extends AbstractLogger {
	
	public function __construct() {
		$cfg = Config::get_instance();
		
		if ( $cfg->get('httpserver.enabled', false) === false ) {
			throw new STDOUT_HTTP_Error('The HTTP server needs to be enabled to use STDOUT_HTTP!');
		}
	}
	
	public function debug($file, $line, $message) {
		$msg = '[DEBUG] '.$file.'('.$line.'): '.$message."\n";
		
		echo $msg;
		HTTPServer::get_instance()->addlog($msg);
		
		return true;
	}
	
	public function info($file, $line, $message) {
		$msg = '[INFO] '.$file.'('.$line.'): '.$message."\n";
		
		echo $msg;
		HTTPServer::get_instance()->addlog($msg);
		
		return true;
	}
	
	public function warning($file, $line, $message) {
		$msg = '[WARNING] '.$file.'('.$line.'): '.$message."\n";
		
		echo $msg;
		HTTPServer::get_instance()->addlog($msg);
		
		return true;
	}
	
	public function fatal($file, $line, $message) {
		$msg = '[FATAL] '.$file.'('.$line.'): '.$message."\n\n\n".'Good bye!';
		
		HTTPServer::get_instance()->addlog($msg);
		
		die($msg);
		return false;
	}
}

class STDOUT_HTTP_Error extends \Exception { }
