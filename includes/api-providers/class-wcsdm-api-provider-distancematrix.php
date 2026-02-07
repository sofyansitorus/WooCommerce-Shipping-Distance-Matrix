<?php
/**
 * DistanceMatrix.ai API Provider for WooReer
 *
 * This file contains the Wcsdm_API_Provider_Distancematrix class which implements
 * distance calculation using the DistanceMatrix.ai API for the WooReer plugin.
 * It provides integration with DistanceMatrix.ai's routing service, supporting
 * multiple travel modes (driving, walking, bicycling, transit) and offering both
 * accurate and fast calculation APIs.
 *
 * Key Features:
 * - Support for multiple travel modes (driving, walking, bicycling, transit)
 * - Dual API types: Accurate (precise calculations) and Fast (optimized for speed)
 * - API key validation during configuration
 * - Comprehensive error handling and logging
 * - Support for address strings, address arrays, and coordinate-based locations
 * - Automatic sensitive data masking in logs (API key protection)
 * - Simple integration with minimal setup requirements
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DistanceMatrix.ai API provider for distance calculation.
 *
 * This class implements the Wcsdm_API_Provider_Interface to provide distance
 * calculation functionality using DistanceMatrix.ai's REST API. It supports
 * both address-based and coordinate-based calculations with various travel
 * modes (driving, walking, bicycling, transit) and offers two application
 * types: Accurate for precise calculations and Fast for optimized performance.
 *
 * The provider validates API keys during configuration and handles all API
 * communication including request formatting, response parsing, and error
 * handling. It uses the Wcsdm_Request_Dispatcher for HTTP operations and
 * returns standardized Wcsdm_Calculate_Distance_Result objects.
 *
 * Implementation Details:
 * - Extends Wcsdm_API_Provider_Base for common provider functionality
 * - Implements Wcsdm_API_Provider_Interface for standardized provider behavior
 * - Uses DistanceMatrix.ai API with JSON response format
 * - Supports field masking for API key security in logs
 * - Includes request/response logging with sensitive data protection
 *
 * @since 3.0
 */
class Wcsdm_API_Provider_Distancematrix extends Wcsdm_API_Provider_Base {

	/**
	 * Constructor.
	 *
	 * Initializes the DistanceMatrix.ai API provider by setting up settings fields
	 * and registering the option validation hook.
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->init_settings_fields();

		// Hook into the option validation process to validate settings.
		add_action( 'wcsdm_validate_option', array( $this, 'validate_option' ), 10, 3 );
	}

	/**
	 * Initialize settings fields configuration for the DistanceMatrix.ai provider.
	 *
	 * @since  3.0
	 */
	private function init_settings_fields() {
		$this->settings_fields = array(
			// DistanceMatrix.ai API Key - Required for authentication with the service.
			'api_key'          => array(
				'title'                  => __( 'API Key', 'wcsdm' ),
				'type'                   => 'text',
				'description'            => __( 'DistanceMatrix.ai API key for distance calculation.', 'wcsdm' ),
				'default'                => '',
				'is_required'            => true,
				'documentation'          => 'https://distancematrix.ai/guides/getting-started-with-distance-matrix-apis',

				// API Request mapping - This field value is sent as 'key' parameter in API requests.
				'api_request_params_key' => 'key',
			),
			// Travel Mode - Determines the type of route calculation (driving, walking, etc.).
			'mode'             => array(
				'title'                  => __( 'Travel Mode', 'wcsdm' ),
				'type'                   => 'select',
				'description'            => __( 'Specify the mode of travel.', 'wcsdm' ),
				'default'                => 'driving',
				'options'                => array(
					'driving'   => 'driving',
					'walking'   => 'walking',
					'bicycling' => 'bicycling',
					'transit'   => 'transit',
				),
				'documentation'          => 'https://distancematrix.ai/distance-matrix-api#travel_modes',

				// API Request mapping.
				'api_request_params_key' => 'mode',
			),
			// Application Type - Selects between accurate (precise) or fast (optimized) API.
			'application_type' => array(
				'title'         => __( 'Application Type', 'wcsdm' ),
				'type'          => 'select',
				'description'   => __( 'Specify which API is right for you.', 'wcsdm' ),
				'default'       => 'accurate',
				'options'       => array(
					'accurate' => __( 'Distance Matrix API Accurate', 'wcsdm' ),
					'fast'     => __( 'Distance Matrix API Fast', 'wcsdm' ),
				),
				'is_required'   => true,
				'documentation' => 'https://distancematrix.ai/product#how-dm-works',
			),
		);
	}

	/**
	 * Validates provider-specific options.
	 *
	 * This method is hooked to the 'wcsdm_validate_option' action and validates
	 * options specific to the DistanceMatrix.ai provider, particularly the API key.
	 * It performs a test API request using predefined test coordinates to ensure the
	 * API key is valid and has proper access to the DistanceMatrix.ai service.
	 *
	 * The validation only runs when:
	 * - The option key is 'api_provider'
	 * - The provider being selected is 'distancematrix'
	 * - An API key has been provided
	 *
	 * If the API key is empty, validation is skipped as it will be caught by the
	 * required field validation. If the test request fails, an exception is thrown
	 * to prevent saving invalid configuration.
	 *
	 * Uses predefined test coordinates to validate the API connection without
	 * requiring actual customer data.
	 *
	 * @since 3.0
	 *
	 * @param mixed                 $value    The option value being validated.
	 * @param string                $key      The option key being validated.
	 * @param Wcsdm_Shipping_Method $instance The shipping method instance.
	 *
	 * @throws Exception If API validation fails with error message from API response.
	 */
	public function validate_option( $value, string $key, Wcsdm_Shipping_Method $instance ) {
		// Only validate when this provider is being selected.
		if ( 'api_provider' !== $key || $value !== $this->get_slug() ) {
			return;
		}

		// Get the API key from the submitted form data.
		$api_key = $instance->get_post_data_value( $this->get_field_key( 'api_key' ), '' );

		// Bail early if no API key provided.
		// It will be caught by required field validation.
		if ( '' === $api_key ) {
			return;
		}

		$request_params   = $this->populate_request_params( $instance, 'settings' );
		$request_headers  = $this->populate_request_headers( $instance, 'settings' );
		$application_type = $instance->get_post_data_value( $this->get_field_key( 'application_type' ), 'accurate' );

		// Perform a test request to validate the API key by calculating distance between test coordinates.
		// This verifies that: 1) API key is valid, 2) Service is accessible, 3) Request format is correct.
		$result = $this->dispatch_request(
			Wcsdm_Location::from_coordinates( WCSDM_TEST_DESTINATION_LAT, WCSDM_TEST_DESTINATION_LNG ),
			Wcsdm_Location::from_coordinates( WCSDM_TEST_ORIGIN_LAT, WCSDM_TEST_ORIGIN_LNG ),
			$request_params,
			$request_headers,
			$application_type
		);

		// Throw an exception if the test request failed.
		if ( $result->is_error() ) {
			$instance->maybe_write_log( 'error', $result->get_error(), $result->get_dispatcher()->to_array() );

			if ( $instance->is_log_enabled() ) {
				throw new Exception( trim( $result->get_error(), '.' ) . ' » ' . __( 'Check the log for more details.', 'wcsdm' ) );
			} else {
				throw new Exception( $result->get_error() );
			}
		}
	}

	/**
	 * Get the provider slug.
	 *
	 * Returns a unique identifier for this provider used internally by WooReer
	 * for option storage, form field naming, and provider selection.
	 *
	 * The slug is used in:
	 * - Database option keys (e.g., 'wcsdm_distancematrix_api_key')
	 * - Form field IDs and names
	 * - Provider selection comparisons
	 * - Settings validation and retrieval
	 * - Hook naming and filtering
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return string The provider slug 'distancematrix'.
	 */
	public function get_slug():string {
		return 'distancematrix';
	}

	/**
	 * Get the provider display name.
	 *
	 * Returns the human-readable name of the provider displayed in the
	 * WooReer admin interface, particularly in the provider selection dropdown.
	 *
	 * The name is translatable and should be clear to end users. It appears in:
	 * - Provider selection dropdown in shipping method settings
	 * - Admin notices and error messages
	 * - Documentation and help text
	 * - Logs and debug output
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return string The localized provider display name 'Distance Matrix API by DistanceMatrix.ai'.
	 */
	public function get_name():string {
		return __( 'Distance Matrix API by DistanceMatrix.ai', 'wcsdm' );
	}

	/**
	 * Calculate the distance between two locations using DistanceMatrix.ai API.
	 *
	 * This is the main public method for distance calculation using the DistanceMatrix.ai
	 * provider. It retrieves the stored API key and application type from the shipping
	 * method instance and dispatches a request to the DistanceMatrix.ai API to calculate
	 * the route distance between the origin and destination locations.
	 *
	 * The method uses the shipping method's configured settings (travel mode, application
	 * type) to customize the route calculation according to the user's preferences. It
	 * supports both address strings, structured address arrays, and geographic coordinates.
	 *
	 * Calculation Flow:
	 * 1. Populates request parameters from shipping method settings (API key, mode)
	 * 2. Populates request headers (if any custom headers are needed)
	 * 3. Retrieves application type setting (accurate or fast)
	 * 4. Dispatches request to DistanceMatrix.ai API via dispatch_request()
	 * 5. Returns standardized result object with distance or error
	 *
	 * Location Format Support:
	 * - Address strings (e.g., "123 Main St, City, State, ZIP")
	 * - Address arrays (WooCommerce address format with address_1, city, state, postcode, country)
	 * - Geographic coordinates (latitude/longitude pairs for precise locations)
	 *
	 * Application Types:
	 * - 'accurate': Uses Distance Matrix API Accurate for precise calculations
	 * - 'fast': Uses Distance Matrix API Fast for optimized performance
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param  Wcsdm_Location        $destination The destination location object (address or coordinates).
	 * @param  Wcsdm_Location        $origin      The origin location object (address or coordinates).
	 * @param  Wcsdm_Shipping_Method $instance    The shipping method instance containing the settings and configuration.
	 *
	 * @return Wcsdm_Calculate_Distance_Result Result object containing either the calculated
	 *                                          distance in meters or an error message with details.
	 *
	 * @see    dispatch_request() For the actual API communication logic.
	 * @see    populate_request_params() For request parameter population from settings.
	 * @see    populate_request_headers() For request header population.
	 * @see    Wcsdm_API_Provider_Base::populate_request_params() Parent method for base parameter handling.
	 * @see    Wcsdm_API_Provider_Base::populate_request_headers() Parent method for base header handling.
	 */
	public function calculate_distance(
		Wcsdm_Location $destination,
		Wcsdm_Location $origin,
		Wcsdm_Shipping_Method $instance
	): Wcsdm_Calculate_Distance_Result {
		$request_params = $this->populate_request_params( $instance, 'calculation' );

		$request_headers = $this->populate_request_headers( $instance, 'calculation' );

		$application_type = $instance->get_option( $this->get_field_key( 'application_type' ), 'accurate' );

		return $this->dispatch_request(
			$destination,
			$origin,
			$request_params,
			$request_headers,
			$application_type
		);
	}

	/**
	 * Dispatches a request to the DistanceMatrix.ai API to calculate distance.
	 *
	 * This private method handles the core API communication with DistanceMatrix.ai.
	 * It formats the request with origin and destination locations, selects the
	 * appropriate API endpoint based on application type (accurate or fast), dispatches
	 * the HTTP GET request, and processes the response to extract distance information.
	 *
	 * The method handles both successful responses and various error conditions:
	 * - HTTP-level errors (network issues, timeouts, DNS failures)
	 * - API-level errors (invalid API key, quota exceeded, no route found)
	 * - Missing or invalid response data
	 * - Malformed JSON responses
	 *
	 * API Endpoints:
	 * - Accurate API: https://api.distancematrix.ai/maps/api/distancematrix/json
	 * - Fast API: https://api-v2.distancematrix.ai/maps/api/distancematrix/json
	 *
	 * Request Parameters:
	 * - origins: Origin location (coordinates or address)
	 * - destinations: Destination location (coordinates or address)
	 * - key: API key for authentication
	 * - mode: Travel mode (driving, walking, bicycling, transit)
	 *
	 * Security Features:
	 * - API key is automatically masked in request logs via masking_callback()
	 * - All sensitive data is sanitized before logging
	 * - Request/response data is logged for debugging purposes
	 *
	 * Expected Success Response:
	 * {
	 *   "rows": [
	 *     {
	 *       "elements": [
	 *         {
	 *           "distance": {
	 *             "text": "1.2 km",
	 *             "value": 1234
	 *           },
	 *           "duration": {
	 *             "text": "5 mins",
	 *             "value": 300
	 *           },
	 *           "status": "OK"
	 *         }
	 *       ]
	 *     }
	 *   ],
	 *   "status": "OK"
	 * }
	 *
	 * Expected Error Response:
	 * {
	 *   "status": "INVALID_REQUEST",
	 *   "error_message": "Invalid API key or missing parameters"
	 * }
	 *
	 * Common Error Statuses:
	 * - INVALID_REQUEST: Invalid parameters or missing required fields
	 * - REQUEST_DENIED: Invalid API key or insufficient permissions
	 * - OVER_QUERY_LIMIT: API quota exceeded
	 * - ZERO_RESULTS: No route found between locations
	 * - UNKNOWN_ERROR: Server error from DistanceMatrix.ai
	 *
	 * @since  3.0
	 * @access private
	 *
	 * @param  Wcsdm_Location        $destination      The destination location to calculate distance to.
	 * @param  Wcsdm_Location        $origin           The origin location to calculate distance from.
	 * @param  Wcsdm_Request_Params  $request_params   Pre-populated request parameters (API key, mode).
	 * @param  Wcsdm_Request_Headers $request_headers  Pre-populated request headers.
	 * @param  string                $application_type Application type ('accurate' or 'fast').
	 *
	 * @return Wcsdm_Calculate_Distance_Result Result object containing either:
	 *                                          - Success: Wcsdm_Distance object with distance in meters
	 *                                          - Error: Error message with details from API or HTTP layer
	 *
	 * @uses   format_location() To convert location objects to API-compatible format.
	 * @uses   Wcsdm_Request_Dispatcher::get() To dispatch the HTTP GET request.
	 * @uses   masking_callback() To mask sensitive data in logs.
	 *
	 * @link   https://distancematrix.ai/dev/distance-matrix-api API Endpoint Documentation
	 * @link   https://distancematrix.ai/product#how-dm-works Application Type Comparison
	 */
	private function dispatch_request(
		Wcsdm_Location $destination,
		Wcsdm_Location $origin,
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers,
		string $application_type
	): Wcsdm_Calculate_Distance_Result {
		$request_params->add_param(
			$this->format_location( $origin ),
			'origins',
		);

		$request_params->add_param(
			$this->format_location( $destination ),
			'destinations',
		);

		// Select API endpoint based on application type.
		if ( 'fast' === $application_type ) {
			$request_url = 'https://api-v2.distancematrix.ai/maps/api/distancematrix/json';
		} else {
			$request_url = 'https://api.distancematrix.ai/maps/api/distancematrix/json';
		}

		// Create and dispatch the HTTP GET request.
		// Select the appropriate API endpoint based on application type:
		// - Fast API uses api-v2.distancematrix.ai for optimized performance.
		// - Accurate API uses api.distancematrix.ai for precise calculations.
		$dispatcher = Wcsdm_Request_Dispatcher::get(
			$request_url,
			$request_params,
			$request_headers,
			array( $this, 'masking_callback' )
		);

		// Get the HTTP response.
		$response = $dispatcher->get_response();

		// Handle HTTP-level errors (network issues, timeouts, etc.).
		if ( is_wp_error( $response ) ) {
			return Wcsdm_Calculate_Distance_Result::error(
				$response->get_error_message(),
				$dispatcher
			);
		}

		// Extract distance in meters from the first route in the response.
		$distance_in_meters = (int) $dispatcher->get_response_body_json_item( array( 'rows', 0, 'elements', 0, 'distance', 'value' ) );

		if ( $distance_in_meters ) {
			return Wcsdm_Calculate_Distance_Result::distance(
				Wcsdm_Distance::from_m( (string) $distance_in_meters ),
				$dispatcher
			);
		}

		// Return error result with API error message or generic fallback.
		return Wcsdm_Calculate_Distance_Result::error(
			$dispatcher->get_response_body_json_item( array( 'error_message' ), __( 'API request failed.', 'wcsdm' ) ),
			$dispatcher
		);
	}

	/**
	 * Formats a location object into the format required by the DistanceMatrix.ai API.
	 *
	 * Converts a Wcsdm_Location object into a string format that matches the
	 * DistanceMatrix.ai API request format. The method supports three types of
	 * location formats:
	 *
	 * 1. 'address': Single address string
	 * 2. 'address_array': Structured address components (converted to formatted string)
	 * 3. 'coordinates': Geographic coordinates (latitude and longitude)
	 *
	 * For coordinate-based locations, the method formats them as "lat,lng" strings.
	 * For address-based locations, it provides the address as a string. Address arrays
	 * are converted to a comma-separated formatted address string using WooCommerce's
	 * country formatting.
	 *
	 * Location Type Priority:
	 * - Coordinates are formatted as "lat,lng" (most precise, no geocoding ambiguity)
	 * - Address arrays are formatted using locale-specific rules
	 * - Address strings are passed directly to the API
	 *
	 * Example Outputs:
	 *
	 * Address String:
	 * ```
	 * "1600 Amphitheatre Parkway, Mountain View, CA 94043"
	 * ```
	 *
	 * Address Array:
	 * ```
	 * // Input: ['address_1' => '123 Main St', 'city' => 'New York', 'state' => 'NY', 'postcode' => '10001', 'country' => 'US']
	 * // Output: "123 Main St, New York, NY, 10001, United States"
	 * ```
	 *
	 * Coordinates:
	 * ```
	 * // Input: lat=37.4224764, lng=-122.0842499
	 * // Output: "37.4224764,-122.0842499"
	 * ```
	 *
	 * @since  3.0
	 * @access private
	 *
	 * @param  Wcsdm_Location $location The location object to format (must have a valid location type).
	 *
	 * @return string Formatted location string for the API request. Returns either:
	 *                - Address string for address-based locations, or
	 *                - "lat,lng" coordinate string for coordinate-based locations.
	 *
	 * @uses   Wcsdm_Location::get_location_type() To determine the location type.
	 * @uses   Wcsdm_Location::get_address() To get the address string.
	 * @uses   Wcsdm_Location::get_address_array() To get the structured address.
	 * @uses   Wcsdm_Location::get_coordinates_latitude() To get the latitude.
	 * @uses   Wcsdm_Location::get_coordinates_longitude() To get the longitude.
	 * @uses   wcsdm_format_address_array() For locale-aware address array formatting.
	 */
	private function format_location( Wcsdm_Location $location ):string {
		// Format location based on type: address, address_array, or coordinates.
		switch ( $location->get_location_type() ) {
			// Simple address string format - used when location is provided as a single string.
			case 'address':
				return $location->get_address();

			// Structured address array format - converts WooCommerce address components
			// into a formatted string using country-specific formatting rules.
			case 'address_array':
				return wcsdm_format_address_array( $location->get_address_array() );

			// Geographic coordinates format (default) - most precise location format.
			// Used when latitude/longitude are available. Output format: "lat,lng".
			case 'coordinates':
			default:
				return $location->get_coordinates_latitude() . ',' . $location->get_coordinates_longitude();
		}
	}

	/**
	 * Callback function to mask sensitive data in request logs.
	 *
	 * This method is used as a callback to sanitize sensitive information in API request
	 * logs before they are stored or displayed. It specifically masks the API key
	 * parameter to prevent accidental exposure in logs or debug output.
	 *
	 * The method checks if the current path in the data structure ends with '.key'
	 * (the API key parameter name) and if so, applies deep masking to the value using
	 * the wcsdm_mask_string function. This ensures that API keys are replaced with
	 * masked versions (e.g., "abc***xyz") in logs.
	 *
	 * Security Benefits:
	 * - Prevents API key exposure in error logs
	 * - Protects sensitive data in debug output
	 * - Maintains audit trail without compromising security
	 * - Allows safe sharing of logs for support purposes
	 * - Complies with security best practices for credential handling
	 *
	 * Masking Pattern:
	 * - Original: "AIzaSyBc123456789DefGhiJklMnoPqRsTuVwXyZ"
	 * - Masked:   "AIza***XyZ" (shows first 4 and last 3 characters)
	 * - Shorter strings are masked proportionally
	 *
	 * Example Path Matching:
	 * - ['params', 'key'] → Matched and masked
	 * - ['request', 'params', 'key'] → Matched and masked
	 * - ['body', 'api_key'] → Not matched (different field name)
	 * - ['headers', 'Authorization'] → Not matched (different field name)
	 *
	 * Integration:
	 * - Called by Wcsdm_Request_Dispatcher during request logging
	 * - Applied to all log contexts (request, response, error)
	 * - Works with nested data structures via map_deep()
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @param  mixed $value The value to potentially mask. Can be any type but typically a string.
	 *                      Arrays and objects are recursively processed.
	 * @param  array $path  The path to the current value in the data structure. Used to identify
	 *                      which fields should be masked. Example: ['params', 'key'].
	 *
	 * @return mixed Returns the masked value if the path matches a sensitive field pattern,
	 *               otherwise returns the original value unchanged. For arrays/objects,
	 *               returns a recursively masked copy.
	 *
	 * @uses   wcsdm_str_ends_with() To check if path ends with the sensitive field name.
	 * @uses   map_deep() To apply masking recursively to nested arrays and objects.
	 * @uses   wcsdm_mask_string() To perform the actual string masking operation.
	 */
	public function masking_callback( $value, array $path ) {
		// Join the path array into a dot-notation string for easy pattern matching.
		$path_joined = implode( '.', $path );

		// Check if this path represents the API key parameter and mask it if so.
		if ( wcsdm_str_ends_with( $path_joined, '.key' ) ) {
			// Apply masking recursively to handle nested structures (arrays/objects).
			return map_deep( $value, 'wcsdm_mask_string' );
		}

		// Return the original value unchanged if it's not a sensitive field.
		return $value;
	}
}
