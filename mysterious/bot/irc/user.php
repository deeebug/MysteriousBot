<?php
## ################################################## ##
##                  MysteriousBot                     ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: user.php                           ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/30/2011                            ##
##  [*] Last edit: 5/31/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class User {
	public $nick;
	public $ident;
	public $host;
	public $name;
	
	public $channelcount = 0;
	public $connected;
	
	public $ircop = false;
	public $away = false;
	
	public $usermodes;
	public $channels = array();
	
	public static function new_instance() {
		return new self;
	}
	
	public function __get($val) {
		if ( $val == 'fullhost' )
			return $this->nick.'!'.$this->ident.'@'.$this->host;
		else
			return $this->$val;
	}
}
