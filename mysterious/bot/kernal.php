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
