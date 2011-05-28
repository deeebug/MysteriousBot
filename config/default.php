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
##  [?] File name: config.php                         ##
##  [?] File type: Config profile                     ##
##  [?] File description: Please edit this file!      ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 5/28/2011                          ##
## ################################################## ##

defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

return array(
	'debug'  => true, // Enable debug-mode?
	'usleep' => 1000, // How long before each socket read should the script wait? In microseconds
	
	'clients' => array(
		'mysteriousbot001' => array(
			// Bare settings
			'enabled'  => true,
			'type'     => 'client',
			
			// Connection settings
			'server'   => 'localhost',
			'port'     => 6667,
			'ssl'      => false,
			'nick'     => 'MyBot',
			'ident'    => 'bot',
			'name'     => 'My Little Bot!',
			
			// Optional settings
			'nickserv' => array(
				'use'      => false,
				'nick'     => 'NickServ',
				'password' => 'mys3kr3tp@ass',
				'ghost'    => true,
			),
			
			'oper'     => array(
				'use'      => true,
				'username' => 'myoperusername',
				'password' => 'myverysecrerandstrongoperpassword123',
			),
			
			'autojoin' => array(
				'#mysteriousbot',
				array('#mysecretchannel', 'mysecretpassword'),
			),
			
			// What plugins will be used by the bot.
			// @@@ NEEDED!!!! Also put it in the autoload.
			'plugins' => array(
				'exampleplugin'
			),
		),
		
		'mysteriousbot002' => array(
			// Bare settings
			'enabled'  => false,
			'type'     => 'server',
			
			// Connection settings
			'server'   => 'localhost',
			'port'     => 6667,
			'ssl'      => false,
			'linkpass' => 'MyLiNKPasS',
			'linkname' => 'mysteriousbot.com',
			'linkdesc' => 'Mysterious Bot Server',
			
			// Clients - ATLEAST ONE IS REQUIRED.
			'clients'  => array(
				'global' => array(
					// Client settings
					'nick'  => 'MyGlobalBot',
					'ident' => 'my',
					'host'  => 'global.bot',
					'name'  => 'Very MYSTERIOUS Bot!',
					'mode'  => 'BSq',
					
					'autojoin' => array(
						'#mysteriousbot',
						'#sekret',
					),
					
					'plugins'  => array(
						'exampleplugin',
					),
				),
			),
			
			// Optional Settings
			'globalchan' => '#opers', // All bots will be found here
		),
	),
	
	'socketserver' => array(
		'enabled' => false, // Enable the Socket Server - API
		'ip' => '127.0.0.1', // Port for the socket server to run on
		'port' => 7363, // Port for the socket server to run on
		'password' => 'd3atht0y0u', // Password required to validate command
	),
	
	'database' => array(
		'enabled' => true, // Enable the DB
		'type' => 'pdo', // Types. Valid options are 'mysql', 'sqlite', and 'pdo'. Must be lowercase.
		
		'pdo' => array(
			'dsn' => 'mysql:host=localhost;dbname=mydb', // PDO DSN
			'username' => 'root', // PDO Username
			'password' => '', // PDO Password
		),
		
		'mysql' => array(
			'host' => 'localhost', // MySQL Host
			'database' => 'my_db', // MySQL database
			'username' => 'root', // MySQL username
			'password' => '', // MySQL password
		),
		
		'sqlite' => array(
			'file' => BASE_DIR.'db/sqlite.db', // File location of the DB file.
		),
	),
	
	'autoload' => array(
		'exampleplugin', // Autoload plugins. Must be in the /plugins dir. Put each name on a new line.
	),
	
	'logger' => array(
		'default' => 'STDOUT', // Default logger script
	),
	
	'ctcp' => array(
		// Here you can set your CTCP replies
		// Their global for EVERYBOT
		// Format:
		// 'name' => 'reply',
		'__default__' => 'WaDDuDoIN?', //This is the default reply for ANY NON-SET CTCP REPLY. Keep blank, if you want no reply.
		'version'   => 'MysteriousBot v'.MYSTERIOUSBOT_VERSION,
		'source'    => 'https://github.com/deeebug/mysteriousbot',
		'finger'    => 'Don\'t finger me!',
	),
	
	'yes_i_edited_this' => false, // IMPORTANT! CHANGE THIS TO TRUE!
);
