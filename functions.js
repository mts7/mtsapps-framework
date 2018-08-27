/**
 * Functions, prototypes, and plugins useful in web development
 * @author Mike Rodarte
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

if (typeof Object.equals !== 'function') {
  /**
   * Validate that the objects both have the same number of properties and values of the properties are equal
   * @param {{}} object Object to use for comparison
   * @returns {boolean}
   */
  Object.prototype.equals = function (object) {
    var thisProperties = Object.getOwnPropertyNames(this);
    var thatProperties = Object.getOwnPropertyNames(object);

    var thisLength = thisProperties.length;
    var thatLength = thatProperties.length;

    if (thisLength === 0 && thatLength === 0) {
      // both are empty objects
      return true;
    }

    if (thisLength !== thatLength) {
      // there are different numbers of properties in each object
      return false;
    }

    // iterate through property names
    for (var property in this) {
      if (!this.hasOwnProperty(property)) {
        // property is probably a built-in function
        continue;
      }

      // get the actual type of the property since Object and Array do not work with ===
      var type = Object.prototype.toString.call(this[property]).replace('[object ', '').replace(']', '');

      switch (type) {
        case 'Object':
        case 'Array':
          var valid = this[property].equals(object[property]);
          if (!valid) {
            // get out of here once anything is not equal
            return false;
          }
          break;
        default:
          if (this[property] !== object[property]) {
            return false;
          }
          break;
      }
    } // end loop

    // all checks passed, so either there is bad logic or the comparisons are good
    return true;
  }
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
 * Get the name of the function that is calling this function.
 * There is no error handling in this function.
 * @returns {string}
 */
function getCaller() {
  return /\s+at (.+) \((.+):(\d+):(\d+)\)/.exec((new Error).stack.toString().split(/\r\n|\n/)[2])[1];
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
