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
##  [?] File name: dev.php                            ##
##  [?] File type: Config profile                     ##
##  [?] File description: Please edit this file!      ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/24/2011                            ##
##  [*] Last edit: 5/29/2011                          ##
## ################################################## ##

defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

return array(
	'debug'  => false, // Enable debug-mode?
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
			'nick'     => 'Client01-Client',
			'ident'    => 'mysterious',
			'name'     => 'Mysterious Bot 001',
			
			// Optional settings
			'nickserv' => array(
				'use'      => false,
				'nick'     => 'NickServ',
				'password' => 'mys3kr3tp@ass',
				'ghost'    => true,
			),
			
			'oper'     => array(
				'use'      => true,
				'username' => 'debug',
				'password' => 'debug',
			),
			
			'autojoin' => array(
				'#mysteriousbot',
				'#test01',
			),
			
			// What plugins will be used by the bot.
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
			'linkpass' => 'LiNk',
			'linkname' => 'mysteriousbot.com',
			'linkdesc' => 'MysteriousBot U:Lined Server',
			
			// Clients - ATLEAST ONE IS REQUIRED.
			'clients'  => array(
				'global' => array(
					// Client settings
					'nick'  => 'Global[Mysterious]',
					'ident' => 'mysterious',
					'host'  => 'zomg.hello',
					'name'  => 'MysteriousBot Bots',
					'mode'  => 'Sq',
					
					'autojoin' => array(
						'#mysteriousbot-ulined',
						'#test01-ulined',
					),
					
					'plugins'  => array(
						'exampleplugin',
					),
				),
				
				'client1' => array(
					// Client settings
					'nick'  => 'Client01-Server',
					'ident' => 'client',
					'host'  => '01',
					'name'  => 'The Client 01',
					'mode'  => 'BSq',
					
					'autojoin' => array(
						'#opers',
					),
				),
				
				'client2' => array(
					// Client settings
					'nick'  => 'Client02-Server',
					'ident' => 'client',
					'host'  => '02',
					'name'  => 'The Client 02',
					'mode'  => 'BSq',
					
					'autojoin' => array(
						'#opers',
					),
				),
			),
			
			// Optional Settings
			'globalchan' => '#bots', // All bots will be found here
		),
		
		'mysteriousbot003' => array(
			// Bare settings
			'enabled'  => false,
			'type'     => 'client',
			
			// Connection settings
			'server'   => 'localhost',
			'port'     => 6667,
			'ssl'      => false,
			'nick'     => 'Client02-Client',
			'ident'    => 'mysterious',
			'name'     => 'Mysterious Bot 003',
			
			// Optional settings
			'nickserv' => array(
				'use'      => false,
				'nick'     => 'NickServ',
				'password' => 'mys3kr3tp@ass',
				'ghost'    => true,
			),
			
			'oper'     => array(
				'use'      => true,
				'username' => 'debug',
				'password' => 'debug',
			),
			
			'autojoin' => array(
				'#mysteriousbot',
				'#test01',
			),
		),
	),
	
	'socketserver' => array(
		'enabled'      => true, // Enable the Socket Server - API
		'ip'           => '127.0.0.1', // Port for the socket server to run on
		'port'         => 7363, // Port for the socket server to run on
		'password'     => 'd3atht0y0u', // Password required to validate command
		'max_clients'  => 10, // Max number of clients accepted to be connected.
		'max_attempts' => 10, // Max number of attempts until socket is disconnected.
	),
	
	'httpserver'  => array(
		'enabled'      => false, // Enable the HTTP Server - WebUI
		'ip'           => '127.0.0.1', // Port for the socket server to run on. Usually 127.0.0.1
		'port'         => 8008, // Port for the socket server to run on
		'webroot'      => BASE_DIR.'public/', // The webroot of the HTTP Server. Must include trailing slash!
		'protect'      => true, // Protect the HTTP Server with a password?
		'password'     => 'abcGTFO', // The password.
		'max_clients'  => 30, // Max number of clients accepted to be connected. Keep it relatively high (30-ish)
	),
	
	'xmpp' => array(
		'enabled'       => true, // Enable XMPP Bot?
		'host'          => 'talk.google.com', // XMPP Server
		'port'          => 5222, // XMPP Port
		'username'      => 'myemail', // XMPP Username. The stuff before the "@" in your email
		'domain'        => 'gmail.com', // XMPP Domain.  The stuff after the "@" in your email
		'password'      => 'mypassword', // Your password
		
		'resource'      => 'MysteriousBot', // Resource name
		'autosubscribe' => true, // Auto subscribe (friend) back?
		
		'admins'        => array(
			'my-admin-email@gmail.com', // Your email address, when contacting the bot
		),
		
		'plugin_dir'    => BASE_DIR.'/xmpp_plugins/', // Must include trailing slash!
		'plugins'       => array(
			'exampleplugin', // Plugin
		),
	),
	
	'database' => array(
		'enabled' => false, // Enable the DB
		'type'   => 'pdo', // Types. Valid options are 'mysql', 'sqlite', and 'pdo'. Must be lowercase.
		'ping'   => 60*5,  // How often we should ping the DB server, so we know we're still connected.
		
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
		// Format:
		// 'name' => 'reply',
		// !!name must be lowercase!!
		'__default' => 'WaDDuDoIN?', //This is the default reply for ANY NON-SET CTCP REPLY. Keep blank, if you want no reply.
		'version'   => 'MysteriousBot v'.MYSTERIOUSBOT_VERSION,
		'source'    => 'https://github.com/deeebug/mysteriousbot',
		'finger'    => 'Don\'t finger me!',
	),
	
	'yes_i_edited_this' => false, // IMPORTANT! CHANGE THIS TO TRUE!
);
