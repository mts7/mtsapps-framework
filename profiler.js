/**
 * Class to provide methods for documenting duration, calls, and other reporting
 * features.
 * @constructor
 * @author Mike Rodarte
 */
function Profiler() {
  /**
   * Tracker of functions called and their start and end times
   * @type {Array}
   */
  var bigData = [];

  /**
   * Flag for using the profiler
   * @type {boolean}
   */
  var enabled = false;

  /**
   * Tracker of times functions have been called
   * @type {{}}
   */
  var functionsCalled = {};

  /**
   * Time threshold for displaying long-running functions
   * @type {number}
   */
  var timeThreshold = 100;

  /**
   * Disable the class
   */
  this.disable = function () {
    enabled = false;
  };

  /**
   * Enable the class
   */
  this.enable = function () {
    enabled = true;
  };

  /**
   * Reset the private members
   */
  this.reset = function () {
    bigData = [];
    enabled = false;
    functionsCalled = {};
  };

  /**
   * Track the start time and function calls for the calling function
   * @returns {number|boolean}
   */
  this.start = function () {
    // get timestamp
    var now = getTimestamp();

    if (!enabled) {
      return false;
    }

    // get function name
    var functionName = getFunctionName();

    // create new element for tracking number of calls to function
    if (!functionsCalled.hasOwnProperty(functionName)) {
      functionsCalled[functionName] = 0;
    }
    // accumulate the number of times this function was called
    functionsCalled[functionName]++;

    // add these values to bigData
    bigData.push({
      id: -1,
      name: functionName,
      start: now,
      end: 0
    });

    // get the last index
    var lastIndex = bigData.length - 1;

    // save the last index
    bigData[lastIndex].id = lastIndex;

    // return the last index
    return lastIndex;
  };

  /**
   * Track the end time of the calling function
   * @param {number} id
   */
  this.end = function (id) {
    // get timestamp
    var now = getTimestamp();

    if (!enabled) {
      return false;
    }

    if (bigData[id] !== undefined) {
      if (bigData[id].id === id) {
        bigData[id].end = now;
        bigData[id].duration = Math.round((bigData[id].end - bigData[id].start).toFixed(3) * 1000);
      }
    }
  };

  /**
   * Display a report in the console which dumps the variables without formatting or analysis
   */
  this.report = function () {
    if (!enabled) {
      console.warn('Profiler is not enabled, so reporting is not available');
    }
    console.info('******** Profiler Report ********');
    console.group('-- Functions Called --');
    console.dir(functionsCalled);
    console.groupEnd();
    console.group('-- Timing --');
    console.dir(bigData);
    console.groupEnd();
    console.log(bigData.length + ' function calls profiled');

    var longRunners = bigData.filter(function (value) {
      return value.duration >= timeThreshold;
    });
    longRunners.sort(function (a, b) {
      return b.duration - a.duration;
    });
    console.group('-- Long Runners --');
    console.dir(longRunners);
    console.groupEnd();
  };

  /**
   * Set threshold for long-running functions
   * @param value
   */
  this.threshold = function (value) {
    var clean = parseInt(value) || value + 0;
    if (clean > 0) {
      timeThreshold = clean;
    }
  };

  /**
   * Get the function name of the caller.
   * @returns {string}
   */
  function getFunctionName() {
    var e = new Error();
    if (!e.stack) {
      return '';
    }

    // this stack element would be 1, so its caller would be 2, which is typically going to be profiler functions
    // 3 should be default to get the caller of the profiler function that called this one
    var defaultNumBack = '3';
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
    var lastFunc = stack[parseInt(numBack)];
    // stack lines have a specific format we need to use to extract data
    var pattern = /\s+at (.+) \((.+):(\d+):(\d+)\)/;
    // get the matches from the stack line
    var matches = pattern.exec(lastFunc);

    if (matches !== null && matches.length > 0) {
      // make the values easier for people to read
      return matches[1];
    }
    else {
      console.log('stack', stack);
      return '<anonymousOrUnknown>';
    }
  }

  /**
   * Get the current timestamp
   * @returns {number}
   */
  function getTimestamp() {
    return (Date.now() / 1000);
  }
} // end Profiler
