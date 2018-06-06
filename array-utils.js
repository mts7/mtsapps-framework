if (typeof Array.equals !== 'function') {
  /**
   * Validate that the arrays both have the same number of elements and values of elements (without sorting first)
   * @param {[]} array Array to use for comparison
   * @returns {boolean}
   */
  Array.prototype.equals = function (array) {
    // if the passed value is not an array, the arrays are not equal
    if (getType(array) !== 'Array') {
      return false;
    }

    // cache the lengths for comparisons
    var thisLength = this.length;
    var thatLength = array.length;

    if (thisLength === 0 && thatLength === 0) {
      // both are empty arrays, so they are equal
      return true;
    }

    if (thisLength !== thatLength) {
      // the arrays have different lengths, so they can't be equal
      return false;
    }

    // iterate through each element
    for (var i = 0; i < thisLength; i++) {
      // get the actual type of the property since Object and Array do not work with ===
      var type = Object.prototype.toString.call(this[i]).replace('[object ', '').replace(']', '');

      switch (type) {
        case 'Object':
        case 'Array':
          var valid = this[i].equals(array[i]);
          if (!valid) {
            // get out of here once anything is not equal
            return false;
          }
          break;
        default:
          if (this[i] !== array[i]) {
            return false;
          }
          break;
      } // end switch type
    } // end for loop

    // all checks passed, so either there is bad logic or the comparisons are good
    return true;
  }
}

if (typeof Array.prototype.intersect !== 'function') {
  /**
   * Find the exact matches between 2 single-dimension arrays
   * @param {Array} that
   * @returns {Array}
   */
  Array.prototype.intersect = function (that) {
    var temp = this.filter(function filterSameValue(n) {
      return that.indexOf(n) !== -1;
    });
    return temp.unique();
  };
}

if (typeof Array.prototype.unique !== 'function') {
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
  Array.prototype.unique = function () {
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
}

if (typeof Array.prototype.max !== 'function') {
  /**
   * Get the maximum value from the array.
   * This works well with a single-dimension array with numbers in it and does
   * not work with multi-dimensional arrays or non-numeric values. In such cases
   * as it does not find the maximum value, it will return NaN.
   *
   * @returns {*|number}
   * @see https://stackoverflow.com/questions/1669190/find-the-min-max-element-of-an-array-in-javascript#answer-50402621
   */
  Array.prototype.max = function () {
    return this.reduce(function (a, b) {
      return Math.max(a, b);
    });
  };
}

if (typeof Array.prototype.min !== 'function') {
  /**
   * Get the minimum value from the array.
   * This works well with a single-dimension array with numbers in it and does
   * not work with multi-dimensional arrays or non-numeric values. In such cases
   * as it does not find the maximum value, it will return NaN.
   *
   * @returns {*|number}
   * @see https://stackoverflow.com/questions/1669190/find-the-min-max-element-of-an-array-in-javascript#answer-50402621
   */
  Array.prototype.min = function () {
    return this.reduce(function (a, b) {
      return Math.min(a, b);
    });
  };
}
