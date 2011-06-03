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
##  [?] File name: xmpp.php                           ##
##  [?] File description: A wrapper class, main       ##
## classes are inside the xmpp/ directory.            ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/1/2011                             ##
##  [*] Last edit: 6/1/2011                           ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\XMPP\Stream;
use Mysterious\Bot\XMPP\Response;

class XMPP extends Singleton {
	public $admins = array();
	public $settings = array();
	
	private $_commands = array();
	private $_plugins = array();
	private $_sid;
	private $_stream;
	
	public function setup() {
		$this->settings = Config::get_instance()->get('xmpp');
		$this->admins = $this->settings['admins'];
		
		foreach ( $this->settings['plugins'] AS $plugin ) {
			if ( !file_exists($this->settings['plugin_dir'].strtolower($plugin).'.php') )
				throw new XMPPError('Failed to load XMPP Plugin - File does not exist. Location: '.$this->settings['plugin_dir'].strtolower($plugin).'.php - Aborting startup');
			
			include $this->settings['plugin_dir'].strtolower($plugin).'.php';
			
			$class = 'XMPP_Plugins\\'.$plugin;
			$this->_plugins[strtolower($plugin)] = new $class;
			
			is_callable(array($this->_plugins[strtolower($plugin)], '__initialize')) and call_user_func(array($this->_plugins[strtolower($plugin)], '__initialize'));
		}
	}
	
	public function set_sid($socketid) {
		$this->_sid = $socketid;
		
		$this->_stream = Stream::get_instance();
		$this->_stream->setup($socketid);
	}
	
	public function register_command($regex, $function, $plugin) {
		$this->_commands[] = array(
			'regex'    => $regex,
			'function' => $function,
			'plugin'   => $plugin,
			'callback' => array($this->_plugins[strtolower($plugin)], $function)
		);
	}
	
	public function remove_command($regex) {
		foreach ( $this->_commands AS $key => $data ) {
			if ( $data['regex'] == $regex )
				unset($this->_commands[$key]);
		}
	}
	
	public function handle_read($socketid, $raw) {
		if ( $socketid != $this->_sid ) return; // Why are we handling this socket?!
		
		// Send that stuff to the Stream!
		$data = $this->_stream->handle($raw);
		
		// Is it a message? Send to the plugin subsystem.
		if ( true == false && $data['type'] == Stream::TYPE_MESSAGE ) {
			foreach ( $this->_commands AS $data ) {
				if ( preg_match($data['regex'], $data['message']) && is_callable($data['callback']) ) {
					Logger::get_instance()->debug(__FILE__, __LINE__, '[XMPP - Plugin] Calling plugin '.$data['plugin']);
					call_user_func($data['callback'], $data);
				}
			}
		}
	}
}

class XMPPError extends \Exception {  }
