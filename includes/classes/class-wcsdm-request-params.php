<?php
/**
 * HTTP Request Parameters Management Class
 *
 * This file contains the Wcsdm_Request_Params class which manages HTTP request
 * parameters (query strings or body data) for API requests in the WooReer plugin.
 * It provides a flexible interface for adding, removing, and retrieving parameters
 * used in distance calculation API calls.
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
 * HTTP Request Parameters Management Class.
 *
 * Manages HTTP request parameters (query strings for GET requests or body data for POST requests)
 * for API requests in the WooReer distance calculation system. Provides methods to add, remove,
 * check, and retrieve parameters that are sent with API requests to various distance calculation
 * providers (Google, Mapbox, etc.).
 *
 * @since 3.0
 */
class Wcsdm_Request_Params {
	/**
	 * Array of HTTP query params.
	 *
	 * Stores param key-value pairs where the key is the param name
	 * and the value is the param value. Values can be strings, arrays,
	 * or other scalar types depending on the API requirements.
	 *
	 * @since 3.0
	 * @var array<string, mixed> Key-value pairs of query parameters.
	 */
	private array $params = array();

	/**
	 * Constructor.
	 *
	 * Initialize the request params collection with optional initial values.
	 *
	 * @since 3.1.0
	 *
	 * @param array<string,mixed>|null $initial_params Optional. Initial params to set.
	 * @return void
	 */
	public function __construct( ?array $initial_params = array() ) {
		$this->params = $initial_params ?? array();
	}

	/**
	 * Add a param to the collection.
	 *
	 * Adds a new param with the specified key and value. If the merge parameter is true
	 * and both the new value and existing value are arrays, they will be merged together.
	 * Otherwise, the new value will replace any existing value for the key.
	 *
	 * @since 3.0
	 *
	 * @param mixed  $value The param value to add. Can be string, array, or other scalar types.
	 * @param string $key   The param key/name.
	 * @param bool   $merge Optional. Whether to merge array values if both exist. Default false.
	 * @return void
	 */
	public function add_param( $value, string $key, ?bool $merge = false ):void {
		// Check if we should merge arrays: merge flag is true, both values are arrays.
		if ( $merge && is_array( $value ) && $this->has_param( $key ) && is_array( $this->get_param( $key ) ) ) {
			// Merge the existing array with the new array.
			$this->params[ $key ] = array_merge( $this->get_param( $key ), $value );
		} else {
			// Set or replace the param value.
			$this->params[ $key ] = $value;
		}
	}

	/**
	 * Remove a param from the collection.
	 *
	 * Deletes the param with the specified key if it exists.
	 * If the key doesn't exist, no action is taken.
	 *
	 * @since 3.0
	 *
	 * @param string $key The param key/name to remove.
	 * @return void
	 */
	public function remove_param( string $key ):void {
		unset( $this->params[ $key ] );
	}

	/**
	 * Check if a param exists in the collection.
	 *
	 * Determines whether a param with the specified key is present
	 * in the params array, regardless of its value.
	 *
	 * @since 3.0
	 *
	 * @param string $key The param key/name to check.
	 * @return bool True if the param exists, false otherwise.
	 */
	public function has_param( string $key ):bool {
		return isset( $this->params[ $key ] );
	}

	/**
	 * Get a specific param value.
	 *
	 * Retrieves the value of a param by its key. Returns null if the
	 * param doesn't exist in the collection.
	 *
	 * @since 3.0
	 *
	 * @param string $key The param key/name to retrieve.
	 * @return mixed|null The param value if found, null otherwise.
	 */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}

	/**
	 * Get all params.
	 *
	 * Returns the complete array of all stored params with their
	 * key-value pairs.
	 *
	 * @since 3.0
	 *
	 * @return array<string, mixed> Associative array of all params.
	 */
	public function get_params():array {
		return $this->params;
	}
}
