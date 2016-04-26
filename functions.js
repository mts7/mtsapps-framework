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
 * Generate a random integer between the start and end values.
 *
 * @param start Number
 * @param end Number
 * @returns {number}
 * @see http://www.w3schools.com/jsref/jsref_random.asp
 */
    if (!isNaN(start) || !isNaN(end) || start > end) {
    }
    return Math.floor((Math.random() * end) + start);
}
