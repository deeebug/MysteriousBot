<?php
## ################################################## ##
##                  MysteriousCode                    ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousCode                       ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: autoloader.php                     ##
##  [?] File version: 1.0-GOLD                        ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Loader {
	public static function register() {
		spl_autoload_register(array(__NAMESPACE__ .'\Loader', 'load'));
		set_error_handler(array(__NAMESPACE__.'\Loader', 'handle_error'));
		set_exception_handler(array(__NAMESPACE__.'\Loader', 'handle_exception'));
	}
	
	public static function load($namespace) {
		$parts = explode('\\', $namespace);
		$handle = true;
		
		foreach ( explode('\\', __NAMESPACE__) AS $part ) {
			if ( strtolower($parts[0]) != strtolower($part) ) {
				$handle = false;
			} else {
				array_shift($parts);
			}
		}
		
		if ( $handle === true ) {
			$file = str_replace('\\', '/', strtolower($namespace));
			
			if ( file_exists(BASE_DIR.$file.'.php') === false ) {
				throw new AutoloaderError('Could not load '.$namespace.', because the file does not exist: '.BASE_DIR.$file.'.php');
			}
			
			include BASE_DIR.$file.'.php';
			
			if ( class_exists($namespace) ) {
				is_callable(array($namespace, '__initialize')) && call_user_func(array($namespace, '__initialize'));
				
				return true;
			} else {
				throw new AutoloaderError('Class '.$namespace.' was not found. The file that was loaded, but that does not contain the class is: '.BASE_DIR.$file.'.php');
				
				return false;
			}
		}
	}
	
	public static function handle_error($num, $str, $file, $line, $context) {
		switch ( $num ) {
			case E_USER_ERROR:
				$msg = "PHP Fatal error%s: %s.\nFile: %s\nLine: %s";
				$num = null;
			break;
			
			case E_USER_WARNING:
				return true;
			break;
			
			case E_USER_NOTICE:
				return true;
			break;
			
			default:
				$msg = "PHP Unknown error (".constrant($num)."): Error number %s with the message '%s'.\nFile: %s\nLine: %s";
			break;
		}
		
		$msg = nl2br(sprintf(
			$msg,
			$num,
			$str,
			$file,
			$line
		));
		
		defined('IS_CLI') && ($msg = strip_tags($msg));
		
		die($msg);
		
		return true;
	}
	
	public static function handle_exception($e) {
		$traceline = '#%s %s(%s): %s(%s)';
		$msg = "PHP Fatal error:  Uncaught exception '%s' with message '%s' in %s:%s\nStack trace:\n%s\n  thrown in %s on line %s";
		
		$trace = $e->getTrace();
		foreach ($trace as $key => $stackPoint) {
			$trace[$key]['args'] = array_map('gettype', $trace[$key]['args']);
		}
		
		$result = array();
		foreach ($trace as $key => $stackPoint) {
			$result[] = sprintf(
				$traceline,
				$key,
				$stackPoint['file'],
				$stackPoint['line'],
				$stackPoint['function'],
				implode(', ', $stackPoint['args'])
			);
		}
		
		$result[] = '#' . ++$key . ' {main}';
		
		$msg = nl2br(sprintf(
			$msg,
			get_class($e),
			$e->getMessage(),
			$e->getFile(),
			$e->getLine(),
			implode("\n", $result),
			$e->getFile(),
			$e->getLine()
		));
		
		defined('IS_CLI') && ($msg = strip_tags($msg));
		
		die($msg);
		
		return true;
	}
}

class AutoloaderError extends \Exception { }

Loader::register();
