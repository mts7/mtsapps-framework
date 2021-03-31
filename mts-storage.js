/**
 * Class to handle storing values to the browser, whether through localStorage,
 * sessionStorage, or the MtsCookie object
 *
 * Basic usage:
 *
 * // prepare storage
 * var storage = new MtsStorage('local');
 * // OR //
 * var storage = new MtsStorage();
 * storage.setType('session');
 *
 * // set a value
 * storage.set('mts', 'storage');
 *
 * // get the stored value
 * var storedMts = storage.get('mts');
 * console.log(storedMts);
 *
 * // delete the stored value
 * storage.delete('mts');
 *
 * // verify the existence of the stored value
 * console.log(storage.exists('mts')); // false
 *
 * @param {string} [inputType] Type of storage to use
 * @author Mike Rodarte
 * @constructor
 * @uses MtsCookie
 */
function MtsStorage(inputType) {
  /**
   * Define a cookie jar for the cookie storage type
   * @type {MtsCookie}
   */
  var cookieJar = new MtsCookie();
  /**
   * Set up object for self
   * @type {MtsStorage}
   */
  var self = this;
  /**
   * Type of storage
   * @type {string}
   */
  var type = '';

  /**
   * Initialize the type
   * @param {string} input
   */
  function init(input) {
    self.setType(input);
  } // end init

  /**
   * Clear all values in storage (except for cookies)
   */
  this.clear = function () {
    switch (type) {
      case 'local':
        localStorage.clear();
        break;
      case 'session':
        sessionStorage.clear();
        break;
      case 'cookie':
        // TODO: consider adding functionality to clear all cookies
        break;
    }
  }; // end clear

  /**
   * Delete the item from storage for the specified key
   * @param {string} key
   */
  this.delete = function (key) {
    switch (type) {
      case 'local':
        localStorage.removeItem(key);
        break;
      case 'session':
        sessionStorage.removeItem(key);
        break;
      case 'cookie':
        cookieJar.delete(key);
        break;
    }
  }; // end delete

  /**
   * Determine if a specified key exists in storage
   * @param {string} key
   * @returns {boolean}
   */
  this.exists = function (key) {
    var exists;

    switch (type) {
      case 'local':
        exists = localStorage.getItem(key) !== null;
        break;
      case 'session':
        exists = sessionStorage.getItem(key) !== null;
        break;
      case 'cookie':
        exists = cookieJar.exists(key);
        break;
    }

    return exists;
  }; // end exists

  /**
   * Get the value for the specified key
   * @param {string} key
   * @returns {*}
   */
  this.get = function (key) {
    var value;

    // get the value for the key
    switch (type) {
      case 'local':
        value = localStorage.getItem(key);
        break;
      case 'session':
        value = sessionStorage.getItem(key);
        break;
      case 'cookie':
        value = cookieJar.get(key);
        break;
    }

    return value;
  }; // end get

  /**
   * Get the name of the key at the specified index
   * @param {int} index
   * @returns {string}
   */
  this.getKey = function (index) {
    var name;

    // get the name for the key
    switch (type) {
      case 'local':
        name = localStorage.key(index);
        break;
      case 'session':
        name = sessionStorage.key(index);
        break;
      case 'cookie':
        name = cookieJar.getName(index);
        break;
    }

    return name;
  }; // end getKey

  /**
   * Get the current type of storage
   * @returns {string}
   */
  this.getType = function () {
    return type;
  }; // end getType

  /**
   * Get the number of items stored
   * @returns {number}
   */
  this.length = function () {
    var length = 0;

    switch (type) {
      case 'local':
        length = localStorage.length;
        break;
      case 'session':
        length = sessionStorage.length;
        break;
      case 'cookie':
        length = cookieJar.size();
        break;
    }

    return length;
  };

  /**
   * Set the key and value
   * @param {string} key Name of item to set
   * @param {string} value Value of item to set
   */
  this.set = function (key, value) {
    // save the value to the key
    switch (type) {
      case 'local':
        localStorage.setItem(key, value);
        break;
      case 'session':
        sessionStorage.setItem(key, value);
        break;
      case 'cookie':
        cookieJar.set(key, value, 400, '/');
        break;
    }
  }; // end set

  /**
   * Set the type of storage
   * @param {string} input
   */
  this.setType = function (input) {
    var acceptableTypes = [
      'local',
      'session',
      'cookie'
    ];

    if (acceptableTypes.indexOf(input) > -1) {
      // this is a valid type
      type = input;
    }
    else {
      if (acceptableTypes.indexOf(type) === -1) {
        // default to cookie storage in case local storage isn't available
        type = typeof Storage !== 'undefined' ? 'local' : 'cookie';
      }
    }
  }; // end setType

  // call the init method to set the input type
  init(inputType);
} // end MtsStorage
