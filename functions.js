/**
 * Functions, prototypes, and plugins useful in web development
 */

/**
 * Closure with jQuery wrapper
 */
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
  $.fn.iframeContents = function (content) {
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
   * Debug class wrapper for console object
   * @constructor
   */
  function Debug() {
    /**
     * Public variable to determine if messages should display
     * @type {boolean}
     */
    this.debugging = false;
    /**
     * Reference to this object for use within method functions
     * @type {Debug}
     */
    var self = this;

    /**
     * Get line information for the caller (or whichever element is specified)
     * @returns {*}
     */
    this.lineInfo = function () {
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
    this.printCaller = function (lineInfo) {
      if (this.debugging && typeof console.info === 'function') {
        if (lineInfo === undefined) {
          lineInfo = self.lineInfo(3);
        }
        console.info(lineInfo.file + ' called ' + lineInfo.function + ' on line ' + lineInfo.line);
      }
    };

    /**
     * Alias for console.log that only displays if debugging is enabled
     */
    this.log = function () {
      if (this.debugging && typeof console.log === 'function') {
        console.log.apply(null, arguments);
      }
    };

    /**
     * Alias for console.info that only displays if debugging is enabled
     */
    this.info = function () {
      if (this.debugging && typeof console.info === 'function') {
        console.info.apply(null, arguments);
      }
    };

    /**
     * Alias for console.warn that only displays if debugging is enabled
     */
    this.warn = function () {
      if (this.debugging && typeof console.warn === 'function') {
        self.printCaller();
        console.warn.apply(null, arguments);
      }
    };

    /**
     * Alias for console.error that only displays if debugging is enabled
     */
    this.error = function () {
      if (this.debugging && typeof console.error === 'function') {
        console.error.apply(null, arguments);
      }
    };

    /**
     * Alias for console.dir that only displays if debugging is enabled
     */
    this.dir = function () {
      if (this.debugging && typeof console.dir === 'function') {
        console.dir.apply(null, arguments);
      }
    };

    /**
     * Display arguments based on their types
     */
    this.display = function () {
      if (!this.debugging) {
        return false;
      }
      $.each(arguments, function (index, arg) {
        if (typeof arg === 'object') {
          self.dir(arg);
        }
        else {
          self.log(arg);
        }
      });
    };
  }


  /**
   * Generate HTML for a group of radios or a select box
   *
   * @param {string} type radio|select Type of options to display
   * @param {{}} values Key-value pairs of values to include
   * @param {{}} containerAttributes HTML attributes to associate with the container
   * @param {{}} optionAttributes HTML attributes to associate with each option
   * @param {string} [inputName] Name for the input
   * @param {string} [labelClass] Class to add to the label of the radio option
   * @returns {*}
   */
  function buildOptions(type, values, containerAttributes, optionAttributes, inputName, labelClass) {
    // validate type
    if (['radio', 'select'].indexOf(type) === -1) {
      return '';
    }

    // set default value for inputName
    if (inputName === undefined) {
      inputName = type;
    }

    // set default value for labelClass
    if (labelClass === undefined) {
      labelClass = '';
    }

    // create a container
    var $container = type === 'select' ? $('<select>') : $('<div>');

    // apply all attributes to $container
    if (isObject(containerAttributes)) {
      $container.attr(containerAttributes);
    }

    // store the options in an array
    var options = [];

    // create a blank first option
    if (type === 'select') {
      $container.attr('name', inputName);
      options.push($('<option value="-1"></option>'));
    }

    // prepare the values
    $.each(values, function (key, value) {
      var $row = null;
      // radios should be wrapped in a label to allow for clicking on the label to select
      if (type === 'radio') {
        $row = $('<label class="row-radio">');

        // add another label class if desired
        if (labelClass.length > 0) {
          $row.addClass(labelClass);
        }
      }

      // get the option ready
      var $option = null;
      switch (type) {
        case 'radio':
          $option = $('<input type="radio" value="' + value + '" />');
          // ensure radio $option will have a name
          if ((isObject(optionAttributes) && !optionAttributes.hasOwnProperty('name')) || !isObject(optionAttributes)) {
            $option.attr('name', inputName);
          }
          break;
        case 'select':
          $option = $('<option value="' + value + '">' + value + '</option>');
          break;
      }

      // apply all attributes to $option
      if (isObject(optionAttributes)) {
        $option.attr(optionAttributes);
      }

      // add this option to the array
      switch (type) {
        case 'radio':
          $row.append([$option, value]);
          options.push($row);
          break;
        case 'select':
          options.push($option);
          break;
      }
    });

    // put the options into the container
    $container.append(options);

    // return the container
    return $container;
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
  var words = this.split(' ');
  var str = '';
  for (var i in words) {
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
    var string = '';
    for (var i = 0; i < number; i++) {
      string += this;
    }
    return string;
  };
}

/**
 * Generate a random integer between the start and end values.
 *
 * @param start {number}
 * @param end {number}
 * @returns {number}
 * @see http://www.w3schools.com/jsref/jsref_random.asp
 */
function getRandom(start, end) {
  if (!isNaN(start) || !isNaN(end) || start > end) {
    return -1;
  }
  return Math.floor((Math.random() * end) + start);
}

/**
 * Generate psuedo-random string of characters that is chars long
 * @param {Number} chars Number of characters to use in the final output
 * @returns {string}
 * @see https://stackoverflow.com/questions/1349404/generate-random-string-characters-in-javascript#answer-1349426
 */
function generateString(chars) {
  // validate incoming argument
  if (isNaN(chars) || typeof chars !== 'number' || chars < 1) {
    // chars has to be a number that is 0 or greater
    return '';
  }

  // create empty string
  var text = '';
  // set up possible characters in a string
  var possible = 'qwertyuiopasdfghjklzxcvbnm QWERTYUIOPASDFGHJKLZXCVBNM9876543210.,;-';
  // cache the length of the possibilities
  var possibleLength = possible.length;

  // loop for the number of characters desired
  for (var i = 0; i < chars; i++) {
    // get a psuedo-random position of the possible characters and add to the string
    text += possible.charAt(getRandom(0, possibleLength));
  }

  // return the string of characters
  return text;
}

/**
 * Get the true variable type of the variable (since all variables are objects)
 * @param {*} value
 * @returns {string}
 */
function getType(value) {
  return Object.prototype.toString.call(value).replace('[object ', '').replace(']', '');
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
  var objectType = getType(obj);
  var validTypes = ['Object', 'Array'];

  // verify obj is an object
  if (validTypes.indexOf(objectType) === -1) {
    // give the user a string representation anyway
    return getString(obj);
  }

  // set the multiplier to 1 if it is not set
  if (indent === undefined) {
    indent = 1;
  }

  // prepare the string
  var string = objectType === 'Object' ? '{' : '[';
  // set the tab to be 2 spaces times the multiplier
  var tab = '  '.repeat(indent);

  // loop through the object
  for (var key in obj) {
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
    var val = obj[key];

    // add the value to the string
    if (validTypes.indexOf(getType(val)) > -1) {
      // check for object because of the object keys
      if (objectType === 'Object') {
        string += '\n' + tab;
      }
      // value is an object, so call this function
      string += getObjectString(val, indent + 1) + ',';
    }
    else {
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
 * @returns {*|string}
 */
function getString(value) {
  // set a default value, even though it is overridden by the switch statement
  var string = '';

  // get the type without the extra object notation
  var type = getType(value);

  switch (type) {
    case 'String':
      string = value;
      break;
    case 'Number':
      string = isNaN(value) ? 'isNaN' : value + '';
      break;
    case 'Boolean':
      string = value ? 'true' : 'false';
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

  return string;
} // end getString

/**
 * Alert whatever the value is in a formatted version
 * @param value
 */
function stringAlert(value) {
  alert(getString(value));
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
 * Determine sizes of the screen and return them to the caller.
 * Outer is the size of the window (plus a little extra).
 * Screen is the full sizes of the display device.
 * Available is the size of the window.
 * Inner is actual space available to web pages.
 * Banana.
 *  @returns {{availableHeight: Number, availableWidth: Number, screenHeight: Number, screenWidth: Number, innerHeight: Number, innerWidth: Number, outerHeight: Number, outerWidth: Number, orientation: string, orientationDegrees: *, dpi: string, devicePixelRatio: *, retina: string}}
 */
function screenSize() {
  var aHeight = window.screen.availHeight;
  var aWidth = window.screen.availWidth;
  var iWidth = window.innerWidth;
  var orientation = window.orientation;
  var retina = window.devicePixelRatio >= 2 ? 'Retina' : 'Not Retina';
  var higherDpi = iWidth > aWidth;
  var hiDpi = higherDpi || retina === 'Retina' ? 'High DPI' : 'Low DPI';

  var orientationText = '';
  if (orientation !== undefined) {
    switch (orientation) {
      case -90:
      case 90:
        orientationText = 'landscape';
        break;
      default:
        orientationText = 'portrait';
        break;
    }
  } else {
    orientationText = aHeight > aWidth ? 'portrait' : 'landscape';
  }

  return {
    availableHeight: aHeight,
    availableWidth: aWidth,
    screenHeight: window.screen.height,
    screenWidth: window.screen.width,
    innerHeight: window.innerHeight,
    innerWidth: iWidth,
    outerHeight: window.outerHeight,
    outerWidth: window.outerWidth,
    orientation: orientationText,
    orientationDegrees: orientation,
    dpi: hiDpi,
    devicePixelRatio: window.devicePixelRatio,
    retina: retina
  };
}
