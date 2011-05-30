<?php
## ################################################## ##
##                   MysteriousBot                    ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##  [@] Uses: MysteriousCode [Fragments]              ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: run.php                            ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/29/2011                          ##
## ################################################## ##

## ################################################## ##
##                 Please edit below                  ##
## ################################################## ##

// Config profile. The filename should be in the config
// directory, and follow the same syntax as default.php
$config_profile = 'default';

## ################################################## ##
##                   STOP EDITING!                    ##
## ################################################## ##

define('BASE_DIR', __DIR__.'/');
defined('STDIN') && define('IS_CLI', true);
define('Y_SO_MYSTERIOUS', true);
define('MYSTERIOUSBOT_VERSION', '1.0-ALPHA1');

require BASE_DIR.'mysterious/autoloader.php';

use Mysterious\Bot\Config;
use Mysterious\Bot\Kernal;
use Mysterious\Bot\Logger;

Config::get_instance()->import($config_profile) or Logger::get_instance()->fatal(__FILE__, __LINE__, 'Failed to import Config!  Message: '.Config::$last_error);

Kernal::get_instance()
	->initialize()
	->loop()
	->loop_finish();
