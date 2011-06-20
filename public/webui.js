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
##  [?] File name: webui.js                           ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/19/2011                            ##
##  [*] Last edit: 6/19/2011                          ##
## ################################################## ##

defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');
?>
// ==ClosureCompiler==
// @compilation_level ADVANCED_OPTIMIZATIONS
// @output_file_name webui.min.js
// @externs_url http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.js
// ==/ClosureCompiler==

$(document).ready(function() {
	$('.notification').hide();
	$('#sockets-list').hide();
	$('#console-output').hide();
	$('#shutdown').hide();
	
	MysteriousBot.init();
});

var MysteriousBot = {
	intervalid: 0,
	key: '<?=$authhash?>',
	
	init: function() {
		$('#botlist-view').click(function() {
			MysteriousBot.tab('bots-list');
			MysteriousBot.botlist();
			return false;
		});
		
		$('#socket-view').click(function() {
			MysteriousBot.tab('sockets-list');
			MysteriousBot.socketlist();
			return false;
		});
		
		$('#console-view').click(function() {
			MysteriousBot.tab('console-output');
			MysteriousBot.console();
			return false;
		});
		
		$('#shutdown-bot').click(function() {
			confirmed = confirm('Are you sure you want to shutdown MysteriousBot?');
			
			if ( confirmed ) {
				$('#time').html(new Date().getHours()+':'+new Date().getMinutes());
				MysteriousBot.tab('shutdown');
				
				$.get('api/shutdownbot');
				
				$('#botlist-view').hide();
				$('#socket-view').hide();
				$('#console-view').hide();
				$('#shutdown-bot').hide();
			}
			
			return false;
		});
		
		$('#plugin-load').click(function() {
			var values = new Array();
			$.each($("input[class='plugin-checkbox-affected']:checked"), function() {
				values.push($(this).val());
			});
			
			$.get('api/loadplugin', {auth: MysteriousBot.key, plugin: $('#plugin-select').val(), affect: values.join(',')}, function(data) {
				data = data.split('::');
				
				if ( data[0] == 'ERROR' ) {
					alert('Error loading: '+data[1]);
				}
				
				MysteriousBot.key = data[2];
			});
			
			$('#plugin-select').val('');
			$.each($("input[class='plugin-checkbox-affected']:checked"), function() {
				$(this).removeAttr('checked');
			});
			return false;
		});
		
		MysteriousBot.botlist();
	},
	
	botlist: function() {
		$.get('api/getbots', {auth: MysteriousBot.key}, function(data) {
			jsondata = json_decode(data);
			$('#botlist').html('<tr id="botlist-header-noremove">'+$('#botlist-header-noremove').html()+'</tr>');
			$('#plugin-checkbox').html('');
			
			MysteriousBot.key = jsondata['auth'];
			delete jsondata['auth'];
			
			$.each(jsondata, function(key, val) {
				if ( val['status'] == true ) {
					status = '<span class="connected">Connected</span>';
					uri = '<a href="#" class="bot-shutdown" bot="'+val['uuid']+'">Shutdown</a>';
				} else {
					status = '<span class="disconnected">Disconnected</span>';
					uri = '<a href="#" class="bot-boot" bot="'+val['uuid']+'">Boot</a>';
				}
				
				finishdata = '<tr><td>'+status+'</td><td>'+val['uuid']+'</td><td>'+val['server']+':'+val['port']+'</td><td>'+val['nick']+'</td><td>'+val['ident']+'</td><td>'+val['name']+'</td><td>'+val['type']+'</td><td>'+val['channels']+'</td><td>'+uri+'</td></tr>';
				
				$('#botlist').append(finishdata);
				
				if ( val['is_server'] == true )
					type = 'server';
				else
					type = 'client';
				
				if ( val['status'] != true) return;
				
				$('#plugin-checkbox').append('<input type="checkbox" class="plugin-checkbox-affected" name="affected[]" value="'+type+'-'+val['uuid']+'" /> '+val['uuid']);
				
				if ( val['is_server'] == true && val['children'] !== undefined ) {
					$('#plugin-checkbox').append(' (Affects ALL Server-Clients)');
					$.each(val['children'], function(key, data) {
						finishdata = '<tr><td></td><td>'+data['uuid']+'</td><td>'+data['server']+':'+data['port']+'</td><td>'+data['nick']+'</td><td>'+data['ident']+'</td><td>'+data['name']+'</td><td>'+data['type']+'</td><td>'+data['channels']+'</td><td>N/A</td></tr>';
						
						$('#botlist').append(finishdata);
						$('#plugin-checkbox').append('<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" class="plugin-checkbox-affected" name="affected[]" value="'+val['uuid']+'-'+data['uuid']+'" /> '+data['uuid']);
					});
				}
				
				$('#plugin-checkbox').append('<br />');
			});
			
			MysteriousBot.register();
		});
		
		$.get('api/loadedplugins', {auth: MysteriousBot.key}, function(data) {
			$('#plugin-loaded-plugins').html('');
			jsondata = json_decode(data);
			
			MysteriousBot.key = jsondata['key'];
			delete jsondata['key'];
			
			$.each(jsondata, function(k, v) {
				$('#plugin-loaded-plugins').append('<li>'+v+'</li>');
			});
		});
	},
	
	socketlist: function() {
		$.get('api/getsockets', {auth: MysteriousBot.key}, function(data) {
			jsondata = json_decode(data);
			$('.sockets').remove();
			
			MysteriousBot.key = jsondata['auth'];
			delete jsondata['auth'];
			
			$.each(jsondata, function(key, val) {
				disconnectlink = '<a href="#" class="socket-disconnect" sid="'+val['id']+'">Disconnect</a>';
				finishdata = '<tr class="sockets"><td>'+val['id']+'</td><td>'+val['type']+'</td><td>'+val['host']+'</td><td>'+val['port']+'</td><td>'+val['ssl']+'</td><td>'+val['record']+'</td><td>'+val['callback']+'</td><td>'+disconnectlink+'</td></tr>';
				$('#socketlist').append(finishdata);
			});
			
			$('.socket-disconnect').click(function() {
				$('.message').html('Disconnecting socket id '+$(this).attr('sid'));
				$('.notification').show();
				
				$.get('api/socketshutdown', {sid: $(this).attr('sid'), auth: MysteriousBot.key}, function(data) {
					data = data.split('::');
					
					if ( data[0] == 'ERROR' ) {
						alert('Error disconnecting: '+data[1]);
					}
					
					MysteriousBot.key = data[2];
					MysteriousBot.socketlist();
				});
				
				$('.notification').hide();
				return false;
			});
		});
	},
	
	register: function() {
		$('.bot-boot').unbind();
		$('.bot-shutdown').unbind();
		
		$('.bot-boot').click(function() {
			$('.message').html('Booting bot UUID '+$(this).attr('bot'));
			$('.notification').show();
			
			$.get('api/boot', {uuid: $(this).attr('bot'), auth: MysteriousBot.key}, function(data) {
				data = data.split('::');
				
				if ( data[0] == 'ERROR' ) {
					alert('Error booting: '+data[1]);
				}
				
				MysteriousBot.key = data[2];
				MysteriousBot.botlist();
			});
			
			$('.notification').hide();
			return false;
		});
		
		$('.bot-shutdown').click(function() {
			$('.message').html('Shutting down bot UUID '+$(this).attr('bot'));
			$('.notification').show();
			
			$.get('api/shutdown', {uuid: $(this).attr('bot'), auth: MysteriousBot.key}, function(data) {
				data = data.split('::');
				
				if ( data[0] == 'ERROR' ) {
					alert('Error shutting down: '+data[1]);
				}
				
				MysteriousBot.key = data[2];
				MysteriousBot.botlist();
			});
			
			$('.notification').hide();
			return false;
		});
	},
	
	console: function() {
		$('#console-output-append').html('Loading.....');
		
		$.get('api/checkconsole', {auth: MysteriousBot.key}, function(data) {
			data = data.split('::');
			
			if ( data[0] == 'ERROR' ) {
				$('#console-output-append').html(data[1]);
			}
			
			MysteriousBot.key = data[2];
		});
		
		// Check if theres errors
		if ( $('#console-output-append').html() != 'Loading.....')
			return false;
		
		$('#console-output-append').html('');
		
		MysteriousBot.updateconsole();
		MysteriousBot.intervalid = setInterval('MysteriousBot.updateconsole()', 5000 );
	},
	
	updateconsole: function() {
		$.get('api/consolepoll', {auth: MysteriousBot.key}, function(data) {
			jsondata = json_decode(data);
			
			MysteriousBot.key = jsondata['key'];
			delete jsondata['key'];
			
			finaldata = null;
			$.each(jsondata, function(k, v) {
				finaldata = finaldata+v+'<br />';
			});
			
			$('#console-output-append').html(finaldata);
			
			document.getElementById('console-terminal').scrollTop = document.getElementById('console-terminal').scrollHeight;
		});
	},
	
	tab: function(divid) {
		$('#bots-list').hide();
		$('#sockets-list').hide();
		$('#console-output').hide();
		$('#shutdown').hide();
		clearInterval(MysteriousBot.intervalid);
		
		$('#'+divid).show();
	}
};

function json_decode (str_json) {
    // http://kevin.vanzonneveld.net
    // +      original by: Public Domain (http://www.json.org/json2.js)
    // + reimplemented by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      improved by: T.J. Leahy
    // +      improved by: Michael White
    // *        example 1: json_decode('[\n    "e",\n    {\n    "pluribus": "unum"\n}\n]');
    // *        returns 1: ['e', {pluribus: 'unum'}]
/*
        http://www.JSON.org/json2.js
        2008-11-19
        Public Domain.
        NO WARRANTY EXPRESSED OR IMPLIED. USE AT YOUR OWN RISK.
        See http://www.JSON.org/js.html
    */

    var json = this.window.JSON;
    if (typeof json === 'object' && typeof json.parse === 'function') {
        try {
            return json.parse(str_json);
        } catch (err) {
            if (!(err instanceof SyntaxError)) {
                throw new Error('Unexpected error type in json_decode()');
            }
            this.php_js = this.php_js || {};
            this.php_js.last_error_json = 4; // usable by json_last_error()
            return null;
        }
    }

    var cx = /[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;
    var j;
    var text = str_json;

    // Parsing happens in four stages. In the first stage, we replace certain
    // Unicode characters with escape sequences. JavaScript handles many characters
    // incorrectly, either silently deleting them, or treating them as line endings.
    cx.lastIndex = 0;
    if (cx.test(text)) {
        text = text.replace(cx, function (a) {
            return '\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice(-4);
        });
    }

    // In the second stage, we run the text against regular expressions that look
    // for non-JSON patterns. We are especially concerned with '()' and 'new'
    // because they can cause invocation, and '=' because it can cause mutation.
    // But just to be safe, we want to reject all unexpected forms.
    // We split the second stage into 4 regexp operations in order to work around
    // crippling inefficiencies in IE's and Safari's regexp engines. First we
    // replace the JSON backslash pairs with '@' (a non-JSON character). Second, we
    // replace all simple value tokens with ']' characters. Third, we delete all
    // open brackets that follow a colon or comma or that begin the text. Finally,
    // we look to see that the remaining characters are only whitespace or ']' or
    // ',' or ':' or '{' or '}'. If that is so, then the text is safe for eval.
    if ((/^[\],:{}\s]*$/).
    test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g, '@').
    replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, ']').
    replace(/(?:^|:|,)(?:\s*\[)+/g, ''))) {

        // In the third stage we use the eval function to compile the text into a
        // JavaScript structure. The '{' operator is subject to a syntactic ambiguity
        // in JavaScript: it can begin a block or an object literal. We wrap the text
        // in parens to eliminate the ambiguity.
        j = eval('(' + text + ')');

        return j;
    }

    this.php_js = this.php_js || {};
    this.php_js.last_error_json = 4; // usable by json_last_error()
    return null;
}

function print_r (array, return_val) {
    // http://kevin.vanzonneveld.net
    // +   original by: Michael White (http://getsprink.com)
    // +   improved by: Ben Bryan
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +      improved by: Brett Zamir (http://brett-zamir.me)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // -    depends on: echo
    // *     example 1: print_r(1, true);
    // *     returns 1: 1
    var output = '',
        pad_char = ' ',
        pad_val = 4,
        d = this.window.document,
        getFuncName = function (fn) {
            var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn);
            if (!name) {
                return '(Anonymous)';
            }
            return name[1];
        },
        repeat_char = function (len, pad_char) {
            var str = '';
            for (var i = 0; i < len; i++) {
                str += pad_char;
            }
            return str;
        },
        formatArray = function (obj, cur_depth, pad_val, pad_char) {
            if (cur_depth > 0) {
                cur_depth++;
            }

            var base_pad = repeat_char(pad_val * cur_depth, pad_char);
            var thick_pad = repeat_char(pad_val * (cur_depth + 1), pad_char);
            var str = '';

            if (typeof obj === 'object' && obj !== null && obj.constructor && getFuncName(obj.constructor) !== 'PHPJS_Resource') {
                str += 'Array\n' + base_pad + '(\n';
                for (var key in obj) {
                    if (Object.prototype.toString.call(obj[key]) === '[object Array]') {
                        str += thick_pad + '[' + key + '] => ' + formatArray(obj[key], cur_depth + 1, pad_val, pad_char);
                    }
                    else {
                        str += thick_pad + '[' + key + '] => ' + obj[key] + '\n';
                    }
                }
                str += base_pad + ')\n';
            }
            else if (obj === null || obj === undefined) {
                str = '';
            }
            else { // for our "resource" class
                str = obj.toString();
            }

            return str;
        };

    output = formatArray(array, 0, pad_val, pad_char);

    if (return_val !== true) {
        if (d.body) {
            this.echo(output);
        }
        else {
            try {
                d = XULDocument; // We're in XUL, so appending as plain text won't work; trigger an error out of XUL
                this.echo('<pre xmlns="http://www.w3.org/1999/xhtml" style="white-space:pre;">' + output + '</pre>');
            } catch (e) {
                this.echo(output); // Outputting as plain text may work in some plain XML
            }
        }
        return true;
    }
    return output;
}
