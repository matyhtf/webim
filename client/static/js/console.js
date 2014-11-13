
// Tell IE9 to use its built-in console
if (Function.prototype.bind && console && typeof console.log == "object") {
    ["log", "info", "warn", "error", "assert", "dir", "clear", "profile", "profileEnd"]
        .forEach(function (method) {
            console[method] = this.call(console[method], console);
        }, Function.prototype.bind);
}

// log() -- The complete, cross-browser (we don't judge!) console.log wrapper for his or her logging pleasure
if (!window.log) {

    window.log = function () {
        log.history = log.history || [];  // store logs to an array for reference
        log.history.push(arguments);

        // Modern browsers
        if (typeof console != 'undefined' && typeof console.log == 'function') {
            // Opera 11
            if (window.opera) {
                var i = 0;
                while (i < arguments.length) {
                    console.log("Item " + (i + 1) + ": " + arguments[i]);
                    i++;
                }
            }

            // All other modern browsers
            else if ((Array.prototype.slice.call(arguments)).length == 1 && typeof Array.prototype.slice.call(arguments)[0] == 'string') {
                console.log((Array.prototype.slice.call(arguments)).toString());
            }
            else {
                console.log(Array.prototype.slice.call(arguments));
            }
        }
        // IE8
        else if (!Function.prototype.bind && typeof console != 'undefined' && typeof console.log == 'object') {
            Function.prototype.call.call(console.log, console, Array.prototype.slice.call(arguments));
        }

        // IE7 and lower, and other old browsers
        else {
            // Inject Firebug lite
            if (!document.getElementById('firebug-lite')) {
                // Include the script
                var script = document.createElement('script');
                script.type = "text/javascript";
                script.id = 'firebug-lite';
                // If you run the script locally, point to /path/to/firebug-lite/build/firebug-lite.js
                script.src = 'https://getfirebug.com/firebug-lite.js';

                // If you want to expand the console window by default, uncomment this line
                //document.getElementsByTagName('HTML')[0].setAttribute('debug','true');
                document.getElementsByTagName('HEAD')[0].appendChild(script);

                setTimeout(function () {
                    log(Array.prototype.slice.call(arguments));
                }, 2000);

            }
            else {
                // FBL was included but it hasn't finished loading yet, so try again momentarily
                setTimeout(function () {
                    log(Array.prototype.slice.call(arguments));
                }, 500);

            }
        }
    }
}