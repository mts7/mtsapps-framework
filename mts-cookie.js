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
    this.cookies = {};

    /**
     * Load the cookies to the object
     */
    this.load = function() {
        var cookieList = document.cookie.split('; ');
        var cookies = {};
        for (var i = 0; i < cookieList.length; i++) {
            var cookie = cookieList[i];
            var pair = cookie.split('=');
            cookies[pair[0]] = pair[1];
        }
        this.cookies = cookies;
    };

    /**
     * Get all cookies
     * @returns {Object}
     */
    this.getAll = function () {
        this.load();
        return this.cookies;
    };

    /**
     * Get a specific cookie by name
     * @param name
     * @returns {*}
     */
    this.get = function (name) {
        if (this.cookies[name] === undefined) {
            this.load();
        }

        return this.cookies[name];
    };

    /**
     * Set a cookie with a name and value
     * @param name
     * @param value
     * @param expires
     * @param path
     * @param domain
     * @param secure
     * @param httponly
     * @returns {boolean}
     */
    this.set = function (name, value, expires, path, domain, secure, httponly) {
        // set values
        var cookie = {
            name: value,
            expires: expires ? expires : null,
            path: path ? path : '/',
            domain: domain ? domain : null,
            secure: secure ? 'secure' : null,
            HttpOnly: httponly ? 'HttpOnly': null
        };

        // build string
        var str = '';
        for (var key in cookie) {
            if (!cookie.hasOwnProperty(key)) {
                continue;
            }
            var val = cookie[key];
            if (val === null || val === undefined) {
                continue;
            }
            switch (key) {
                case 'name':
                case 'path':
                case 'domain':
                    str += name + '=' + val + '; ';
                    break;
                case 'expires':
                    val = setExpiration(val);
                    str += name + '=' + val + '; ';
                    break;
                case 'secure':
                case 'HttpOnly':
                    str += val + '; ';
                    break;
            }
        }

        // set the new cookie
        document.cookie = str;

        // refresh the object's cookies
        this.load();

        return this.exists(name);
    };

    /**
     * Delete a cookie by name
     * @param name
     * @param path
     * @param domain
     * @returns {boolean}
     * @uses MtsCookie.set()
     */
    this.delete = function (name, path, domain) {
        // set the expiration to yesterday
        return this.set(name, '', -1, path, domain);
    };

    /**
     * Check to see if a cookie exists with a value
     * @param name
     * @returns {boolean}
     * @uses MtsCookie.get()
     */
    this.exists = function (name) {
        var val = this.get(name);
        return val !== undefined && val.length > 0;
    };

    /**
     * Set expiration date
     * @param expires int number of days to expire
     * @returns {*}
     */
    function setExpiration(expires) {
        if (expires === null) {
            return null;
        }

        var today = new Date();
        var expr = new Date(today.getTime() + expires * 24 * 60 * 60 * 1000);
        return expr.toUTCString();
    }

    // load the cookies
    this.load();
}
