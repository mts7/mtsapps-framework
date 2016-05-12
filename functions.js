/**
 * Get unique values from an array, not differentiating between types.
 * This was tested alongside of 3 other functions and was found to be the fastest (that ignored type).
 * For a solution that maintains types, do this: return Array.from(new Set(a));
 * Using filter and indexOf is slower than these 2 methods and unsupported in older browsers.
 * phpjs' array_unique was extremely slow in all tests and should be avoided.
 *
 * @param a
 * @returns {Array}
 * @see http://stackoverflow.com/questions/1960473/unique-values-in-an-array#answer-1961068
 */
function array_unique(a) {
    // check if a is not an array
    if (Object.prototype.toString.call(a) !== '[object Array]') {
        return a;
    }
    var u = {}, b = [];
    for (var i = 0, l = a.length; i < l; ++i) {
        if (u.hasOwnProperty(a[i])) {
            continue;
        }
        b.push(a[i]);
        u[a[i]] = 1;
    }
    return b;
}


/**
 * Center an element according to the window, body, and element size.
 *
 * @param string selector Element selector
 * @author Mike Rodarte
 */
function centerBox(selector) {
    var $selector = $(selector);
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
        left: left
    });
}


/**
 * Generate a random integer between the start and end values.
 *
 * @param start Number
 * @param end Number
 * @returns {number}
 * @see http://www.w3schools.com/jsref/jsref_random.asp
 */
function get_random(start, end) {
    if (!isNaN(start) || !isNaN(end) || start > end) {
        return -1;
    }
    return Math.floor((Math.random() * end) + start);
}
