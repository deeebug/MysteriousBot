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
##  [*] Created: 6/3/2011                             ##
##  [*] Last edit: 6/3/2011                           ##
## ################################################## ##

namespace Mysterious\Bot\XMPP;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\XMPP;

class Plugin {
	private $data = array();
	
	final public function __construct() { }
	
	final public function __setdata($data) {
		$this->data = $data;
	}
	
	final public function register_command($regex, $function) {
		if ( substr($regex, 0, 1) != '/' || substr($regex, -1) != '/' )
			$regex = '/^'.$regex.'/';
		
		list(,$class) = explode('\\', get_class($this));
		XMPP::get_instance()->register_command($regex, $function, $class);
	}
	
	final function message($to, $body) {
		Stream::get_instance()->message($to, $body, 'chat');
	}
	
	final function respond($body) {
		Stream::get_instance()->message($this->data['from'], $body, 'chat');
	}
}
