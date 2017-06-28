// create an object for handling the debugging using console wrappers
var mts = {};
mts.debugging = false;
mts.debug = {};


(function ($) {
    /**
     * Get all attributes for the given element if there are no arguments, or apply the arguments as typically handled.
     * @returns {*}
     */
    $.fn.attr = function () {
        if (arguments.length === 0) {
            if (this.length === 0) {
                return null;
            }

            var obj = {};
            $.each(this[0].attributes, function () {
                if (this.specified) {
                    obj[this.name] = this.value;
                }
            });
            return obj;
        }

        return $.apply(this, arguments);
    };


    /**
     * Get or set the contents of the specified iframe
     * @param content {string}
     * @returns string
     * @author Mike Rodarte
     */
    $.fn.iframeContents = function(content) {
        // be sure this is an iframe
        if (this[0].nodeName !== 'IFRAME') {
            return '';
        }

        if (content !== undefined && typeof content === 'string') {
            $($(this.contents()[0].body).contents().find('body').context).html(content);
        } else {
            return $($(this.contents()[0].body).contents().find('body').context).html();
        }
    };


    /**
     * Determine if an element is a child of a parent
     * Alias for jQuery.contains
     * @param parent string selector
     * @returns {*}
     */
    $.fn.isChildOf = function (parent) {
        return $.contains($(parent)[0], this[0]);
    };


    /**
     * Center an element according to the window, body, and element size.
     *
     * @author Mike Rodarte
     */
    $.fn.centerBox = function () {
        var $selector = this;
        if ($selector.length < 1) {
            return false;
        }

        // get widths of containers
        var windowWidth = $(window).width();
        var bodyWidth = $('body').width();
        var boxWidth = $selector.width();

        // calculate widths and positions
        var centeredLeft = (bodyWidth - boxWidth) / 2;
        var bodyMargin = windowWidth - bodyWidth;
        var left = bodyMargin / 2 + centeredLeft;

        // apply the left value to the selector
        $selector.css({
            left: Math.floor(left)
        });
    };


    /**
     * Get line information for the caller (or whichever element is specified)
     * @returns {*}
     */
    mts.debug.lineInfo = function () {
        var e = new Error();
        if (!e.stack) {
            return {};
        }

        // this stack element would be 0, so its caller would be 1, which is typically going to be debug functions
        // 2 should be default to get the caller of the debug function that called this one
        var numBack = 2;
        if (arguments[0] !== undefined) {
            numBack = parseInt(arguments[0]);
        }

        // split the stack
        var stack = e.stack.toString().split(/\r\n|\n/);

        // use default value in case provided argument is bad
        if (!stack.hasOwnProperty(numBack) && stack.hasOwnProperty(2)) {
            numBack = 2;
        }

        // get the last function data
        var lastFunc = stack[numBack];
        // stack lines have a specific format we need to use to extract data
        var pattern = /(.+)@(.+):([\d]+):([\d]+)/;
        // get the matches from the stack line
        var matches = pattern.exec(lastFunc);

        // make the values easier for people to read
        return {
            function: matches[1],
            file: matches[2],
            line: matches[3],
            column: matches[4]
        };
    };


    /**
     * Print a trace from a given object
     * @param lineInfo
     */
    mts.debug.printCaller = function(lineInfo) {
        if (mts.debugging && typeof console.info === 'function') {
            if (lineInfo === undefined) {
                lineInfo = mts.debug.lineInfo(3);
            }
            console.info(lineInfo.file + ' called ' + lineInfo.function + ' on line ' + lineInfo.line);
        }
    };


    /**
     * Alias for console.log that only displays if debugging is enabled
     */
    mts.debug.log = function() {
        if (mts.debugging && typeof console.log === 'function') {
            console.log.apply(null, arguments);
        }
    };


    /**
     * Alias for console.info that only displays if debugging is enabled
     */
    mts.debug.info = function() {
        if (mts.debugging && typeof console.info === 'function') {
            console.info.apply(null, arguments);
        }
    };


    /**
     * Alias for console.warn that only displays if debugging is enabled
     */
    mts.debug.warn = function() {
        if (mts.debugging && typeof console.warn === 'function') {
            mts.debug.printCaller();
            console.warn.apply(null, arguments);
        }
    };


    /**
     * Alias for console.error that only displays if debugging is enabled
     */
    mts.debug.error = function() {
        if (mts.debugging && typeof console.error === 'function') {
            console.error.apply(null, arguments);
        }
    };


    /**
     * Alias for console.dir that only displays if debugging is enabled
     */
    mts.debug.dir = function() {
        if (mts.debugging && typeof console.dir === 'function') {
            console.dir.apply(null, arguments);
        }
    };


    /**
     * Display arguments based on their types
     */
    mts.debug.display = function() {
        if (!mts.debugging) {
            return false;
        }
        $.each(arguments, function(index, arg) {
            if (typeof arg === 'object') {
                mts.debug.dir(arg);
            } else {
                mts.debug.log(arg);
            }
        });
    };
})(jQuery);


/**
 * Get unique values from an array, not differentiating between types.
 * This was tested alongside of 3 other functions and was found to be the fastest (that ignored type).
 * For a solution that maintains types, do this: return Array.from(new Set(a));
 * Using filter and indexOf is slower than these 2 methods and unsupported in older browsers.
 * phpjs' array_unique was extremely slow in all tests and should be avoided.
 *
 * @returns {Array}
 * @see http://stackoverflow.com/questions/1960473/unique-values-in-an-array#answer-1961068
 */
Array.prototype.unique = function() {
    var u = {}, b = [];
    for (var i = 0, l = this.length; i < l; ++i) {
        if (u.hasOwnProperty(this[i])) {
            continue;
        }
        b.push(this[i]);
        u[this[i]] = 1;
    }
    return b;
};


/**
 * Uppercase the first character of the string
 * @returns {string}
 */
String.prototype.ucfirst = function() {
    return this[0].toUpperCase() + this.substr(1);
};


/**
 * Generate a random integer between the start and end values.
 *
 * @param start {number}
 * @param end {number}
 * @returns {number}
 * @see http://www.w3schools.com/jsref/jsref_random.asp
 */
function get_random(start, end) {
    if (!isNaN(start) || !isNaN(end) || start > end) {
        return -1;
    }
    return Math.floor((Math.random() * end) + start);
}
