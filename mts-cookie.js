/**
 * Basic cookie object
 * @author Mike Rodarte
 *
 * Main usage:
 *
 * var Cookies = new MtsCookie();
 *
 * // set cookie -- expiry days, path, domain, secure, and httponly are available, too
 * Cookies.set('mts', 'cookie');
 *
 * // get cookie
 * alert(Cookies.get('mts')); // alerts 'cookie'
 *
 * // delete cookie -- path and domain are available, too
 * Cookies.delete('mts');
 *
 * // get all cookies
 * var cookies = Cookies.getAll();
 *
 * // alert each cookie
 * for (var name in cookies) {
 *   if (!cookies.hasOwnProperty(name)) {
 *     continue;
 *   }
 *   alert(name + ': ' + cookies[name]);
 * }
 */
function MtsCookie() {
  /**
   * Cookie storage for the object
   * @type {{}}
   */
  var mtsCookies = {};

  /**
   * Load the cookies to the object
   */
  this.load = function () {
    var cookieList = document.cookie.split('; ');
    var cookies = {};
    for (var i = 0; i < cookieList.length; i++) {
      var cookie = cookieList[i];
      var pair = cookie.split('=');
      cookies[pair[0]] = pair[1];
    }
    mtsCookies = cookies;
  }; // end load

  /**
   * Get all cookies
   * @returns {Object}
   */
  this.getAll = function () {
    this.load();
    return mtsCookies;
  }; // end getAll

  /**
   * Get a specific cookie by name
   * @param {string} name Name of the cookie
   * @returns {*}
   */
  this.get = function (name) {
    if (mtsCookies[name] === undefined) {
      this.load();
    }

    return mtsCookies[name];
  }; // end get

  /**
   * Get the name of the cookie at the specified index
   * @param {int} index Expected index to use to find the name
   * @returns {*}
   */
  this.getName = function (index) {
    // set up an iterator
    var i = 0;

    // set up the return variable
    var foundName = '';
    // loop through cookies with names
    for (var name in mtsCookies) {
      if (!mtsCookies.hasOwnProperty(name)) {
        continue;
      }
      // check for this iteration being the same as the desired index
      if (i === index) {
        // set return value to this cookie name
        foundName = name;
        break;
      }
      i++;
    }

    return foundName;
  }; // end getName

  /**
   * Set a cookie with a name and value
   * @param {string} name Name of the cookie
   * @param {string|null} value Value of the cookie
   * @param {int} [expires] Number of days until expiration
   * @param {string} [path] Path where cookie is valid
   * @param {string} [domain] Domain where cookie is valid
   * @param {string} [secure] Only use on Secure connections
   * @param {string} [httponly] Only available through HTTP requests
   * @returns {boolean}
   */
  this.set = function (name, value, expires, path, domain, secure, httponly) {
    // set values
    var cookie = {
      name: value,
      Expires: expires ? expires : null,
      Path: path ? path : '/',
      Domain: domain ? domain : '.' + location.host,
      Secure: secure ? 'secure' : null,
      HttpOnly: httponly ? 'HttpOnly' : null
    };

    // build string
    var str = '';
    for (var key in cookie) {
      if (!cookie.hasOwnProperty(key)) {
        continue;
      }
      var val = cookie[key];
      if (key !== 'name' && (val === null || val === undefined)) {
        continue;
      }
      switch (key) {
        case 'name':
          // set the cookie name
          str += name + '=' + val + '; ';
          break;
        case 'Path':
        case 'Domain':
          // set the keys and values
          str += key + '=' + val + '; ';
          break;
        case 'Expires':
          // update expiration to a proper date
          val = setExpiration(val);
          // set the key and value
          str += key + '=' + val + '; ';
          break;
        case 'Secure':
        case 'HttpOnly':
          // set the value
          str += val + '; ';
          break;
      }
    }

    // set the new cookie without trailing semi-colon and space
    document.cookie = str.substr(0, str.lastIndexOf('; '));

    // refresh the object's cookies
    this.load();

    return this.exists(name);
  }; // end set

  /**
   * Delete a cookie by name
   * @param {string} name Name of the cookie
   * @param {string} [path] Path where cookie is valid
   * @param {string} [domain] Domain where cookie is valid
   * @returns {boolean}
   * @uses MtsCookie.set()
   */
  this.delete = function (name, path, domain) {
    // set the expiration to yesterday
    return this.set(name, null, -1, path, domain);
  }; // end delete

  /**
   * Get the current number of cookies
   * @returns {number}
   */
  this.size = function () {
    // reload the cookies to get any updates
    this.load();

    return Object.keys(mtsCookies).length;
  }; // end size

  /**
   * Check to see if a cookie exists with a value
   * @param {string} name Name of cookie to check
   * @returns {boolean}
   * @uses MtsCookie.get()
   */
  this.exists = function (name) {
    var val = this.get(name);
    return val !== undefined && val.length > 0;
  }; // end exists

  /**
   * Set expiration date
   * @param {int} expires Number of days to expire
   * @returns {*}
   */
  function setExpiration(expires) {
    if (expires === null) {
      return null;
    }

    var today = new Date();
    var oneDay = 24 * 60 * 60;
    var expr = new Date(expires * oneDay * 1000 + today.getTime());
    return expr.toUTCString();
  } // end setExpiration

  // load the cookies
  this.load();
} // end MtsCookie
