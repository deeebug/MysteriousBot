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
##  [?] File name: db.php                             ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/29/2011                            ##
##  [*] Last edit: 5/29/2011                          ##
## ################################################## ##

namespace Database;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\Config;

class DB extends Singleton {
	private $_timerid = false;
	protected $db;
	
	public function __call($name, $args) {
		return call_user_func_array(array($this->db, $name), $args);
	}
	
	public function setup() {
		$config = Config::get_instance();
		$type = $config->get('database.type');
		
		if ( $type === false ) {
			throw new DatabaseError('Database support is enabled, however there is no type set! Valid choices are pdo, mysql, and sqlite');
		} else {
			if ( $config->get('database.'.$type) === false ) {
				throw new DatabaseError('Database type was set to '.$type.', however there is no configuration set for it!');
			}
		}
		
		try {
			switch ( strtolower($type) ) {
				case 'pdo':
					$this->db = new \PDO($config->get('database.'.$type.'.dsn', null), $config->get('database.'.$type.'.username', null), $config->get('database.'.$type.'.password', null));
				break;
				
				case 'mysql':
					$settings = $config->get('database.'.$type);
					$this->db = new \PDO('mysql:host='.$settings['host'].';dbname='.$settings['database'], $settings['username'], $settings['password']);
				break;
				
				case 'sqlite':
					$this->db = new \PDO('sqlite:'.$config->get('database.'.$type.'.file'));
				break;
				
				default:
					throw new DatabaseError('Database type '.$type.' is not supported!');
				break;
			}
		} catch ( \PDOException $e ) {
			throw new DatabaseError('Error while connecting to the database: '.$e->getMessage());
		}
		
		// Let's look at the current directory, and run any files that != *.php
		$ranfiles = array();
		foreach (new DirectoryIterator(BASE_DIR.'database') as $info) {
			if ( $info->isDot() ) continue;
			
			if ( substr($info->getFilename(), -4) != '.php' ) {
				$this->db->query(file_get_contents(BASE_DIR.'database/'.$info->getFilename()));
				$ranfiles[] = $info->getFilename();
			}
		}
		
		if ( !empty($ranfiles) ) {
			Logger::get_instance()->debug(__FILE__, __LINE__, '[Database] Ran '.count($ranfiles).' database files - '.implode(', ', $ranfiles));
		}
		
		if ( $this->_timerid === false ) {
			$this->_timerid = Timer::register($config->get('database.ping', 60*10), array($this, 'ping'));
		}
	}
	
	public function ping() {
		try {
			$this->db->query('SELECT 1');
		} catch ( \PDOException $e ) {
			$this->setup();
		}
		
		return true;
	}
}

class DatabaseError extends \Exception { }
