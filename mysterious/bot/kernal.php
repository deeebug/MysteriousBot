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

class Kernal extends Singleton {
	
	public function initialize() {
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
		
		return $this;
	}
	
	public function loop() {
		
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
