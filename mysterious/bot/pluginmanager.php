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
##  [*] Last edit: 6/17/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\IRC\BotManager;

class PluginManager extends Singleton {
	public $_plugins = array();
	
	public function do_autoload() {
		$autoload = Config::get_instance()->get('autoload');
		
		if ( $autoload === false || empty($autoload) ) return;
		
		foreach ( $autoload AS $plugin )
			$this->load($plugin);
	}
	
	public function load($plugin) {
		if ( file_exists(BASE_DIR.'plugins/'.strtolower($plugin).'.php') === false ) {
			// Can't load it, just log it as a warning.
			Logger::get_instance()->warning(__FILE__, __LINE__, '[Plugin Loader] Plugin '.$plugin.' does not exist: '.BASE_DIR.'plugins/'.strtolower($plugin).'.php');
			
			return false;
		}
		
		if ( !isset($this->_plugins[strtolower($plugin)]) ) {
			include BASE_DIR.'plugins/'.strtolower($plugin).'.php';
			$class = 'Plugins\\'.$plugin;
			$this->_plugins[strtolower($plugin)] = new $class;
		}
		
		// Now for EVERY bot, we have to run the __initialize command
		foreach ( Config::get_instance()->get('clients') AS $uuid => $settings ) {
			if ( $settings['enabled'] === false ) continue;
			if ( !isset($settings['plugins']) ) continue;
			if ( !in_array($plugin, $settings['plugins']) ) continue;
			
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
	
	public function load_plugin($plugin, $botuuid=null, $load_server_clients=false, $server_clients_load=array()) {
		if ( empty($botuuid) )
			$affected = array_keys(BotManager::get_instance()->_bots);
		else
			$affected = array($botuuid);
		
		if ( !isset($this->_plugins[strtolower($plugin)]) ) {
			include BASE_DIR.'plugins/'.strtolower($plugin).'.php';
			$class = 'Plugins\\'.$plugin;
			$this->_plugins[strtolower($plugin)] = new $class;
		}
		
		foreach ( $affected AS $uuid ) {
			if ( !isset(BotManager::get_instance()->_bots[$uuid]) ) continue;
			
			if ( BotManager::get_instance()->_bots[$uuid] instanceof Client ) {
				// First tell the plugin that we're using XXXX bot
				$this->_plugins[strtolower($plugin)]->__setbot($uuid);
				
				// Okay, run __initialize
				$this->_plugins[strtolower($plugin)]->__initialize();
			} else {
				if ( $load_server_clients == false ) continue;
				
				foreach ( Config::get_instance()->get('clients.'.$uuid.'.clients', array()) AS $clientuuid => $config ) {
					if ( !empty($server_clients_load) && array_search($clientuuid, $server_clients_load) === false ) continue;
					
					// First tell the plugin that we're using XXXX bot
					$this->_plugins[strtolower($plugin)]->__setbot('S_'.$uuid.'-'.$clientuuid);
					
					// Okay, run __initialize
					$this->_plugins[strtolower($plugin)]->__initialize();
				}
			}
		}
		
		return true;
	}
	
	public function get_plugin($plugin, $bot) {
		if ( !isset($this->_plugins[strtolower($plugin)]) ) return false;
		
		$this->_plugins[strtolower($plugin)]->__setbot($bot);
		return $this->_plugins[strtolower($plugin)];
	}
}
