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
##  [?] File description: Only for dev stuff...sshhh  ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/24/2011                            ##
##  [*] Last edit: 5/24/2011                          ##
## ################################################## ##

defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

return array(
	'debug' => true, // Enable debug-mode?
	
	'connection' => array(
		'type' => 'client', // Valid types are 'server' or 'client'. Server requires a linkpass, client doesn't.
		'linkpass' => 'my_linkpass_here', // If your type above is server, it requires a linkpass
		'linkname' => 'my.mysterious.bot', // The linked server's name. Again, requires type to be server
		
		'server' => 'localhost', // The IRC Server. REQUIRED.
		'port' => 6667, // The IRC Server PORT. REQUIRED.
		'ssl' => false, // Use ssl?
		'password' => '', // If the IRC server requires a password to connect, put it here.
		'pingfrequency' => 60*3, // How long to send a ping to the server, if the bot has not recieved a PING request yet. Currently 3 minutes.
		
		// The following config is only used when the connection type is server.
		'servercfg' => array(
			'clients' => array(
			
				// Please view the docs/server_clients.txt file, for the syntax
				// The following will spawn 2 clients, with the name "MysteriousClient1" and "MysteriousClient2"
				'client1' => array(
					'nick' => 'MysteriousClient1',
					'ident' => 'client',
					'name' => 'Mysterious Client',
					'modes' => 'bir',
					'autojoin' => array(
						'#clients',
					),
				),
				
				'client2' => array(
					'nick' => 'MysteriousClient1',
					'ident' => 'client',
					'name' => 'Mysterious Client',
					'modes' => 'bir',
					'autojoin' => array(
						'#clients',
					),
				),
			),
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
	
	'irc' => array(
		'nickserv' => array(
			'use' => false, // If you identify to nickserv, change false to true
			'nick' => 'NickServ', // If the nickserv service is different than nickserv, put it here
			'password' => 'mys3kretpass', // Your nickserv password
			'auto_ghost' => true, // To auto ghost, if someone has the nick
		),
		
		'oper' => array(
			'use' => true, // If you have an O:Line aka oper, change false to true
			'username' => 'debug', // Your O:Line username
			'password' => 'debug', // Your O:Line password
		),
		
		'nick' => 'MysteriousBot', // Your IRC Nickname
		'ident' => 'mysterious', // Your IRC 'ident'
		'name' => 'Mysterious Bot', // Your IRC Real Name
		
		'autojoin' => array(
			'#mysteriousbot',
			'#logging',
		),
	),
	
	'autoload' => array(
		'', // Autoload plugins. Must be in the /plugins dir. Put each name on a new line.
	),
	
	'logger' => array(
		'default' => 'STDOUT', // Default logger script
	),
	
	'ctcp' => array(
		// Here you can set your CTCP replies
		// Format:
		// 'name' => 'reply',
		'__default' => 'WaDDuDoIN?', //This is the default reply for ANY NON-SET CTCP REPLY. Keep blank, if you want no reply.
		'version'   => 'MysteriousBot v'.MYSTERIOUSBOT_VERSION,
		'source'    => 'https://github.com/deeebug/mysteriousbot'
		'finger'    => 'Don\'t finger me!',
	),
	
	'yes_i_edited_this' => true, // IMPORTANT! CHANGE THIS TO TRUE!
);
