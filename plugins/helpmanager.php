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
##  [?] File name: helpmanager.php                    ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 6/1/2011                           ##
## ################################################## ##

namespace Plugins;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Kernal;
use Mysterious\Bot\Message;
use Mysterious\Bot\Plugin;
use Mysterious\Bot\IRC\Style;

class HelpManager extends Plugin {
	private $__tpl_welcome = '%s is bot that is running MysteriousBot';
	private $__tpl_page = 'Page %d of %d.  To see the next page, type [U]%shelp %d';
	
	public function __initialize() {
		// Privmsg, but as a PM
		$this->register_event('irc.privmsg.private', 'help', 'cmd_private_help');
		
		// Privmsg, but in channel
		$this->register_event('irc.privmsg.channel', '!help', 'cmd_channel_help');
	}
	
	public function cmd_private_help() {
		// First lets get the help.
		$docs = Kernal::get_instance()->get_help();
		
		// Are we doing another page?
		if ( Message::args() !== null && count(Message::args()) == 1 && is_numeric(Message::args(0)) ) {
			$page = Message::args(0);
		} else {
			$page = 1;
		}
		
		if ( count($docs) > 20 ) {
			// Find out how many pages there will be
			$pages = ceil(count($docs)/20);
			if ( $page > $pages )
				$page = 1;
			
			array_splice($docs, ($page*20));
			$docs[] = sprintf($this->__tpl_page, $page, $pages, '', $page+1);
		}
		
		$out = array();
		$out[] = sprintf($this->__tpl_welcome, $this->config('nick'));
		
		foreach ( $docs AS $cmd => $doc )
			$out[] = Style::format($cmd, '[B]', '[B]').' - '.$doc;
		
		$this->privmsg(Message::nick(), $out);
	}
	
	public function cmd_channel_help() {
		// First lets get the help.
		$docs = Kernal::get_instance()->get_help(true);
		
		// Are we doing another page?
		if ( Message::args() !== null && count(Message::args()) == 1 && is_numeric(Message::args(0)) ) {
			$page = Message::args(0);
		} else {
			$page = 1;
		}
		
		if ( count($docs) > 20 ) {
			// Find out how many pages there will be
			$pages = ceil(count($docs)/20);
			if ( $page > $pages )
				$page = 1;
			
			array_splice($docs, ($page*20));
			$docs[] = sprintf($this->__tpl_page, $page, $pages, '', $page+1);
		}
		
		$out = array();
		$out[] = sprintf($this->__tpl_welcome, $this->config('nick'));
		
		foreach ( $docs AS $cmd => $doc )
			$out[] = Style::format($cmd, '[B]', '[B]').' - '.$doc;
		
		$this->privmsg(Message::channel(), $out);
	}
}
