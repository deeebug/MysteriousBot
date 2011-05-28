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
##  [?] File name: plugin.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 5/28/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\IRC\BotManager;

abstract class Plugin {
	private $__bot;
	
	final public function __construct() { }
	
	final public function __setbot($bot) {
		$this->__bot = $bot;
	}
	
	final public function privmsg($channel, $message, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_instance()->get_bot($bot)->privmsg($channel, $message);
	}
	
	final public function notice($to, $message, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_instance()->get_bot($bot)->notice($to, $message);
	}
	
	final public function join($channel, $key=null, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_instance()->get_bot($bot)->join($channel, $key);
	}
	
	final public function part($channel, $message=null, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_instance()->get_bot($bot)->part($channel, $message);
	}
	
	final public function config($item, $default=false) {
		return Config::get_instance()->get('clients.'.$this->__bot.$item, $default);
	}
	
	final public function register_event() {
		$args = func_get_args();
		$plugin = get_class($this);
		
		switch ( count($args) ) {
			// I dunno what this is, but IMA RETURN FALSE IT
			case 0:
			case 1:
			default:
				Logger::get_instance()->warning(__FILE__, __LINE__, '[IRC Bot ('.$this->__bot.') - Plugin '.get_class($this).'] '.count($args).' was passed, but register_event only supports 2/3 args passed');
				return false;
			break;
			
			// It's a catch all
			case 2:
				$args[0] = strtolower($args[0]);
				
				if ( substr($args[0], 0, 4) != 'irc.' )
					$args[0] = 'irc.'.$args[0];
				
				Event::register($args[0], $args[1], $plugin, $this->__bot);
				Logger::get_instance()->debug(__FILE__, __LINE__, '[Plugin '.get_class($this).'] Registered new catch all for '.$args[0].' for bot '.$this->__bot);
			break;
			
			// Its a specific command, or regex.
			case 3:
				$args[0] = strtolower($args[0]);
				
				if ( substr($args[0], 0, 4) != 'irc.' )
					$args[0] = 'irc.'.$args[0];
				
				// It's not a regex, so make it one
				if ( substr($args[1], 0, 1) != '/' && substr($args[1], -1) != '/' )
					$args[1] = '/^'.$args[1].'/';
				
				Event::register_command($args[0], $args[1], $args[2], $plugin, $this->__bot);
				Logger::get_instance()->debug(__FILE__, __LINE__, '[Plugin '.get_class($this).'] Registered new command for '.$args[1].' ('.$args[0].') for bot '.$this->__bot);
			break;
		}
		
		return true;
	}
}
