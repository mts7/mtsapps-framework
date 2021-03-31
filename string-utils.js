/**
 * Uppercase the first character of the string
 * @returns {string}
 */
String.prototype.ucfirst = function () {
    return this[0].toUpperCase() + this.substr(1);
};


/**
 * Uppercase the first character of each word
 * @returns {string}
 * @see String.prototype.ucfirst()
 */
String.prototype.ucwords = function () {
    const words = this.split(' ');
    let str = '';
    for (let i in words) {
        if (!words.hasOwnProperty(i)) {
            continue;
        }

        str += words[i].ucfirst() + ' ';
    }

    return str.trim();
};

if (typeof ''.repeat !== 'function') {
    /**
     * Repeat the string so many times
     * @param {number} number Number of times to repeat the string
     * @returns {string}
     */
    String.prototype.repeat = function (number) {
        let string = '';
        for (let i = 0; i < number; i++) {
            string += this;
        }
        return string;
    };
}

/**
 * Determine if the variable is an actual object
 * @param obj
 * @returns {boolean}
 * @see https://stackoverflow.com/questions/8511281/check-if-a-value-is-an-object-in-javascript#answer-42250981
 */
function isObject(obj) {
    return getType(obj) === 'Object';
}

/**
 * Get the string of an object with key/value pairs
 * @param {Array|Object} obj Object or Array for converting to a string
 * @param indent Multiplier used for getObjectString and getArrayString
 * @returns {string}
 */
function getObjectString(obj, indent) {
    const objectType = getType(obj);
    const validTypes = ['Object', 'Array'];

    // verify obj is an object
    if (validTypes.indexOf(objectType) === -1) {
        // give the user a string representation anyway
        return getString(obj, false);
    }

    // return empty braces or brackets if the object is empty
    if (objectType === 'Object' && Object.keys(obj).length === 0) {
        return '{}';
    }
    if (objectType === 'Array' && obj.length === 0) {
        return '[]';
    }

    // set the multiplier to 1 if it is not set
    if (indent === undefined) {
        indent = 1;
    }

    // prepare the string
    let string = objectType === 'Object' ? '{' : '[';
    // set the tab to be 2 spaces times the multiplier
    const tab = '  '.repeat(indent);

    // loop through the object
    for (let key in obj) {
        // verify the object has the key as a property
        if (!obj.hasOwnProperty(key)) {
            continue;
        }

        // start the value on the next line
        string += '\n' + tab;

        if (objectType === 'Object') {
            // add the key to the string
            string += key + ': ';
        }

        // get the value
        let val = obj[key];

        // add the value to the string
        if (validTypes.indexOf(getType(val)) > -1) {
            // check for object because of the object keys
            if (objectType === 'Object') {
                string += '\n' + tab;
            }
            // value is an object, so call this function
            string += getObjectString(val, indent + 1) + ',';
        } else {
            // value is not an object, so add it to the string
            string += getString(val) + ',';
        }
    }

    // remove the trailing comma
    string = string.replace(/,$/, '');

    // return the string with the closing character at one fewer indent
    return string + '\n' + '  '.repeat(indent - 1) + (objectType === 'Object' ? '}' : ']');
} // end getObjectString

/**
 * Get the string version of a variable
 * @param value
 * @param {boolean=} html Display spaces as &nbsp;
 * @returns {*|string}
 */
function getString(value, html) {
    // set a default value, even though it is overridden by the switch statement
    let string;

    // get the type without the extra object notation
    const type = getType(value);

    switch (type) {
        case 'String':
            string = value;
            break;
        case 'Number':
        case 'Boolean':
            string = value.toString();
            break;
        case 'Object':
        case 'Array':
            string = getObjectString(value);
            break;
        case 'Null':
            string = 'null';
            break;
        case 'Undefined':
            string = 'undefined';
            break;
        default:
            string = 'unknown type {' + type + '}';
            break;
    }

    if (html === true) {
        string = string.replace(/ /g, '&nbsp;');
    }

    return string;
} // end getString

/**
 * Alert whatever the value is in a formatted version
 * @param value
 */
function stringAlert(value) {
    alert(getString(value, false));
}

/**
 * Alert an object in string form
 * @param obj
 */
function objectAlert(obj) {
    if (!isObject(obj)) {
        alert('not an object');
    } else {
        alert(getObjectString(obj));
    }
}

/**
 * Gets all permutations of the passed word with both uppercase and lowercase letters.
 * @param {string} word
 * @returns {string[]}
 * @see https://stackoverflow.com/questions/27992204/find-all-lowercase-and-uppercase-combinations-of-a-string-in-javascript#answer-27995370
 */
function getLowerAndUpperCaseWord(word = '') {
    const letters = word.split('');
    const permutationCount = 1 << word.length;
    let results = [];

    for (let permutation = 0; permutation < permutationCount; permutation++) {
        letters.reduce((permutation, letter, i) => {
            letters[i] = (permutation & 1) ? letter.toUpperCase() : letter.toLowerCase();
            return permutation >> 1;
        }, permutation);

        results.push(letters.join(''));
    }
    return results.filter((value, index, self) => {
        return self.indexOf(value) === index;
    });
}
