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
##  [?] File name: httpserver.php                     ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/13/2011                            ##
##  [*] Last edit: 6/19/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\IRC\BotManager;

class HTTPServer extends Singleton {
	private $closed   = false;
	private $_codes   = array(
		100 => '100 Continue',
		200 => '200 OK',
		201 => '201 Created',
		204 => '204 No Content',
		206 => '206 Partial Content',
		300 => '300 Multiple Choices',
		301 => '301 Moved Permanently',
		302 => '302 Found',
		303 => '303 See Other',
		304 => '304 Not Modified',
		307 => '307 Temporary Redirect',
		400 => '400 Bad Request',
		401 => '401 Unauthorized',
		403 => '403 Forbidden',
		404 => '404 Not Found',
		405 => '405 Method Not Allowed',
		406 => '406 Not Acceptable',
		408 => '408 Request Timeout',
		410 => '410 Gone',
		413 => '413 Request Entity Too Large',
		414 => '414 Request URI Too Long',
		415 => '415 Unsupported Media Type',
		416 => '416 Requested Range Not Satisfiable',
		417 => '417 Expectation Failed',
		500 => '500 Internal Server Error',
		501 => '501 Method Not Implemented',
		503 => '503 Service Unavailable',
		506 => '506 Variant Also Negotiates'
	);
	private $_exts = array(
		'html' => 'text/html',
		'htm'  => 'text/html',
		'php'  => 'text/html',
		
		'css'  => 'text/plain',
		'js'   => 'text/plain',
		'txt'  => 'text/plain',
		
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'jpg'  => 'image/jpg',
		'jpeg' => 'image/jpeg',
		'ico'  => 'image/x-icon'
	);
	private $_log = array();
	private $_password = null;
	private $_protect = false;
	private $_timerid = false;
	private $_timeout = 3000;
	
	public function addlog($log) {
		$this->_log[] = date('h:i:s').' '.$log;
	}
	
	public function setup() {
		$this->_password = Config::get_instance()->get('httpserver.password', null);
		$this->_protect = Config::get_instance()->get('httpserver.protect', false);
		$this->_timeout = 60*5; // 5 minutes
	}
	
	public function new_connection($socketid) { } // Does nothing.
	
	public function handle_read($socketid, $raw) {
		$this->closed = false;
		$parsed = $this->_parse($socketid, $raw);
		
		$this->serve($socketid, $parsed);
		
		if ( $this->closed === false )
			Socket::get_instance()->close($socketid);
	}
	
	private function _parse($socketid, $raw) {
		$parsed = $_REQUEST = $_COOKIE = $_GET = $_POST = array();
		
		// Parse the headers
		foreach ( explode("\n", $raw) AS $key => $rawline ) {
			if ( $key == 0 ) {
				list($parsed['METHOD'], $parsed['URI'], $parsed['HTTP_VERSION']) = explode(' ', $rawline);
				continue;
			}
			
			$tmp = explode(': ', $rawline);
			
			if ( isset($tmp[1]) )
				$parsed[str_replace('-', '_', strtoupper($tmp[0]))] = $tmp[1];
		}
		
		// Validate the parsed headers
		if ( !isset($parsed['METHOD']) || !isset($parsed['URI']) || empty($parsed) ) {
			$this->generate_response($socketid, 400, 'text/html', 'Invalid headers passed');
			return;
		}
		
		// Do some security on the URI
		$parsed['URI'] = str_replace('../', '', $parsed['URI']);
		$parsed['URI'] = str_replace('./', '', $parsed['URI']);
		
		// Parse the URI
		$tmp = parse_url($parsed['URI']);
		$parsed['PATH'] = $tmp['path'];
		
		if ( isset($tmp['query']) ) {
			$parsed['QUERYSTR'] = $tmp['query'];
			parse_str($tmp['query'], $parsed['HTTP_GET_VARS']);
		}
		
		// Parse the directory/file
		$tmp = explode('/', $parsed['PATH']);
		$parsed['FILE'] = array_pop($tmp);
		$parsed['DIR'] = implode('/', $tmp);
		
		// Empty file? Baaaad....
		if ( empty($parsed['FILE']) ) {
			if ( !empty($parsed['DIR']) ) $parsed['DIR']  .= '/'.$parsed['FILE'];
			$parsed['FILE'] = 'index.php';
		}
		
		// Get file ext.
		$tmp = explode('.', $parsed['FILE']);
		$parsed['FILE_EXT'] = array_pop($tmp);
		
		// Parse the cookie(s)
		if ( isset($parsed['COOKIE']) ) {
			parse_str($parsed['COOKIE'], $_COOKIE);
		}
		
		// Parse some POST vars
		if ( $parsed['METHOD'] == 'POST' ) {
			$tmp = explode("\n", $raw);
			parse_str(array_pop($tmp), $parsed['HTTP_POST_VARS']);
		}
		
		// Fill global vars.
		if ( isset($parsed['HTTP_GET_VARS']) || isset($parsed['HTTP_POST_VARS']) ) {
			if ( isset($parsed['HTTP_GET_VARS']) )
				$_GET = $parsed['HTTP_GET_VARS'];
			
			if ( isset($parsed['HTTP_POST_VARS']) )
				$_POST = $parsed['HTTP_POST_VARS'];
			
			$_REQUEST = array_merge($_COOKIE, $_GET, $_POST);
		}
		
		unset($tmp);
		
		return $parsed;
	}
	
	private function generate_authhash() {
		$id = uniqid('W');
		$time = time();
		return str_rot13($id) . '-' . $time . '-' . md5($id . $time . $this->_password.'-MysteriousBot/'.MYSTERIOUSBOT_VERSION);
	}
	
	private function serve($socketid, $parseddata) {
		$file = Config::get_instance()->get('httpserver.webroot').$parseddata['DIR'].'/'.$parseddata['FILE'];
		
		$authhash = null;
		if ( $this->_protect === true ) {
			$matches = null;
			
			// Some hack...
			if ( isset($_GET['a']) )
				$_GET['auth'] = $_GET['a'];
			
			
			if ( isset($_GET['login']) && isset($_POST['password']) && !empty($_POST['password']) ) {
				if ( $_POST['password'] != $this->_password ) {
					$this->generate_response($socketid, 401, 'text/html', file_get_contents(Config::get_instance()->get('httpserver.webroot').'/_auth/badpass.html'));
					return;
				} else {
					$authhash = $this->generate_authhash();
					$headers = array(
						'Location' => '/index.php?auth='.$authhash
					);
					$this->generate_response($socketid, 301, 'text/plain', 'Please wait to be redirected....', $headers);
				}
			} else if ( isset($_GET['login']) ) {
				$this->generate_response($socketid, 401, 'text/html', file_get_contents(Config::get_instance()->get('httpserver.webroot').'/_auth/badpass.html'));
				return;
			} else if ( !isset($_GET['auth']) || empty($_GET['auth']) ) {
				if ( isset($parseddata['X_REQUESTED_WITH']) && $parseddata['X_REQUESTED_WITH'] == 'XMLHttpRequest' )
					$headercode = 403;
				else
					$headercode = 200;
				
				$this->generate_response($socketid, $headercode, 'text/html', file_get_contents(Config::get_instance()->get('httpserver.webroot').'/_auth/login.html'));
				return;
			} else if ( isset($_GET['auth']) && preg_match('/^(\w++)-(\d++)-(\w++)$/', $_GET['auth'], $matches) ) {
				$username = str_rot13($matches[1]);
				$time = (int)$matches[2];
				$authhash = $matches[3];
				
				if ( md5($username . $time . $this->_password.'-MysteriousBot/'.MYSTERIOUSBOT_VERSION) === $authhash && $time >= time() - $this->_timeout ) {
					$authhash = $this->generate_authhash();
				} else {
					$this->generate_response($socketid, 403, 'text/html', file_get_contents(Config::get_instance()->get('httpserver.webroot').'/_auth/hacking.html'));
					return;
				}
			} else {
				$this->generate_response($socketid, 500, 'text/html', 'Sorry, server has failed. Check '.__LINE__.' in ./mysterious/bot/httpserver.php'.str_repeat('<!--padding-->'."\n", 20));
				return;
			}
		}
		
		if ( ($data = $this->_servespecial($parseddata, $authhash)) !== false ) {
			$this->generate_response($socketid, 200, 'text/plain', $data);
		} else if ( file_exists($file) && is_file($file) ) {
			$return = file_get_contents($file);
			
			if ( stripos($return, '<?php') !== false ) {
				unset($return);
				$parseddata['FILE_EXT'] = 'php';
				
				ob_start();
				include $file;
				$return = ob_get_contents();
				ob_end_clean();
			}
			
			$this->generate_response($socketid, 200, $this->get_type($parseddata['FILE_EXT']), $return);
		} else {
			$this->generate_response($socketid, 404, 'text/html', '<html><head><title>404 Not Found</title></head><body bgcolor="white"><center><h1>404 Not Found</h1></center><hr><center>MysteriousBot/'.MYSTERIOUSBOT_VERSION.'</center></body></html>'.str_repeat('<!-- Padding, goddamn padding -->'."\n", 10));
		}
	}
	
	private function _servespecial($data, $authhash) {
		if ( $data['DIR'] != 'api' && array_search(strtolower($data['FILE']), array('getbots', 'boot', 'shutdown', 'getsockets', 'socketshutdown', 'shutdownbot', 'checkconsole', 'consolepoll', 'loadedplugins', 'loadplugin')) === false ) return false;
		
		switch ( strtolower($data['FILE']) ) {
			// Bots
			case 'getbots':
				$stuff = array();
				$stuff['auth'] = $authhash;
				
				foreach ( Config::get_instance()->get('clients') AS $botuuid => $botdata ) {
					if ( BotManager::get_instance()->get_bot($botuuid) !== false ) {
						$bot = BotManager::get_instance()->get_bot($botuuid);
						$nick = isset($botdata['nick']) ? $botdata['nick'] : 'N/A';
						$ident = isset($botdata['ident']) ? $botdata['ident'] : 'N/A';
						$name = isset($botdata['name']) ? $botdata['name'] : 'N/A';
						$status = true;
						
						if ( isset($bot->channels) && !isset($bot->enforcer) )
							$channels = array_keys($bot->channels);
						else
							$channels = array('N/A');
					} else {
						$nick = $ident = $name = 'N/A';
						$status = false;
						$channels = array('N/A');
					}
					
					$stuff[] = array(
						'status' => $status,
						'uuid' => $botuuid,
						'server' => $botdata['server'],
						'port' => $botdata['port'],
						'nick' => $nick,
						'ident' => $ident,
						'name' => $name,
						'type' => ucfirst(strtolower($botdata['type'])),
						'channels' => implode(',', $channels)
					);
					
					if ( $status === true && $botdata['enabled'] === true && isset($botdata['type']) && strtolower($botdata['type']) == 'server' && isset($botdata['clients']) && !empty($botdata['clients']) ) {
						$key = count($stuff)-2;
						
						$stuff[$key]['is_server'] = true;
						$stuff[$key]['children'] = array();
						
						$children =& $stuff[$key]['children'];
						$botchans = isset(BotManager::get_instance()->get_bot($botuuid)->botchans) ? BotManager::get_instance()->get_bot($botuuid)->botchans : array();
						
						foreach ( $botdata['clients'] AS $clientuuid => $clientsettings ) {
							$children[] = array(
								'uuid' => $clientuuid,
								'server' => $botdata['server'],
								'port' => $botdata['port'],
								'nick' => $clientsettings['nick'],
								'ident' => $clientsettings['ident'],
								'name' => $clientsettings['name'],
								'type' => 'Server Client',
								'channels' => isset($botchans[$clientuuid]) ? $botchans[$clientuuid] : 'N/A'
							);
						}
						
						$children[] = array(
							'uuid' => '_enforcer',
							'server' => $botdata['server'],
							'port' => $botdata['port'],
							'nick' => BotManager::get_instance()->get_bot($botuuid)->enforcer,
							'ident' => 'services',
							'name' => $botdata['linkdesc'].' Enforcer Bot',
							'type' => 'Server Enforcer',
							'channels' => 'N/A'
						);
					}
				}
				
				return json_encode($stuff);
			break;
			
			case 'boot':
				if ( !isset($_GET['uuid']) )
					return 'ERROR::No UUID.::'.$authhash;
				
				if ( Config::get_instance()->get('clients.'.$_GET['uuid'], false) === false )
					return 'ERROR::Unknown UUID.::'.$authhash;
				
				try {
					Kernal::get_instance()->boot($_GET['uuid'], Config::get_instance()->get('clients.'.$_GET['uuid']));
					return 'OKAY::Booted::'.$authhash;
				} catch ( KernalError $e ) {
					return 'ERROR::'.$e->getMessage().'::'.$authhash;
				}
			break;
			
			case 'shutdown':
				if ( !isset($_GET['uuid']) )
					return 'ERROR::No UUID.::'.$authhash;
				
				if ( Config::get_instance()->get('clients.'.$_GET['uuid'], false) === false )
					return 'ERROR::Unknown UUID.::'.$authhash;
				
				try {
					Kernal::get_instance()->shutdown($_GET['uuid']);
					return 'OKAY::Shutdown::'.$authhash;
				} catch ( KernalError $e ) {
					return 'ERROR::'.$e->getMessage().'::'.$authhash;
				}
			break;
			
			/////// Socket page
			case 'getsockets':
				$rtn = array();
				
				foreach ( Socket::get_instance()->_sockets AS $sid => $data ) {
					$type = (substr(strtolower($sid), 0, 1) == 'c' ? 'Client' : 'Listener');
					$host = isset($data['host']) ? $data['host'] : $data['ip'];
					$ssl = isset($data['ssl']) ? $data['ssl'] : false;
					
					if ( is_array($data['callback']) )
						$callback = 'Class: '.(is_object($data['callback'][0]) ? get_class($data['callback'][0]) : $data['callback'][0]).' | Function: '.$data['callback'][1];
					else
						$callback = 'Unknown';
					
					$rtn[] = array(
						'id' => $sid,
						'type' => $type,
						'host' => $host,
						'port' => $data['port'],
						'ssl' => $ssl,
						'record' => get_host($host),
						'callback' => $callback
					);
				}
				
				$rtn['auth'] = $authhash;
				return json_encode($rtn);
			break;
			
			case 'socketshutdown':
				if ( !isset($_GET['sid']) )
					return 'ERROR::No Socket ID.::'.$authhash;
				
				if ( Socket::get_instance()->get_data($_GET['sid']) === false )
					return 'ERROR::Unknown UUID.::'.$authhash;
				
				Socket::get_instance()->close($_GET['sid']);
				Logger::get_instance()->info(__FILE__, __LINE__, 'Shutdown socket id '.$_GET['sid']);
				return 'OKAY::Shutdown::'.$authhash;
			break;
			
			//// Shutdown bot
			case 'shutdownbot':
				Kernal::get_instance()->stop_loop();
			break;
			
			// Console
			case 'checkconsole':
				if ( ($logger = Config::get_instance()->get('logger.default', null)) != 'STDOUT_HTTP' ) {
					return 'ERROR::Error - Your default logger must be "STDOUT_HTTP", instead of what it is currently ('.$logger.')::'.$authhash;
				}
				
				return 'OKAY::Proceed::'.$authhash;
			break;
			
			case 'consolepoll':
				return json_encode(array_merge(array('key'=>$authhash), $this->_log));
			break;
			
			// Plugin
			case 'loadedplugins':
				return json_encode(array_merge(array('key'=>$authhash), array_keys(PluginManager::get_instance()->_plugins)));
			break;
			
			case 'loadplugin':
				if ( empty($_GET['plugin']) || empty($_GET['affect']) )
					return 'ERROR::Empty plugin/affecting bots::'.$authhash;
				
				if ( !file_exists(BASE_DIR.'plugins/'.strtolower($_GET['plugin']).'.php') )
					return 'ERROR::Unknown plugin/Does not exist::'.$authhash;
				
				foreach ( explode(',', $_GET['affect']) AS $bot ) {
					if ( count(explode('-', $bot)) != 2 )
						continue;
					
					list($type, $uuid) = explode('-', $bot);
					
					switch ( strtolower($type) ) {
						case 'server':
						case 'client':
							PluginManager::get_instance()->load_plugin($_GET['plugin'], $uuid, true);
						break;
						
						// Server client
						default:
							// Verify
							if ( Config::get_instance()->get('clients.'.$uuid.'.clients.'.$type, false) === false ) continue;
							
							// Load.
							PluginManager::get_instance()->load_plugin($_GET['plugin'], $uuid, true, array($type));
						break;
					}
				}
				
				return 'OKAY::Loaded plugin '.$_GET['plugin'].' to '.$_GET['affect'].'::'.$authhash;
			break;
			
			default:
				return false;
			break;
		}
	}
	
	private function get_type($ext) {
		return isset($this->_exts[$ext]) ? $this->_exts[$ext] : 'text/html';
	}
	
	private function generate_response($socketid, $status, $type, $content, $extra_headers=array()) {
		$response  = 'HTTP/1.1 '.$this->code($status)."\r\n";
		$response .= 'Date: '.gmdate('D, d M Y H:i:s T')."\r\n";
		$response .= 'Server: MysteriousBot/'.MYSTERIOUSBOT_VERSION."\r\n";
		$response .= 'Content-Type: '.$type."\r\n";
		$response .= 'Connection: close'."\r\n";
		$response .= 'Content-Length: '.strlen($content)."\r\n";
		
		if ( !empty($extra_headers) ) {
			foreach ( $extra_headers AS $key => $val )
				$response .= $key.': '.$val."\r\n";
		}
		
		$response .= "\r\n";
		$response .= $content;
		
		Socket::get_instance()->write($socketid, $response, true);
		Socket::get_instance()->close($socketid);
		$this->closed = true;
	}
	
	private function code($num) {
		if ( !is_numeric($num) ) return $num;
		
		return isset($this->_codes[$num]) ? $this->_codes[$num] : '200 OK';
	}
}

/*
 * @author: Norf
 * @link: http://www.php.net/manual/en/function.gethostbyaddr.php#99826
 */
function get_host($ip){
	$ptr= implode('.', array_reverse(explode('.',$ip))).'.in-addr.arpa';
	$host = dns_get_record($ptr, DNS_PTR);
	
	if ( $host == null )
		return $ip;
	else
		return $host[0]['target'];
}
