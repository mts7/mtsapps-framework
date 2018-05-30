/**
 * Debug class wrapper for console object
 * @constructor
 * @author Mike Rodarte
 */
function Debug() {
  /**
   * Public variable to determine if messages should display
   * @type {boolean}
   */
  this.debugging = false;
  /**
   * Public variable to determine if timestamps and profiling should display
   * @type {boolean}
   */
  this.profiling = false;
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
    // 4 should be default to get the caller of the debug function that called this one
    var defaultNumBack = '4';
    var numBack = defaultNumBack;
    if (arguments[0] !== undefined) {
      numBack = parseInt(arguments[0]) + '';
    }

    // split the stack
    var stack = e.stack.toString().split(/\r\n|\n/);

    // use default value in case provided argument is bad
    if (!stack.hasOwnProperty(numBack) && stack.hasOwnProperty(defaultNumBack)) {
      numBack = defaultNumBack;
    }

    // get the last function data
    var lastFunc = stack[numBack];
    // stack lines have a specific format we need to use to extract data
    var pattern = /\s+at (.+) \((.+):(\d+):(\d+)\)/;
    // get the matches from the stack line
    var matches = pattern.exec(lastFunc);

    // make the values easier for people to read
    var results = {
      function: '',
      file: '',
      line: -1,
      column: -1
    };
    if (matches !== null) {
      results = {
        function: matches[1],
        file: matches[2],
        line: matches[3],
        column: matches[4]
      };
    }
    return results;
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

  /**
   * Display a message with a timestamp in front of it
   * @param {string} message
   * @returns {boolean}
   */
  this.timestamp = function (message) {
    if (!this.debugging || !this.profiling) {
      return false;
    }

    self.info((Date.now() / 1000) + ' ' + message);
  };
} // end Debug
