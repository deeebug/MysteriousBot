<?php
## ################################################## ##
##                  MysteriousBot                     ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: style.php                          ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/17/2011                            ##
##  [*] Last edit: 6/17/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

class Style {
	public static $_tags = array(
		'[BLACK]'     => 'BLACK',
		'[WHITE]'     => 'WHITE',
		'[BLUE]'      => 'BLUE',
		'[GREEN]'     => 'GREEN',
		'[RED]'       => 'RED',
		'[BROWN]'     => 'BROWN',
		'[PURPLE]'    => 'PURPLE',
		'[ORANGE]'    => 'ORANGE',
		'[YELLOW]'    => 'YELLOW',
		'[LIMEGREEN]' => 'LIMEGREEN',
		'[TURQUISE]'  => 'TURQUISE',
		'[CYAN]'      => 'CYAN',
		'[LIGHTBLUE]' => 'LIGHTBLUE',
		'[PINK]'      => 'PINK',
		'[GREY]'      => 'GREY',
		'[LIGHTGREY]' => 'LIGHTGREY',
		'[B]'         => 'BOLD',
		'[BOLD]'      => 'BOLD',
		'[U]'         => 'UNDERLINE',
		'[UNDERLINE]' => 'UNDERLINE',
		'[NOCOLOR]'   => 'NOCOLOR',
		'[NC]'        => 'NOCOLOR',
	);
	
	const BLACK     = 0;
	const WHITE     = 1;
	const BLUE      = 2;
	const GREEN     = 3;
	const RED       = 4;
	const BROWN     = 5;
	const PURPLE    = 6;
	const ORANGE    = 7;
	const YELLOW    = 8;
	const LIMEGREEN = 9;
	const TURQUISE  = 10;
	const CYAN      = 11;
	const LIGHTBLUE = 12;
	const PINK      = 13;
	const GREY      = 14;
	const LIGHTGREY = 15;
	
	const BOLD      = 2;
	const UNDERLINE = 31;
	const NOCOLOR   = 3;
	
	public static function __initialize() {
		$r = new \ReflectionClass('Mysterious\Bot\IRC\Style'); 
		
		foreach ( self::$_tags AS $k => $v ) {
			if ( !in_array($v, array('BOLD', 'UNDERLINE', 'NOCOLOR')) )
				self::$_tags[$k] = chr(3).$r->getConstant($v);
			else
				self::$_tags[$k] = chr($r->getConstant($v));
		}
		
		unset($r);
	}
	
	public static function format($input, $append='', $prepend='') {
		return str_replace(array_keys(self::$_tags), array_values(self::$_tags), $append.$input.$prepend);
	}
}
