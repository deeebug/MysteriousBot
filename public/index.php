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
##  [?] File name: index.php                          ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/16/2011                            ##
##  [*] Last edit: 6/16/2011                          ##
## ################################################## ##

defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');
?>
<html>
<head>
	<title>MysteriousBot :: WebUI</title>
	<style type="text/css">
		body{text-align:center;background:#eee;color:#222;font: 12pt "Lucida Grande", "Trebuchet MS", Verdana, sans-serif;}
		#title{position:fixed;top:0px;left:0px;width:100%;background:#ccc;font:75%;}
		#content{margin-top:100px;}
		
		.notification{display:block;position:fixed;top:0px;left:0px;color:#000000;background-color:#DDDDDD;padding:5px;z-index:10000}
		
		.connected{color: #008C00;}
		.disconnected{color: #CC0000;}
		td {text-align:center;}
		
		#botlist-view{background:white;color:gray;position:fixed;bottom:0px;right:0px;padding:14px;z-index:6;}
		#console-view{background:white;color:gray;position:fixed;bottom:0px;right:100px;padding:14px;z-index:6;}
		#socket-view{background:white;color:gray;position:fixed;bottom:0px;right:300px;padding:14px;z-index:6;}
		#shutdown-bot{background:white;color:gray;position:fixed;bottom:0px;right:525px;padding:14px;z-index:6;}
		
		/* Basic Terminal CSS - from http://wterminal.appspot.com/demo */
		.wterm_terminal { text-align:left; background: #000; color: #0f0; font-size: 1em; font-family: monospace; padding: 3px; width: 100%; height: 75%; display: block; overflow-x: none; overflow-y: auto;  }
		.full { height: 100%; }
		.wterm_terminal div:first { margin-bottom: 1em; }
	</style>
	
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js"></script>
	<script type="text/javascript" src="webui.js"></script>
</head>
<body>
<div id="title">
	<h1>Welcome to the MysteriousBot WebUI v1.0-ALPHA2</h1>
</div>

<div class="notification" style="display:none;"><p class="message">Loading</p></div>

<div id="content">
    <div id="bots-list">
		<h2>Bot List</h2>
		<table style="border-collapse:collapse;width:100%;" cellpadding="3" border="1" id="botlist">
			<tr>
				<td>STATUS</td>
				<td>UUID</td>
				<td>SERVER</td>
				<td>BOT NICK</td>
				<td>BOT IDENT</td>
				<td>BOT NAME</td>
				<td>TYPE</td>
				<td>CHANNELS</td>
				<td>CONTROL</td>
			</tr>
		</table>
    </div>
    
    <div id="sockets-list">
		<h2>Socket connections</h2>
		<table style="border-collapse:collapse;width:100%;" cellpadding="3" border="1" id="socketlist">
			<tr>
				<td>ID</td>
				<td>TYPE</td>
				<td>HOST</td>
				<td>PORT</td>
				<td>SSL</td>
				<td>CALLBACK</td>
				<td>DISCONNECT</td>
			</tr>
		</table>
	</div>
	
	<div id="console-output">
		<h2>Console Output</h2>
		<div class="wterm_terminal" id="console-terminal">
			<div>Welcome to MysteriousBot's console output</div>
			
			<p>[http@mysteriousbot]$ uname<br />
			MysteriousBot/HTTP <?=MYSTERIOUSBOT_VERSION?></p>
			
			<p>[http@mysteriousbot]$ ./bot console<br />
			Starting live updating console...<br />
			=========================================</p>
			
			<p><pre id="console-output-append">Loading.....</pre></p>
		</div>
	</div>
	
	<div id="shutdown">
		<div class="wterm_terminal full">
			<div>Thank you for using MysteriousBot!</div>
			
			<p>[http@mysteriousbot]$ shutdown now<br />
			Broadcast message from http@mysteriousbot (/dev/pts/1) at <span id="time">now</span> ...<br />
			<br />
			The system is going down for halt NOW!</p>
			
			<p id="console-output-append"><pre>
 _______ _                 _                               __                        _             
|__   __| |               | |                             / _|                      (_)            
   | |  | |__   __ _ _ __ | | __    _   _  ___  _   _    | |_ ___  _ __    _   _ ___ _ _ __   __ _ 
   | |  | '_ \ / _` | '_ \| |/ /   | | | |/ _ \| | | |   |  _/ _ \| '__|  | | | / __| | '_ \ / _` |
   | |  | | | | (_| | | | |   <    | |_| | (_) | |_| |   | || (_) | |     | |_| \__ \ | | | | (_| |
   |_|  |_| |_|\__,_|_| |_|_|\_\    \__, |\___/ \__,_|   |_| \___/|_|      \__,_|___/_|_| |_|\__, |
                                     __/ |                                                    __/ |
                                    |___/                                                    |___/ 
 __  __           _            _                 ____        _      _ 
|  \/  |         | |          (_)               |  _ \      | |    | |
| \  / |_   _ ___| |_ ___ _ __ _  ___  _   _ ___| |_) | ___ | |_   | |
| |\/| | | | / __| __/ _ \ '__| |/ _ \| | | / __|  _ < / _ \| __|  | |
| |  | | |_| \__ \ ||  __/ |  | | (_) | |_| \__ \ |_) | (_) | |_   |_|
|_|  |_|\__, |___/\__\___|_|  |_|\___/ \__,_|___/____/ \___/ \__/  (_)
         __/ |                                                     
        |___/                        Version <?=MYSTERIOUSBOT_VERSION?>
</pre></p>
		</div>
	</div>
</div>

<a id="botlist-view" href="#">Bot List</a>
<a id="console-view" href="#">View console output</a>
<a id="socket-view" href="#">View socket connections</a>
<a id="shutdown-bot" href="#">Shutdown bot</a>

</body>
</html>
