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
##  [*] Created: 6/3/2011                             ##
##  [*] Last edit: 6/3/2011                           ##
## ################################################## ##

namespace XMPP_Plugins;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Socket;
use Mysterious\Bot\IRC\BotManager;
use Mysterious\Bot\XMPP\Plugin;
use Mysterious\Bot\XMPP\Stream;

class ExamplePlugin extends Plugin {
	private $data;
	
	public function __initialize() {
		$this->register_command('/^[Dd]isconnect/', 'cmd_disconnect');
	}
	
	public function cmd_disconnect() {
		$args = explode(' ', $this->data['message']);
		array_shift($args);
		$bot = array_shift($bot);
		
		$BM = BotManager::get_instance();
		$sid = $BM->_bot2sid($bot);
		if ( empty($sid) ) {
			$this->respond('Unknown Bot UUID. Maybe you misspelled it?');
			return;
		}
		
		Socket::get_instance()->close($sid);
		$BM->destroy_bot($uuid);
		
		$this->respond('Bot UUID '.$bot.' was shutdown.');
		Stream::get_instance()->update_presence('status', sprintf('Bot Status: Online | Connected Bots: %s', count($BM->_bots)));
	}
}
