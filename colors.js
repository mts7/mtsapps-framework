/**
 * Find the inverse color of the provided color in hex.
 *
 * @param string hex Hexadecimal value of either 3 or 6 characters (with no leading #)
 * @todo Handle leading #
 * @todo Consider moving individual variables into an object and using a loop
 */
function invertColor(hex) {
    // declare variables
    var r, g, b;

    // grab R, G, B from hex (if 6 or 3)
    if (hex.length === 6) {
        r = hex.substr(0, 2);
        g = hex.substr(2, 2);
        b = hex.substr(4, 2);
    } else if (hex.length === 3) {
        r = hex.substr(0, 1);
        g = hex.substr(1, 1);
        b = hex.substr(2, 1);
        r = r + '' + r;
        g = g + '' + g;
        b = b + '' + b;
    } else {
        return false;
    }

    // get decimal values of R, G, B
    var decR = parseInt(r, 16);
    var decG = parseInt(g, 16);
    var decB = parseInt(b, 16);

    // make sure the input was valid hexadecimal  
    if (isNaN(decR) || isNaN(decG) || isNaN(decB)) {
        return false;
    }

    // determine the opposite of each color (with maximum value of 255)
    var diffR = 255 - decR;
    var diffG = 255 - decG;
    var diffB = 255 - decB;

    // return the concatenated string
    return diffR.toString(16) + diffG.toString(16) + diffB.toString(16);
}
