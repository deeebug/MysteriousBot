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
##  [?] File name: socket_test.php                    ##
##  [?] File description: An example of how you would ##
## connect to your Socket Server ran by MysteriousBot ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/29/2011                            ##
##  [*] Last edit: 5/29/2011                          ##
## ################################################## ##
define('Y_SO_MYSTERIOUS', true);

## ################################################## ##
##                 Please edit below                  ##
## ################################################## ##

// The IP where your Socket Server for MysteriousBot is running
// *usually* its 127.0.0.1, aka localhost (the same computer)
$api_ip = '127.0.0.1';

// The Port that you set for your Socket Server for MysteriousBot
// to be. Default is 7363, so don't change unless you edited it
$api_port = 7363;

// The Password that you set for your Socket Server for MysteriousBot
// Default is d3atht0y0u, don't change unless you edited it.
$api_pass = 'd3atht0y0u';

## ################################################## ##
##                   STOP EDITING!                    ##
##  Continue editing where it says "case 'CONNECTED'" ##
## ################################################## ##

$fp = fsockopen($api_ip, $api_port);
$active = true;
$sent = false;

while ( $active === true && ($data = trim(fgets($fp))) !== false ) {
	echo '[IN] '.$data."\n";
	$parts = explode(' ', $data);
	
	switch ( $parts[0] ) {
		case 'CHALLENGE':
			if ( $sent === false ) {
				$time = time();
				$salt = md5(time().rand().uniqid().time());
				$resp = sha1($time.$api_pass.$parts[1].$salt).'-'.$salt.'-'.$time;
				$sent = true;
			} else {
				echo "\nChallenge is wrong. Script error?";
				fclose($fp);
				break 2;
			}
		break;
		
		case 'CONNECTED':
			//$resp = 'PRIVMSG mysteriousbot001 #opers Hello, from the socket server!';
			//$resp = 'RAW mysteriousbot001 PART #opers';
			$resp = 'GET_CHANNELS_OBJECT mysteriousbot002';
			
		break;
		
		case 'RESPONSE':
			$tmp = $parts;
			array_shift($tmp);
			$response = array_shift($tmp);
			$checksum = array_shift($tmp);
			$data = implode(' ', $tmp);
			
			echo "Response! - ". $response."\n\n========================================\n";
			
			if ( sha1($data) != $checksum )
				echo "Fatal error - Checksum failed!";
			else
				print_r(unserialize($data));
			
			$resp = 'LOGOUT';
			$active = false;
		break;
		
		default:
		case 'ERROR':
		case 'OKAY':
			$resp = 'LOGOUT';
			$active = false;
		break;
	}
	
	echo '[OUT] '.$resp."\n";
	fwrite($fp, $resp, strlen($resp));
}

echo "\nSocket closed. Script execution has finished.\n";
