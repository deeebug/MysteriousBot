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
##  [?] File name: kernal.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\Socket;

class Kernal extends Singleton {
	public $IRC_SID;
	public $SOCKET_SID;
	private $bot;
	private $sserver; // Socket Server
	
	public function initialize() {
		// Are we even on the CLI?
		if ( !defined('IS_CLI') ) {
			throw new KernalError('Fatal error - Please run MysteriousBot from the command line!');
		}
		
		// First we make sure they edited the config correctly
		$required_settings = array(
			'connection.type', 'connection.server', 'connection.port',
			'irc.nick', 'irc.ident', 'irc.name',
			'yes_i_edited_this'
		);
		
		$config = Config::get_instance();
		
		foreach ( $required_settings AS $setting ) {
			if ( $config->get($setting) === false || is_empty($config->get($setting)) ) {
				throw new KernalError('Configuration error - The setting "'.$setting.'" is not set/empty! Please double check the config file, edit fully, and read all comments and documentation!');
			}
		}
		
		if ( !in_array($config->get('connection.type', 'N/A'), array('client', 'server')) ) {
			throw new KernalError('Configuration error - The connection type must be either "client" or "server"!  The value it currently has is '.$config->get('connection.type'));
		}
		
		// Check if they completely edited for the server settings.
		if ( $config->get('connection.type') == 'server' ) {
			$required_settings = array(
				'connection.linkpass', 'connection.linkname'
			);
			
			foreach ( $required_settings AS $setting ) {
				if ( $config->get($setting) === false || is_empty($config->get($setting)) ) {
					throw new KernalError('Configuration error - The setting "'.$setting.'" is not set/empty! Please double check the config file, edit fully, and read all comments and documentation!');
				}
			}
		}
		
		// Now lets start the socket server, if they enabled it
		if ( $config->get('socketserver.enabled') === true ) {
			$required_settings = array(
				'socketserver.ip', 'socketserver.port'
			);
			
			foreach ( $required_settings AS $setting ) {
				if ( $config->get($setting) === false || is_empty($config->get($setting)) ) {
					throw new KernalError('Configuration error - The setting "'.$setting.'" is not set/empty! Please double check the config file, edit fully, and read all comments and documentation!');
				}
			}
			
			$ip   = $config->get('socketserver.ip');
			$port = $config->get('socketserver.port');
			$this->SOCKET_SID = Socket::get_instance()->add_listener($ip, $port, array(__NAMESPACE__.'\SocketServer', 'handle_read'));
		}
		
		// Finally, lets init the bot
		$class = __NAMESPACE__.'\IRC\\'.ucfirst(strtolower(Config::get_instance()->get('connection.type')));
		$this->bot = $class::get_instance();
		
		// They pass the test..let them continue
		return $this;
	}
	
	public function loop() {
		$config = Config::get_instance();
		$host = $config->get('connection.server');
		$port = $config->get('connection.port');
		$ssl  = $config->get('connection.ssl');
		
		$SM = Socket::get_instance();
		$this->IRC_SID = $SM->add_client($host, $port, $ssl, array($this->bot, 'handle_read'));
		$this->bot->set_sid($this->IRC_SID);
		
		$SM->loop();
		
		return $this;
	}
	
	public function loop_finish() {
		Logger::get_instance()->fatal(__FILE__, __LINE__, 'Loop has finished! :(');
		
		return $this;
	}
}

class KernalError extends \Exception { }

/*
 * empty() only works on vars, grr..
 * What a stupid hack, but its needed
 */
function is_empty($val) {
	return empty($val);
}
