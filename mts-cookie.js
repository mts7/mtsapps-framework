/**
 * Basic cookie object
 * @author Mike Rodarte
 */
function MtsCookie() {
	this.cookies = {};

	/**
	 * Get all cookies and save them locally
	 */
	this.getAll = function () {
		var cookieList = document.cookie.split( '; ' );
		var cookies = {};
		for ( var i = 0; i < cookieList.length; i++ ) {
			var cookie = cookieList[ i ];
			var pair = cookie.split( '=' );
			cookies[ pair[ 0 ] ] = pair[ 1 ];
		}
		this.cookies = cookies;

		return this.cookies;
	};

	/**
	 * Get a specific cookie by name
	 * @param name
	 * @returns {*}
	 */
	this.get = function (name) {
		if ( this.cookies[ name ] === undefined ) {
			this.getAll();
		}

		return this.cookies[ name ];
	};

	/**
	 * Set a cookie with a name and value
	 * @param name
	 * @param value
	 */
	this.set = function (name, value) {
		document.cookie = name + '=' + value;
	};

	/**
	 * Delete a cookie by name
	 * @param name
	 * @uses webocookies.set()
	 */
	this.delete = function (name) {
		this.set( name, ';Thu, 01 Jan 1970 00:00:00 UTC' );
	};
}
