/*
## ################################################## ##
##                   MysteriousBot                    ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##                                                    ##
##  [?] File name: webui.js                           ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 6/12/2011                            ##
##  [*] Last edit: 6/12/2011                          ##
## ################################################## ##
*/

$(document).ready(function() {
	$('.notification').hide();
	$('#sockets-list').hide();
	$('#console-output').hide();
	$('#shutdown').hide();
	
	MysteriousBot.init();
});

var MysteriousBot = {
	intervalid: 0,
	
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
		
		this.botlist();
	},
	
	botlist: function() {
		$.get('api/getbots', function(data) {
			jsondata = json_decode(data);
			
			$.each(jsondata, function(key, val) {
				if ( val['status'] == true ) {
					status = '<span class="connected">Connected</span>';
					uri = '<a href="#" class="bot-shutdown" bot="'+val['uuid']+'">Shutdown</a>';
				} else {
					status = '<span class="disconnected">Disconnected</span>';
					uri = '<a href="#" class="bot-boot" bot="'+val['uuid']+'">Boot</a>';
				}
				
				finishdata = '<td>'+status+'</td><td>'+val['uuid']+'</td><td>'+val['server']+':'+val['port']+'</td><td>'+val['nick']+'</td><td>'+val['ident']+'</td><td>'+val['name']+'</td><td>'+val['type']+'</td><td>'+val['channels']+'</td><td>'+uri+'</td>';
				
				if ( $('#'+val['uuid']).length > 0 ) {
					$('#'+val['uuid']).html(finishdata);
				} else {
					$('#botlist').append('<tr id="'+val['uuid']+'">'+finishdata+'</tr>');
				}
			});
			
			MysteriousBot.register();
		});
	},
	
	socketlist: function() {
		$.get('api/getsockets', function(data) {
			jsondata = json_decode(data);
			$('.sockets').remove();
			
			$.each(jsondata, function(key, val) {				
				disconnectlink = '<a href="#" class="socket-disconnect" sid="'+val['id']+'">Disconnect</a>';
				finishdata = '<tr class="sockets"><td>'+val['id']+'</td><td>'+val['type']+'</td><td>'+val['host']+'</td><td>'+val['port']+'</td><td>'+val['ssl']+'</td><td>'+val['callback']+'</td><td>'+disconnectlink+'</td></tr>';
				$('#socketlist').append(finishdata);
			});
			
			$('.socket-disconnect').click(function() {
				$('.message').html('Disconnecting socket id '+$(this).attr('sid'));
				$('.notification').show();
				
				$.get('api/socketshutdown', {sid: $(this).attr('sid')}, function(data) {
					data = data.split(':');
					
					if ( data[0] == 'ERROR' ) {
						alert('Error disconnecting: '+data[1]);
					}
					
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
			
			$.get('api/boot', {uuid: $(this).attr('bot')}, function(data) {
				data = data.split(':');
				
				if ( data[0] == 'ERROR' ) {
					alert('Error booting: '+data[1]);
				}
				
				MysteriousBot.init();
			});
			
			$('.notification').hide();
			return false;
		});
		
		$('.bot-shutdown').click(function() {
			$('.message').html('Shutting down bot UUID '+$(this).attr('bot'));
			$('.notification').show();
			
			$.get('api/shutdown', {uuid: $(this).attr('bot')}, function(data) {
				data = data.split(':');
				
				if ( data[0] == 'ERROR' ) {
					alert('Error shutting down: '+data[1]);
				}
				
				MysteriousBot.init();
			});
			
			$('.notification').hide();
			return false;
		});
	},
	
	console: function() {
		$('#console-output-append').html('Loading.....');
		
		$.get('api/checkconsole', function(data) {
			data = data.split(':');
			
			if ( data[0] == 'ERROR' ) {
				$('#console-output-append').html(data[1]);
			}
		});
		
		// Check if theres errors
		if ( $('#console-output-append').html() != 'Loading.....')
			return false;
		
		$('#console-output-append').html('');
		
		this.updateconsole();
		this.intervalid = setInterval('MysteriousBot.updateconsole()', 5000 );
	},
	
	updateconsole: function() {
		$.get('api/consolepoll', function(data) {
			$('#console-output-append').html(data);
			
			document.getElementById('console-terminal').scrollTop = document.getElementById('console-terminal').scrollHeight;
		});
	},
	
	tab: function(divid) {
		$('#bots-list').hide();
		$('#sockets-list').hide();
		$('#console-output').hide();
		$('#shutdown').hide();
		clearInterval(this.intervalid);
		
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
