<?php
/**
 * HTTP Request Dispatcher Class
 *
 * This file contains the Wcsdm_Request_Dispatcher class which handles HTTP API requests
 * for distance calculation providers in the WooReer plugin. It provides a unified interface
 * for making GET and POST requests with comprehensive request/response handling, error
 * management, and debugging capabilities.
 *
 * Key Features:
 * - Factory pattern for GET and POST request creation
 * - Automatic JSON response parsing
 * - Request/response logging with sensitive data masking
 * - WordPress HTTP API integration
 * - Comprehensive error handling
 * - Debug information access for troubleshooting
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP Request Dispatcher Class.
 *
 * Manages HTTP requests to distance calculation APIs, providing a consistent interface
 * for GET and POST operations with automatic response parsing, error handling, and
 * debugging capabilities.
 *
 * @since 3.0
 */
class Wcsdm_Request_Dispatcher {
	/**
	 * HTTP request method (GET or POST).
	 *
	 * @since 3.0
	 * @var string
	 */
	private $request_method;

	/**
	 * Full URL for the HTTP request.
	 *
	 * @since 3.0
	 * @var string
	 */
	private $request_url;

	/**
	 * Request parameters object containing query or body parameters.
	 *
	 * @since 3.0
	 * @var Wcsdm_Request_Params
	 */
	private $request_params;

	/**
	 * Request headers object containing HTTP headers.
	 *
	 * @since 3.0
	 * @var Wcsdm_Request_Headers
	 */
	private $request_headers;

	/**
	 * Raw response from wp_remote_get() or wp_remote_post().
	 *
	 * Contains either the full response array or a WP_Error object if the request failed.
	 *
	 * @since 3.0
	 * @var WP_Error|array
	 */
	private $response;

	/**
	 * HTTP response status code.
	 *
	 * @since 3.0
	 * @var int|string
	 */
	private $response_code;

	/**
	 * Response headers as associative array.
	 *
	 * @since 3.0
	 * @var array
	 */
	private $response_headers;

	/**
	 * Raw response body string.
	 *
	 * Contains the unparsed response content as returned by the API.
	 *
	 * @since 3.0
	 * @var string
	 */
	private $response_body;

	/**
	 * Parsed JSON response body as array.
	 *
	 * Contains the decoded JSON response or null if parsing fails or response is not JSON.
	 *
	 * @since 3.0
	 * @var array|null
	 */
	private $response_body_json;

	/**
	 * Callback function to mask sensitive data in debug output.
	 *
	 * This function is applied to request/response data before logging
	 * to prevent exposure of API keys, tokens, and other sensitive information.
	 *
	 * @since 3.0
	 * @var callable|null
	 */
	private $masking_callback;

	/**
	 * Private constructor to enforce usage of static factory methods.
	 *
	 * Use get() or post() static methods to create instances.
	 *
	 * @since 3.0
	 *
	 * @param string                $request_method   HTTP method (GET or POST).
	 * @param string                $request_url      Full URL for the request.
	 * @param Wcsdm_Request_Params  $request_params   Request parameters object.
	 * @param Wcsdm_Request_Headers $request_headers  Request headers object.
	 * @param callable|null         $masking_callback Optional callback to mask sensitive data.
	 */
	private function __construct(
		string $request_method,
		string $request_url,
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers,
		?callable $masking_callback = null
	) {
		// Initialize all request parameters.
		$this->request_method   = $request_method;
		$this->request_url      = $request_url;
		$this->request_params   = $request_params;
		$this->request_headers  = $request_headers;
		$this->masking_callback = $masking_callback;
	}

	/**
	 * Create and execute a GET request.
	 *
	 * Factory method that creates a new dispatcher instance, executes a GET request,
	 * and returns the dispatcher with populated response data.
	 *
	 * @since 3.0
	 *
	 * @param string                $request_url      Full URL for the request.
	 * @param Wcsdm_Request_Params  $request_params   Query parameters to append to URL.
	 * @param Wcsdm_Request_Headers $request_headers  HTTP headers for the request.
	 * @param callable|null         $masking_callback Optional callback to mask sensitive data in logs.
	 *
	 * @return Wcsdm_Request_Dispatcher Dispatcher instance with response data.
	 */
	public static function get(
		string $request_url,
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers,
		?callable $masking_callback = null
	): Wcsdm_Request_Dispatcher {
		// Create new dispatcher instance for GET request.
		$dispatcher = new self(
			'GET',
			$request_url,
			$request_params,
			$request_headers,
			$masking_callback
		);

		// Execute the request immediately.
		$dispatcher->dispatch();

		return $dispatcher;
	}

	/**
	 * Create and execute a POST request.
	 *
	 * Factory method that creates a new dispatcher instance, executes a POST request
	 * with JSON-encoded body, and returns the dispatcher with populated response data.
	 *
	 * @since 3.0
	 *
	 * @param string                $request_url      Full URL for the request.
	 * @param Wcsdm_Request_Params  $request_params   Parameters to send in request body as JSON.
	 * @param Wcsdm_Request_Headers $request_headers  HTTP headers for the request.
	 * @param callable|null         $masking_callback Optional callback to mask sensitive data in logs.
	 *
	 * @return Wcsdm_Request_Dispatcher Dispatcher instance with response data.
	 */
	public static function post(
		string $request_url,
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers,
		?callable $masking_callback = null
	): Wcsdm_Request_Dispatcher {
		// Create new dispatcher instance for POST request.
		$dispatcher = new self(
			'POST',
			$request_url,
			$request_params,
			$request_headers,
			$masking_callback
		);

		// Execute the request immediately.
		$dispatcher->dispatch();

		return $dispatcher;
	}

	/**
	 * Execute the HTTP request and populate response properties.
	 *
	 * Handles both GET and POST requests using WordPress HTTP API.
	 * Automatically parses JSON responses and applies filters for customization.
	 *
	 * @since 3.0
	 */
	private function dispatch() {
		// Base request arguments with default timeout and headers.
		$request_args = array(
			'timeout' => 10,
			'headers' => $this->request_headers->get_headers(),
		);

		if ( 'GET' === $this->request_method ) {
			$request_url    = $this->request_url;
			$request_params = $this->request_params->get_params();

			// Append request data as query string parameters if present.
			if ( $request_params ) {
				$request_url = add_query_arg(
					$request_params,
					$request_url
				);
			}

			/**
			 * Filters the GET request URL before execution.
			 *
			 * Allows modification of the complete URL with query parameters before the
			 * HTTP GET request is dispatched to the distance calculation API.
			 *
			 * @since 3.0
			 *
			 * @param string $request_url Full URL with query parameters.
			 */
			$request_url = apply_filters( 'wcsdm_request_dispatcher_get_request_url', $request_url );

			/**
			 * Filters the GET request arguments before execution.
			 *
			 * Allows modification of request arguments such as timeout, headers, and other
			 * wp_remote_get() parameters before the request is dispatched.
			 *
			 * @since 3.0
			 *
			 * @param array $request_args Request arguments including timeout and headers.
			 */
			$request_args = apply_filters( 'wcsdm_request_dispatcher_get_request_args', $request_args );

			// Execute the GET request using WordPress HTTP API.
			$this->response = wp_remote_get( $request_url, $request_args );
		} else {
			// Handle POST request with JSON body.
			$request_url    = $this->request_url;
			$request_params = $this->request_params->get_params();

			// Prepare POST request body as JSON if data is present.
			if ( $request_params ) {
				$request_args['body'] = wp_json_encode( $request_params );
			}

			/**
			 * Filters the POST request URL before execution.
			 *
			 * Allows modification of the complete URL before the HTTP POST request
			 * is dispatched to the distance calculation API.
			 *
			 * @since 3.0
			 *
			 * @param string $request_url Full URL for the POST request.
			 */
			$request_url = apply_filters( 'wcsdm_request_dispatcher_post_request_url', $request_url );

			/**
			 * Filters the POST request arguments before execution.
			 *
			 * Allows modification of request arguments such as timeout, headers, body, and
			 * other wp_remote_post() parameters before the request is dispatched.
			 *
			 * @since 3.0
			 *
			 * @param array $request_args Request arguments including timeout, headers, and body.
			 */
			$request_args = apply_filters( 'wcsdm_request_dispatcher_post_request_args', $request_args );

			// Execute the POST request using WordPress HTTP API.
			$this->response = wp_remote_post( $request_url, $request_args );
		}

		/*
		 * Extract response components using WordPress HTTP API helper functions.
		 * These functions safely handle both successful responses and WP_Error objects,
		 * returning empty values when errors occur.
		 */
		$this->response_code    = wp_remote_retrieve_response_code( $this->response );
		$this->response_headers = wp_remote_retrieve_headers( $this->response );
		$this->response_body    = wp_remote_retrieve_body( $this->response );

		/*
		 * Attempt to parse JSON response body. Set to null if parsing fails.
		 * This allows the response to be accessed as an array for easier data extraction.
		 * Non-JSON responses (HTML, XML, plain text) will gracefully fall back to null,
		 * in which case use get_response_body() to access the raw response.
		 */
		try {
			$this->response_body_json = json_decode( $this->response_body, true );
		} catch ( JsonException $e ) {
			// Non-JSON responses or malformed JSON will result in null.
			$this->response_body_json = null;
		}
	}

	/**
	 * Check if the request resulted in an error.
	 *
	 * @since 3.0
	 *
	 * @return bool True if request failed with WP_Error, false otherwise.
	 */
	public function is_error() : bool {
		return is_wp_error( $this->response );
	}

	/**
	 * Get the raw response object.
	 *
	 * @since 3.0
	 *
	 * @return WP_Error|array Raw response from wp_remote_get/post or WP_Error on failure.
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Get the HTTP response status code.
	 *
	 * @since 3.0
	 *
	 * @return int|null Response code as integer or null if not available.
	 */
	public function get_response_code():?int {
		if ( is_numeric( $this->response_code ) ) {
			return (int) $this->response_code;
		}

		return null;
	}

	/**
	 * Get the response headers.
	 *
	 * @since 3.0
	 *
	 * @return array Associative array of response headers.
	 */
	public function get_response_headers() {
		return $this->response_headers;
	}

	/**
	 * Get the raw response body.
	 *
	 * @since 3.0
	 *
	 * @return string Raw response body as string.
	 */
	public function get_response_body() {
		return $this->response_body;
	}

	/**
	 * Get the parsed JSON response body.
	 *
	 * @since 3.0
	 *
	 * @return array|null Decoded JSON response as array, or null if not JSON or parsing failed.
	 */
	public function get_response_body_json() {
		return $this->response_body_json;
	}

	/**
	 * Get a specific item from the JSON response body using dot notation.
	 *
	 * @since 3.0
	 *
	 * @param array $path    Array path to the desired item (e.g., ['data', 'user', 'name']).
	 * @param mixed $fallback_value Optional. Default value to return if path not found. Default null.
	 *
	 * @return mixed The value at the specified path or default if not found.
	 */
	public function get_response_body_json_item( array $path, $fallback_value = null ) {
		return wcsdm_array_get( $this->response_body_json ?? array(), $path, $fallback_value );
	}

	/**
	 * Get complete request and response data for debugging.
	 *
	 * Returns a comprehensive array containing all request details
	 * (method, URL, headers, params) and response details (code, headers, body).
	 * Automatically applies masking callback if provided to protect sensitive data
	 * like API keys and tokens.
	 *
	 * @since 3.0
	 *
	 * @return array {
	 *     Complete request and response data.
	 *
	 *     @type array $request {
	 *         Request information.
	 *
	 *         @type string $method  HTTP method (GET or POST).
	 *         @type string $url     Full request URL.
	 *         @type array  $headers Request headers.
	 *         @type array  $params  Request parameters.
	 *     }
	 *     @type array $response {
	 *         Response information.
	 *
	 *         @type int|string $code    HTTP response code.
	 *         @type array      $headers Response headers.
	 *         @type mixed      $body    Response body (parsed JSON array or raw string).
	 *     }
	 * }
	 */
	public function to_array():array {
		// Compile request information.
		$request = array(
			'method'  => $this->request_method,
			'url'     => $this->request_url,
			'params'  => $this->request_params->get_params(),
			'headers' => $this->request_headers->get_headers(),
		);

		// Compile response information, prefer JSON parsed body if available.
		$response = array(
			'code'      => $this->response_code ?? null,
			'headers'   => $this->response_headers ?? null,
			'body'      => $this->response_body ?? null,
			'body_json' => $this->response_body_json ?? null,
		);

		$vars = array(
			'request'  => $request,
			'response' => $response,
		);

		// Apply masking callback if provided to protect sensitive data like API keys.
		if ( is_callable( $this->masking_callback ) ) {
			$vars = wcsdm_array_map_deep(
				$vars,
				$this->masking_callback
			);
		}

		return $vars;
	}

	/**
	 * Create a dispatcher instance from an array of request/response data.
	 *
	 * Factory method that reconstructs a dispatcher object from serialized data,
	 * typically used for caching or logging purposes. This bypasses the actual
	 * HTTP request execution and directly populates the instance with stored data.
	 *
	 * @since 3.0
	 *
	 * @param array $vars {
	 *     Array containing request and response data.
	 *
	 *     @type array $request {
	 *         Request information.
	 *
	 *         @type string $method  HTTP method (GET or POST).
	 *         @type string $url     Full request URL.
	 *         @type array  $params  Request parameters.
	 *         @type array  $headers Request headers.
	 *     }
	 *     @type array $response {
	 *         Response information.
	 *
	 *         @type int|string $code      HTTP response code.
	 *         @type array      $headers   Response headers.
	 *         @type string     $body      Raw response body.
	 *         @type array|null $body_json Optional. Parsed JSON response body.
	 *     }
	 * }
	 *
	 * @return Wcsdm_Request_Dispatcher Dispatcher instance populated with provided data.
	 */
	public static function from_array( array $vars ): Wcsdm_Request_Dispatcher {
		$dispatcher = new self(
			$vars['request']['method'],
			$vars['request']['url'],
			new Wcsdm_Request_Params( $vars['request']['params'] ),
			new Wcsdm_Request_Headers( $vars['request']['headers'] )
		);

		$dispatcher->response_code      = $vars['response']['code'];
		$dispatcher->response_headers   = $vars['response']['headers'];
		$dispatcher->response_body      = $vars['response']['body'];
		$dispatcher->response_body_json = $vars['response']['body_json'];

		return $dispatcher;
	}
}
