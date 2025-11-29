/**
 * Cookie utility functions with error handling
 */
export const cookieUtils = {
	/**
	 * Check if cookies are available
	 * @returns {boolean} True if cookies are available
	 */
	isAvailable: (): boolean => {
		try {
			// Test if we can set and read a test cookie
			const testName = "wcsdm_cookie_test";
			const testValue = "test";
			document.cookie = `${testName}=${testValue};path=/`;
			const isAvailable =
				document.cookie.indexOf(`${testName}=${testValue}`) !== -1;
			// Clean up test cookie
			document.cookie = `${testName}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/`;
			return isAvailable;
		} catch (e) {
			console.error(e);
			return false;
		}
	},

	/**
	 * Set a cookie with error handling
	 * @param {string} name - Cookie name
	 * @param {string} value - Cookie value
	 * @param {number} days - Expiry days
	 * @returns {boolean} True if cookie was set successfully
	 */
	set: (name: string, value: string, days: number): boolean => {
		try {
			if (!cookieUtils.isAvailable()) {
				return false;
			}

			const expires = new Date();
			expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
			document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;

			// Verify the cookie was set
			return cookieUtils.get(name) === value;
		} catch (e) {
			console.warn(
				"WCSDM: Failed to set cookie:",
				e instanceof Error ? e.message : String(e),
			);
			return false;
		}
	},

	/**
	 * Get a cookie value with error handling
	 * @param {string} name - Cookie name
	 * @returns {string|null} Cookie value or null if not found/error
	 */
	get: (name: string): string | null => {
		try {
			if (!cookieUtils.isAvailable()) {
				return null;
			}

			const nameEQ = name + "=";
			const ca = document.cookie.split(";");
			for (let i = 0; i < ca.length; i++) {
				let c = ca[i];
				while (c.charAt(0) === " ") c = c.substring(1);
				if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length);
			}
			return null;
		} catch (e) {
			console.warn(
				"WCSDM: Failed to get cookie:",
				e instanceof Error ? e.message : String(e),
			);
			return null;
		}
	},

	/**
	 * Delete a cookie with error handling
	 * @param {string} name - Cookie name
	 * @returns {boolean} True if cookie was deleted successfully
	 */
	delete: (name: string): boolean => {
		try {
			if (!cookieUtils.isAvailable()) {
				return false;
			}

			// Set cookie with past expiration date to delete it
			document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/`;

			// Verify the cookie was deleted
			return cookieUtils.get(name) === null;
		} catch (e) {
			console.warn(
				"WCSDM: Failed to delete cookie:",
				e instanceof Error ? e.message : String(e),
			);
			return false;
		}
	},
};
