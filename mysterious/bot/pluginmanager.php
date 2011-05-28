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
##  [?] File name: pluginmanager.php                  ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/27/2011                            ##
##  [*] Last edit: 5/27/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\IRC\BotManager;

class PluginManager extends Singleton {
	private $_plugins = array();
	
	public function do_autoload() {
		$autoload = Config::get_instance()->get('autoload');
		
		if ( $autoload === false || empty($autoload) ) return;
		
		foreach ( $autoload AS $plugin )
			$this->load_plugin($plugin);
	}
	
	public function load_plugin($plugin) {
		if ( file_exists(BASE_DIR.'plugins/'.strtolower($plugin).'.php') === false ) {
			// Can't load it, just log it as a warning.
			Logger::get_instance()->warning(__FILE__, __LINE__, '[Plugin Autoloader] Plugin '.$plugin.' does not exist: '.BASE_DIR.'plugins/'.strtolower($plugin).'.php');
			
			return false;
		}
		
		include BASE_DIR.'plugins/'.strtolower($plugin).'.php';
		$class = 'Plugins\\'.$plugin;
		$this->_plugins[strtolower($plugin)] = new $class;
		
		// Now for EVERY bot, we have to run the __initialize command
		foreach ( Config::get_instance()->get('clients') AS $uuid => $settings ) {
			if ( $settings['enabled'] === false ) continue;
			
			switch ( strtolower($settings['type']) ) {
				case 'client':
					if ( isset($settings['plugins']) && !empty($settings['plugins']) ) {
						if ( array_search(strtolower($plugin), array_map('strtolower', $settings['plugins'])) !== false ) {
							// First tell the plugin that we're using XXXX bot
							$this->_plugins[strtolower($plugin)]->__setbot($uuid);
							
							// Okay, run __initialize
							$this->_plugins[strtolower($plugin)]->__initialize();
						}
					}
				break;
				
				case 'server':
					foreach ( $settings['clients'] AS $clientuuid => $config ) {
						if ( isset($config['plugins']) && !empty($config['plugins']) ) {
							if ( array_search(strtolower($plugin), array_map('strtolower', $config['plugins'])) !== false ) {
								// First tell the plugin that we're using XXXX bot
								$this->_plugins[strtolower($plugin)]->__setbot('S_'.$uuid.'-'.$clientuuid);
								
								// Okay, run __initialize
								$this->_plugins[strtolower($plugin)]->__initialize();
							}
						}
					}
				break;
			}
		}
	}
	
	public function get_plugin($plugin, $bot) {
		if ( !isset($this->_plugins[strtolower($plugin)]) ) return false;
		
		$this->_plugins[strtolower($plugin)]->__setbot($bot);
		return $this->_plugins[strtolower($plugin)];
	}
}
