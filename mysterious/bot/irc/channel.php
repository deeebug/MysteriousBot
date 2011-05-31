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
##  [?] File name: channel.php                        ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/30/2011                            ##
##  [*] Last edit: 5/30/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Channel {
	public $modes = '';
	public $created = '';
	
	public $usercount = 0;
	
	public $banlist = array();
	public $invites = array();
	public $nicks = array();
	
	public $topic = '';
	public $topicby = '';
	public $topicset = '';
	
	public static function new_instance() {
		return new self;
	}
}
