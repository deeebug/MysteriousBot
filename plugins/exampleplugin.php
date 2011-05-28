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
##  [?] File name: exampleplugin.php                  ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 5/27/2011                          ##
## ################################################## ##

namespace Plugins;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Plugin;
use Mysterious\Bot\Message;

class ExamplePlugin extends Plugin {
	
	public function __initialize() {
		// Privmsg, all..
		$this->register_event('irc.privmsg', '!example', 'cmd_example');
		//$this->register_event('irc.privmsg', '/^!example/', 'cmd_example');
		
		// Privmsg, but as a PM
		//$this->register_event('irc.privmsg.private', '!hello', 'cmd_hello');
		
		// Privmsg, but in channel
		//$this->register_event('irc.privmsg.channel', '!test', 'cmd_test');
		
		// Catch all
		//$this->register_event('irc.privmsg', 'catchall');
		
		//$this->register_help('!example', 'The help info for !example');
	}
	
	public function cmd_example() {
		$this->privmsg(Message::channel(), 'Hello!');
	}
	
	// ...etc
	
	public function catchall() {
		//$this->privmsg(Message::$channel, strrev(Message::$message));
		// Do nothing.
	}
}
