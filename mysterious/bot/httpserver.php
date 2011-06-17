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
##  [*] Last edit: 6/16/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;
use Mysterious\Bot\IRC\BotManager;

class HTTPServer extends Singleton {
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
	private $_timerid = false;
	
	public function addlog($log) {
		$this->_log[] = date('h:i:s').' '.$log;
	}
	
	public function setup() { } // Does nothing.
	
	public function new_connection($socketid) { } // Does nothing.
	
	public function handle_read($socketid, $raw) {
		$this->addlog = true;
		$parsed = $this->_parse($socketid, $raw);
		
		$this->serve($socketid, $parsed);
		
		if ( $this->closed === false )
			Socket::get_instance()->close($socketid);
	}
	
	private function _parse($socketid, $raw) {
		$parsed = array();
		
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
			parse_str($tmp['query'], $query);
			$parsed['HTTP_'.$parsed['METHOD'].'_VARS'] = $query;
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
			$cookies = parse_str($parsed['COOKIE']);
			
			$_COOKIE = $cookies;
		}
		
		// Fill global vars.
		if ( isset($parsed['HTTP_'.$parsed['METHOD'].'_VARS']) ) {
			$_REQUEST = $_GET = $_POST = array();
			
			switch ( $parsed['METHOD'] ) {
				default:
				case 'GET':
					$_REQUEST = $_GET = $parsed['HTTP_'.$parsed['METHOD'].'_VARS'];
				break;
				
				case 'POST':
					$_REQUEST = $_POST = $parsed['HTTP_'.$parsed['METHOD'].'_VARS'];
				break;
			}
		}
		
		unset($tmp);
		
		return $parsed;
	}
	
	private function serve($socketid, $parseddata) {
		$file = Config::get_instance()->get('httpserver.webroot').$parseddata['DIR'].'/'.$parseddata['FILE'];
		
		if ( ($data = $this->_servespecial($parseddata)) !== false ) {
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
	
	private function _servespecial($data) {
		if ( $data['DIR'] != 'api' && array_search(strtolower($data['FILE']), array('getbots', 'boot', 'shutdown', 'getsockets', 'socketshutdown', 'shutdownbot', 'checkconsole', 'consolepoll')) === false ) return false;
		
		switch ( strtolower($data['FILE']) ) {
			// Bots
			case 'getbots':
				$stuff = array();
				
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
				}
				
				return json_encode($stuff);
			break;
			
			case 'boot':
				if ( !isset($_GET['uuid']) )
					return 'ERROR:No UUID.';
				
				if ( Config::get_instance()->get('clients.'.$_GET['uuid'], false) === false )
					return 'ERROR:Unknown UUID.';
				
				try {
					Kernal::get_instance()->boot($_GET['uuid'], Config::get_instance()->get('clients.'.$_GET['uuid']));
					return 'OKAY:Booted';
				} catch ( KernalError $e ) {
					return 'ERROR:'.$e->getMessage();
				}
			break;
			
			case 'shutdown':
				if ( !isset($_GET['uuid']) )
					return 'ERROR:No UUID.';
				
				if ( Config::get_instance()->get('clients.'.$_GET['uuid'], false) === false )
					return 'ERROR:Unknown UUID.';
				
				try {
					Kernal::get_instance()->shutdown($_GET['uuid']);
					return 'OKAY:Shutdown';
				} catch ( KernalError $e ) {
					return 'ERROR:'.$e->getMessage();
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
						'callback' => $callback
					);
				}
				
				return json_encode($rtn);
			break;
			
			case 'socketshutdown':
				if ( !isset($_GET['sid']) )
					return 'ERROR:No Socket ID.';
				
				if ( Socket::get_instance()->get_data($_GET['sid']) === false )
					return 'ERROR:Unknown UUID.';
				
				Socket::get_instance()->close($_GET['sid']);
				return 'OKAY:Shutdown';
			break;
			
			//// Shutdown bot
			case 'shutdownbot':
				Kernal::get_instance()->stop_loop();
			break;
			
			// Console
			case 'checkconsole':
				if ( ($logger = Config::get_instance()->get('logger.default', null)) != 'STDOUT_HTTP' ) {
					return 'ERROR:Error - Your default logger must be "STDOUT_HTTP", instead of what it is currently ('.$logger.')';
				}
				
				return 'OKAY:Proceed';
			break;
			
			case 'consolepoll':
				$rtn = implode("\n", $this->_log);
				
				return implode("\n", $this->_log);
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
