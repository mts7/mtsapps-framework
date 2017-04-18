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
     * Get the contents of the specified iframe
     * @returns string
     * @author Mike Rodarte
     */
    $.fn.iframeContents = function() {
        // be sure this is an iframe
        if (this[0].nodeName !== 'IFRAME') {
            return '';
        }

        return $($(this.contents()[0].body).contents().find('body').context).html();
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
    }
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
