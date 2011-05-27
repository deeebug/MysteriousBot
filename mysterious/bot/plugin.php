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
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\IRC\BotManager;

abstract class Plugin {
	private $__bot;
	
	abstract public function __initialize();
	
	final public function __setbot($bot) {
		$this->__bot = $bot;
	}
	
	final private function privmsg($channel, $message, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_bot($bot)->privmsg($channel, $message);
	}
	
	final private function notice($to, $message, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_bot($bot)->notice($to, $message);
	}
	
	final private function join($channel, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_bot($bot)->join($channel);
	}
	
	final private function part($channel, $message=null, $bot=null) {
		if ( empty($bot) ) $bot = $this->__bot;
		
		BotManager::get_bot($bot)->part($channel, $message);
	}
}
