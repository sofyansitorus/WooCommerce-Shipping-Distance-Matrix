<?php
/**
 * HTTP Request Headers Management Class
 *
 * This file contains the Wcsdm_Request_Headers class which manages HTTP headers
 * for API requests in the WooReer plugin. It provides a simple interface for
 * adding, removing, and retrieving headers used in distance calculation API calls.
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * HTTP Request Headers Management Class.
 *
 * Manages HTTP headers for API requests in the WooReer distance calculation system.
 * Provides methods to add, remove, check, and retrieve headers that are sent with
 * API requests to various distance calculation providers (Google, Mapbox, etc.).
 *
 * @since 3.0
 */
class Wcsdm_Request_Headers {
	/**
	 * Array of HTTP headers.
	 *
	 * Stores header key-value pairs where the key is the header name
	 * and the value is the header value.
	 *
	 * @since 3.0
	 * @var array<string, string>
	 */
	private array $headers = array();

	/**
	 * Constructor.
	 *
	 * Initialize the request headers collection with optional initial values.
	 *
	 * @since 3.1.0
	 *
	 * @param array<string,string>|null $initial_headers Optional. Initial headers to set.
	 * @return void
	 */
	public function __construct( ?array $initial_headers = array() ) {
		$this->headers = $initial_headers ?? array();
	}

	/**
	 * Add a header to the collection.
	 *
	 * Stores a new header or updates an existing header with the provided
	 * key-value pair. If the key already exists, the value will be overwritten.
	 *
	 * @since 3.0
	 *
	 * @param string $value The header value to store.
	 * @param string $key   The header key/name (e.g., 'Authorization', 'Content-Type').
	 * @return void
	 */
	public function add_header( string $value, string $key ):void {
		// Store the header value with the specified key.
		$this->headers[ $key ] = $value;
	}

	/**
	 * Remove a header from the collection.
	 *
	 * Deletes the header with the specified key if it exists.
	 * If the key doesn't exist, no action is taken.
	 *
	 * @since 3.0
	 *
	 * @param string $key The header key/name to remove.
	 * @return void
	 */
	public function remove_header( string $key ):void {
		unset( $this->headers[ $key ] );
	}

	/**
	 * Check if a header exists in the collection.
	 *
	 * @since 3.0
	 *
	 * @param string $key The header key/name to check.
	 * @return bool True if the header exists, false otherwise.
	 */
	public function has_header( string $key ):bool {
		return isset( $this->headers[ $key ] );
	}

	/**
	 * Get a specific header value.
	 *
	 * Retrieves the value of a header by its key. Returns null if the
	 * header doesn't exist in the collection.
	 *
	 * @since 3.0
	 *
	 * @param string $key The header key/name to retrieve.
	 * @return string|null The header value if found, null otherwise.
	 */
	public function get_header( string $key ):?string {
		return $this->headers[ $key ] ?? null;
	}

	/**
	 * Get all headers.
	 *
	 * Returns the complete array of all stored headers with their
	 * key-value pairs.
	 *
	 * @since 3.0
	 *
	 * @return array<string, string> Associative array of all headers.
	 */
	public function get_headers():array {
		return $this->headers;
	}
}
