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

use Mysterious\Bot\XMPP\Plugin;

class ExamplePlugin extends Plugin {
	private $data;
	
	public function __initialize() {
		$this->register_command('!test', 'cmd_test');
	}
	
	public function cmd_test() {
		$this->respond('!test? TEST!');
	}
}
