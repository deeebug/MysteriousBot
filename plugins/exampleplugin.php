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
##  [*] Last edit: 5/26/2011                          ##
## ################################################## ##

namespace Plugins;

use Mysterious\Bot\Plugin;
#use Mysterious\Bot\Message;

class ExamplePlugin extends Plugin {
	
	public function __initialize() {
		$this->register_event('irc.privmsg', '!example', array($this, 'cmd_example'));
		$this->register_event('irc.privmsg', '/^!example/', array($this, 'cmd_example'));
		$this->register_event('irc.privmsg', array($this, 'catchall'));
		
		$this->register_help('!example', 'The help info for !example');
	}
	
	public function cmd_example() {
		$this->privmsg(Message::$channel, 'Hello!');
	}
	
	public function catchall() {
		//$this->privmsg(Message::$channel, strrev(Message::$message));
		// Do nothing.
	}
}
