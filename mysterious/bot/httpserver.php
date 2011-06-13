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
##  [*] Last edit: 6/13/2011                          ##
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
	private $_timerid = false;
	
	public function setup() {
		if ( $this->_timerid === false ) {
			$this->_timerid = Timer::register(30, array($this, 'cleanup'));
		}
	}
	
	public function cleanup() {
		foreach ( $this->_clients AS $socketid => $info ) {
			if ( time()-30 >= $info['last_action'] ) {
				Socket::get_instance()->close($socketid);
			}
		}
	}
	
	public function new_connection($socketid) {
		if ( count($this->_clients) >= Config::get_instance()->get('httpserver.max_clients') ) {
			$this->generate_response($socketid, 503, 'text/html', 'Max clients '.Config::get_instance()->get('httpserver.max_clients').' reached!'.str_repeat('<!-- Padding -->'."\n",20));
		}
	}
	
	public function handle_read($socketid, $raw) {
		$parsed = $this->_parse($socketid, $raw);
		$this->serve($socketid, $parsed);
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
			$parsed['HTTP_'.$parsed['METHOD'].'_VARS'] = filter_input_array(constant('INPUT_'.$parsed['METHOD']), parse_str($tmp['query']));
		}
		
		// Parse the directory/file
		if ( empty($parsed['PATH']) )
			$parsed['PATH'] = 'index.php';
			
		$tmp = explode('/', $parsed['PATH']);
		$parsed['FILE'] = array_pop($tmp);
		$parsed['DIR'] = implode('/', $tmp);
		
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
			$method = '_'.$parsed['METHOD'];
			$_REQUEST = $$method = $parsed['HTTP_'.$parsed['METHOD'].'_VARS'];
		}
		
		unset($tmp);
		
		return $parsed;
	}
	
	private function serve($socketid, $parseddata) {
		$file = Config::get_instance()->get('httpserver.webroot').$parseddata['URI'];
		
		if ( file_exists($file) && is_file($file) ) {
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
	}
	
	private function code($num) {
		if ( !is_numeric($num) ) return $num;
		
		return isset($this->_codes[$num]) ? $this->_codes[$num] : '200 OK';
	}
}
