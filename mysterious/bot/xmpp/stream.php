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
##  [?] File name: stream.php                         ##
##                                                    ##
##  [@] Notes: Very very messy file! I'll rewrite,    ##
## but the basic functions are there, and thats what  ##
## counts, right?                                     ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/2/2011                             ##
##  [*] Last edit: 6/3/2011                           ##
## ################################################## ##

namespace Mysterious\Bot\XMPP;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use DomDocument;
use Mysterious\Singleton;
use Mysterious\Bot\Config;
use Mysterious\Bot\Socket;
use Mysterious\Bot\XMPPError;

class Stream extends Singleton {
	// Namespaces
	const NS_CLIENT = 'jabber:client';
	const NS_SERVER = 'jabber:server';
	const NS_AUTH = 'jabber:iq:auth';
	const NS_REGISTER = 'jabber:iq:register';
	const NS_ROSTER = 'jabber:iq:roster';
	const NS_OFFLINE = 'jabber:x:offline';
	const NS_AGENT = 'jabber:iq:agent';
	const NS_AGENTS = 'jabber:iq:agents';
	const NS_DELAY = 'jabber:x:delay';
	const NS_VERSION = 'jabber:iq:version';
	const NS_TIME = 'jabber:iq:time';
	const NS_VCARD = 'vcard-temp';
	const NS_PRIVATE = 'jabber:iq:private';
	const NS_SEARCH = 'jabber:iq:search';
	const NS_OOB = 'jabber:iq:oob';
	const NS_XOOB = 'jabber:x:oob';
	const NS_ADMIN = 'jabber:iq:admin';
	const NS_FILTER = 'jabber:iq:filter';
	const NS_AUTH_0K = 'jabber:iq:auth:0k';
	const NS_BROWSE = 'jabber:iq:browse';
	const NS_EVENT = 'jabber:x:event';
	const NS_CONFERENCE = 'jabber:iq:conference';
	const NS_SIGNED = 'jabber:x:signed';
	const NS_ENCRYPTED = 'jabber:x:encrypted';
	const NS_GATEWAY = 'jabber:iq:gateway';
	const NS_LAST = 'jabber:iq:last';
	const NS_ENVELOPE = 'jabber:x:envelope';
	const NS_EXPIRE = 'jabber:x:expire';
	const NS_XHTML = 'http://www.w3.org/1999/xhtml';
	const NS_XDBGINSERT = 'jabber:xdb:ginsert';
	const NS_XDBNSLIST = 'jabber:xdb:nslist';
	const NS_TLS = 'urn:ietf:params:xml:ns:xmpp-tls';
	const NS_STREAM = 'http://etherx.jabber.org/streams';
	const NS_SASL = 'urn:ietf:params:xml:ns:xmpp-sasl';
	const NS_BIND = 'urn:ietf:params:xml:ns:xmpp-bind';
	const NS_SESSION = 'urn:ietf:params:xml:ns:xmpp-session';
	
	// Mechanisms
	const MECHANISM_PLAIN = 'PLAIN';
	const MECHANISM_DIGEST_MD5 = 'DIGEST-MD5';
	
	// Statuses (Internal)
	const S_WAITINGFOR_FEATURES_MECH = 0x001;
	const S_STARTING_TLS             = 0x002;
	const S_TLS_NEGOTIATION          = 0x003;
	const S_SENTAUTH                 = 0x004;
	const S_SENTPRESENCE             = 0x005;
	const S_BINDING                  = 0x006;
	const S_STARTSESSION             = 0x007;
	const S_REQUESTING_ROSTER        = 0x008;
	const S_LISTENING                = 0x009;
	
	public $roster = array();
	
	private $clientStreamStart = "<stream:stream to='%s' xmlns='%s' xmlns:stream='%s' version='1.0'>";
	private $serverStreamStart = '<stream:stream xmlns="%s" xmlns:stream="%s" from="%s" version="1.0">';
	private $features = array();
	private $mechanisms = array();
	
	private $status;
	private $_config         = array();
	private $_readstream     = '';
	private $tls_enabled     = false;
	private $authenticated   = false;
	private $binded          = false;
	private $session_started = false;
	
	protected $response   = null;
	protected $socketid   = null;
	
	public function setup($socketid) {
		$this->_config = Config::get_instance()->get('xmpp');
		$this->_config['full_jid'] = sprintf('%s@%s/%s', $this->_config['username'], $this->_config['domain'], $this->_config['resource']);
		$this->_config['bare_jid'] = sprintf('%s@%s', $this->_config['username'], $this->_config['domain']);
		
		$this->socketid = $socketid;
		
		$this->clientStreamStart = sprintf($this->clientStreamStart, $this->_config['domain'], self::NS_CLIENT, self::NS_STREAM);
		$this->serverStreamStart = sprintf($this->serverStreamStart, self::NS_CLIENT, self::NS_STREAM, $this->_config['domain']);
		
		// Send connect
		$this->status = self::S_WAITINGFOR_FEATURES_MECH;
		$out = array();
		$out[] = "<?xml version='1.0' ?>";
		$out[] = $this->clientStreamStart;
		
		$this->write($out);
	}
	public function write($raw) {
		Socket::get_instance()->write($this->socketid, $raw);
	}
	
	public function handle($raw) {
		switch ( $this->status ) {
			case self::S_WAITINGFOR_FEATURES_MECH:
				if ( empty($this->_readstream) ) {
					$this->_readstream = $raw;
					return;
				} else {
					$raw = $this->_readstream.$raw;
					$this->_readstream = null;
				}
				
				if ( preg_match('/<stream:error>/', $raw) )
					throw new XMPPError('Recieved stream error from XMPP Server - Raw: '.$raw);
				
				$this->clientStream = $this->serverStream = new DomDocument;
				
				$this->clientStream->loadXML("<?xml version='1.0' ?>\n{$this->clientStreamStart}\n</stream:stream>");
				$this->serverStream->loadXML($raw . "</stream:stream>");
				
				// Get features list
				$this->features = array();
				$this->mechanisms = array();
				if ( $features = $this->serverStream->getElementsByTagName('features')->item(0) ) {
					foreach ( $features->childNodes as $feature ) {
						$featureInfo['tag'] = $feature->tagName;
						$featureInfo['namespace'] = $feature->namespaceURI;
						$featureInfo['required'] = $feature->getElementsByTagName('required')->item(0) ? true : false;
						
						$this->features[$feature->tagName] = $featureInfo;
					}
					
					// Get authentication mechanisms
					foreach ( $features->getElementsByTagName('mechanism') as $mechanism ) {
						$this->mechanisms[$mechanism->nodeValue] = $mechanism->nodeValue;
					}
				}
				
				if ( isset($this->features['starttls']) && $this->tls_enabled == false ) {
					$this->write("<starttls xmlns='".self::NS_TLS."' />");
					$this->status = self::S_TLS_NEGOTIATION;
					return;
				}
				
				// Auth to the server!
				if ( $this->tls_enabled == true && $this->authenticated === false ) {
					$dom = new DomDocument;
					$auth = $dom->createElement('auth', base64_encode('' . chr(0) . $this->_config['username'] . chr(0) . $this->_config['password']));
					$auth->setAttribute('mechanism', self::MECHANISM_PLAIN);
					$auth->setAttribute('xmlns', self::NS_SASL);
					$dom->appendChild($auth);
					$this->write($dom->saveXML($dom->firstChild));
					
					$this->authenticated = true;
					$this->status = self::S_SENTAUTH;
					return;
				}
				
				// We're binding.
				if ( $this->authenticated === true && $this->session_started === false && $this->binded === false ) {
					if ( isset($this->features['bind']) ) {
						$dom = new DomDocument;
						
						$this->_config['id'] = uniqid();
						
						$iqNode = $dom->createElement('iq');
						$iqNode->setAttribute('id', $this->_config['id']);
						$iqNode->setAttribute('type', 'set');
						
						$dom->appendChild($iqNode);
						
						$bind = $dom->createElement('bind');
						$bind->setAttribute('xmlns', self::NS_BIND);
						
						$resourceNode = $dom->createElement('resource', $this->_config['resource']);
						
						$bind->appendChild($resourceNode);
						$iqNode->appendChild($bind);
						
						$dom->appendChild($iqNode);
						$this->write($dom->saveXML($dom->firstChild));
						
						$this->authenticated = true;
						$this->status = self::S_BINDING;
						return;
					}
				}
			break;
			
			
			case self::S_TLS_NEGOTIATION:
				Socket::get_instance()->enable_crypto($this->socketid, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				$this->tls_enabled = true;
			
			case self::S_SENTAUTH:
				$this->status = self::S_WAITINGFOR_FEATURES_MECH;
				
				$out = array();
				$out[] = "<?xml version='1.0' ?>";
				$out[] = $this->clientStreamStart;
			
				$this->write($out);
			break;
			
			case self::S_BINDING:
				// Let's get our full JID first
				$xml = @simplexml_load_string($raw, 'SimpleXMLElement', ~LIBXML_DTDVALID);
				$this->_config['full_jid'] = $xml->bind->jid;
				
				$dom = new DomDocument;
				
				$this->_config['id2'] = uniqid();
				
				$iqNode = $dom->createElement('iq');
				$iqNode->setAttribute('id', $this->_config['id2']);
				$iqNode->setAttribute('type', 'set');
				
				$session = $dom->createElement('session');
				$session->setAttribute('xmlns', self::NS_SESSION);
				$iqNode->appendChild($session);
				
				$dom->appendChild($iqNode);
				$this->write($dom->saveXML($dom->firstChild));
				
				$this->session_start = true;
				$this->status = self::S_STARTSESSION;
			break;
			
			case self::S_STARTSESSION:
				$dom = new DomDocument;
				
				$iqNode = $dom->createElement('iq');
				$iqNode->setAttribute('id', 'roster_1');
				$iqNode->setAttribute('type', 'get');
				$iqNode->setAttribute('from', $this->_config['full_jid']);
				
				$queryNode = $dom->createElement('query');
				$queryNode->setAttribute('xmlns', self::NS_ROSTER);
				$iqNode->appendChild($queryNode);
				
				$dom->appendChild($iqNode);
				$this->write($dom->saveXML($dom->firstChild));
				
				$this->status = self::S_REQUESTING_ROSTER;
			break;
			
			case self::S_REQUESTING_ROSTER:
				// here we get the roster
				$rawfixed = preg_replace('/\s(xmlns=(?:"|\')[^"\']*(?:"|\'))/', '', $raw);
				$xml = @simplexml_load_string('<xml>'.$rawfixed.'</xml>', 'SimpleXMLElement', ~LIBXML_DTDVALID);
				$result = $xml->xpath("//iq[@type='result']/query");
				if ( $result ) {
					foreach ( $result[0]->children() AS $contact ) {
						$attrs = $contact->attributes();
						$this->roster[strval($attrs->jid)] = array(
							'subscription' => strval($attrs->subscription),
							'name' => isset($attrs->name) ? strval($attrs->name) : null
						);
					}
				}
				
				// Now we tell the WHOLEEE world we're online!
				$dom = new DomDocument;
				
				$presence = $dom->createElement('presence');
				
				$show = $dom->createElement('show', 'chat');
				$presence->appendChild($show);
				
				$status = $dom->createElement('status', 'I am online!');
				$presence->appendChild($status);
				
				$priority = $dom->createElement('priority', 25);
				$presence->appendChild($priority);
				
				$dom->appendChild($presence);
				$this->write($dom->saveXML($dom->firstChild));
				
				$this->status = self::S_LISTENING;
			break;
			
			case self::S_LISTENING:
				if ( empty($raw) ) return;
				
				$rawfixed = preg_replace('/\s(xmlns=(?:"|\')[^"\']*(?:"|\'))/', '', $raw);
				$xml = @simplexml_load_string('<xml>'.$rawfixed.'</xml>', 'SimpleXMLElement', ~LIBXML_DTDVALID);
				
				foreach ( $xml->xpath("//message") AS $message ) {
					$attrs = $message->attributes();
					$children = $message->children();
					$body = $children->body;
					if ( !empty($body) ) {
						echo '**NEW MESSAGE** From: '.strval($attrs->from).' || Type: '.strval($attrs->type).' || Body: '.strval($body)."\n";
						continue;
					}
					if ( isset($children->composing) )
						echo strval($attrs->from).' has started writing!'."\n";
					else if ( isset($children->paused) )
						echo strval($attrs->from).' has stopped writing!'."\n";
				}
			break;
		}
	}
}

