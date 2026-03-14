<?php
/**
 * HERE Routing API Provider for WooReer Distance Matrix
 *
 * This file contains the Wcsdm_API_Provider_Here class which implements
 * distance calculation using the HERE Routing API v8 for the WooReer plugin.
 * It provides integration with HERE's routing service, supporting multiple
 * transport modes (car, pedestrian, bicycle, truck, scooter, taxi, bus) with
 * built-in geocoding via HERE Geocoding API for address conversion.
 *
 * Key Features:
 * - Support for multiple transport modes (car, pedestrian, bicycle, truck, scooter, taxi, bus)
 * - Built-in geocoding via HERE Geocoding & Search API for address conversion
 * - API key validation during configuration
 * - Comprehensive error handling and logging
 * - Support for address strings, address arrays, and coordinate-based locations
 * - Automatic sensitive data masking in logs (API key protection)
 * - Distance returned in meters from route summary
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.1.4
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 *
 * @see        Wcsdm_API_Provider_Base For base provider functionality
 * @see        https://developer.here.com/documentation/routing-api HERE Routing API Documentation
 * @see        https://developer.here.com/documentation/geocoding-search-api HERE Geocoding API Documentation
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HERE Routing API provider for distance calculation.
 *
 * This class implements the Wcsdm_API_Provider_Interface to provide distance
 * calculation functionality using HERE Routing API v8. It supports both
 * address-based and coordinate-based calculations with various transport modes
 * (car, pedestrian, bicycle, truck, scooter, taxi, bus). The provider includes
 * automatic geocoding via HERE Geocoding & Search API for converting addresses
 * to coordinates.
 *
 * The provider validates API keys during configuration and handles all API
 * communication including request formatting, response parsing, and error
 * handling. It uses the Wcsdm_Request_Dispatcher for HTTP operations and
 * returns standardized Wcsdm_Calculate_Distance_Result objects with distances
 * in meters.
 *
 * Implementation Details:
 * - Extends Wcsdm_API_Provider_Base for common provider functionality
 * - Implements Wcsdm_API_Provider_Interface for standardized provider behavior
 * - Uses HERE Routing API v8 (https://router.hereapi.com/v8/routes)
 * - Uses HERE Geocoding & Search API v1 for address-to-coordinate conversion
 * - Coordinates format: latitude,longitude (comma-separated)
 * - Distance extracted from routes[0].sections[0].summary.length (in meters)
 * - Supports API key masking for security in logs
 *
 * @since 3.1.4
 */
class Wcsdm_API_Provider_Here extends Wcsdm_API_Provider_Base implements Wcsdm_API_Provider_Interface {

	/**
	 * Constructor.
	 *
	 * Initializes the HERE API provider by setting up settings fields and
	 * registering the validation hook for options.
	 *
	 * @since 3.1.4
	 */
	public function __construct() {
		$this->init_settings_fields();

		// Hook into the option validation process to validate settings.
		add_action( 'wcsdm_validate_option', array( $this, 'validate_option' ), 10, 3 );
	}

	/**
	 * Initialize settings fields for the HERE API provider.
	 *
	 * Defines the configuration fields required for the HERE Routing API v8
	 * integration, including the API key and transport mode. Each field includes
	 * validation rules, descriptions, and documentation links.
	 *
	 * @since 3.1.4
	 *
	 * @return void
	 */
	private function init_settings_fields():void {
		$this->settings_fields = array(
			// HERE API key field - required for all API authentication.
			'api_key'        => array(
				'title'                  => __( 'API Key', 'wcsdm' ),
				'type'                   => 'password',
				'description'            => __( 'API key with Routing API and Geocoding & Search API enabled.', 'wcsdm' ),
				'default'                => '',
				'is_required'            => true,
				'documentation'          => 'https://www.here.com/docs/bundle/routing-api-developer-guide-v8/page/get-started.html#get-an-api-key',
				'api_request_params_key' => 'apikey',
			),
			// Transport mode field - determines how routes are calculated.
			'transport_mode' => array(
				'title'                  => __( 'Transport Mode', 'wcsdm' ),
				'type'                   => 'select',
				'description'            => __( 'Mode of transport for route calculation. Each mode optimizes routes based on road restrictions and vehicle type.', 'wcsdm' ),
				'default'                => 'car',
				'options'                => array(
					'car'        => 'car',
					'truck'      => 'truck',
					'pedestrian' => 'pedestrian',
					'bicycle'    => 'bicycle',
					'scooter'    => 'scooter',
					'taxi'       => 'taxi',
					'bus'        => 'bus',
					'privateBus' => 'privateBus',
				),
				'is_required'            => true,
				'documentation'          => 'https://www.here.com/docs/bundle/routing-api-developer-guide-v8/page/concepts/transport-modes.html',
				'api_request_params_key' => 'transportMode',
			),
			// Route avoidance options - optional route restrictions.
			'avoid_features' => array(
				'title'                        => __( 'Route Avoidances', 'wcsdm' ),
				'type'                         => 'multiselect',
				'description'                  => __( 'Select road features to avoid when calculating routes.', 'wcsdm' ),
				'options'                      => array(
					'tollRoad'                => 'tollRoad',
					'ferry'                   => 'ferry',
					'uTurns'                  => 'uTurns',
					'dirtRoad'                => 'dirtRoad',
					'carShuttleTrain'         => 'carShuttleTrain',
					'seasonalClosure'         => 'seasonalClosure',
					'controlledAccessHighway' => 'controlledAccessHighway',
				),
				'select_buttons'               => true,
				'documentation'                => 'https://www.here.com/docs/bundle/routing-api-developer-guide-v8/page/tutorials/avoid.html',
				'api_request_params_key'       => 'avoid[features]',
				'api_request_params_sanitizer' => function( $selected_options ):?string {
					if ( $selected_options ) {
						return implode( ',', $selected_options );
					}
					return null;
				},
			),
		);
	}

	/**
	 * Validate provider-specific options during settings save.
	 *
	 * This method validates the HERE API key by performing a test API request
	 * using predefined test coordinates. It only runs when this provider is
	 * being selected to avoid unnecessary validation overhead.
	 *
	 * @since 3.1.4
	 *
	 * @param mixed                 $value    The value being validated.
	 * @param string                $key      The option key being validated.
	 * @param Wcsdm_Shipping_Method $instance The shipping method instance.
	 *
	 * @throws Exception If the API validation request fails.
	 *
	 * @return void
	 */
	public function validate_option( $value, string $key, Wcsdm_Shipping_Method $instance ) {
		// Only validate when this provider is being selected.
		if ( 'api_provider' !== $key || $value !== $this->get_slug() ) {
			return;
		}

		// Get the API key from POST data for validation.
		// We retrieve from POST data (not saved options) to validate the new value being submitted.
		$api_key = $instance->get_post_data_value( $this->get_field_key( 'api_key' ), '' );

		// Bail early if API key is empty.
		// It will be caught by the required field validation in the parent class.
		if ( wcsdm_is_empty_string( $api_key ) ) {
			return;
		}

		// Create test locations using predefined coordinates for validation.
		$destination = Wcsdm_Location::from_coordinates( WCSDM_TEST_DESTINATION_LAT, WCSDM_TEST_DESTINATION_LNG );
		$origin      = Wcsdm_Location::from_coordinates( WCSDM_TEST_ORIGIN_LAT, WCSDM_TEST_ORIGIN_LNG );

		// Populate request parameters from settings, injecting the route endpoints and return type.
		$request_params = $this->populate_request_params(
			$instance,
			'settings',
			array(
				'origin'      => $this->format_location( $origin ),
				'destination' => $this->format_location( $destination ),
				'return'      => 'summary',
			)
		);

		// Populate request headers from settings context.
		$request_headers = $this->populate_request_headers( $instance, 'settings' );

		// Perform a test API request to validate the credentials.
		$result = $this->dispatch_request( $request_params, $request_headers );

		// Throw an exception if the API request failed.
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
	 * Get the unique identifier for this API provider.
	 *
	 * @since 3.1.4
	 *
	 * @return string The provider slug.
	 */
	public function get_slug():string {
		return 'here';
	}

	/**
	 * Get the human-readable name for this API provider.
	 *
	 * @since 3.1.4
	 *
	 * @return string The localized provider name.
	 */
	public function get_name():string {
		return __( 'Routing API by HERE', 'wcsdm' );
	}

	/**
	 * Calculate distance between two locations using the HERE Routing API v8.
	 *
	 * This method handles the complete distance calculation workflow:
	 * 1. Retrieves the API key and transport mode from saved options
	 * 2. Geocodes address-based locations to coordinates if needed
	 * 3. Dispatches the API request to HERE Routing API
	 * 4. Returns a standardized distance result
	 *
	 * @since 3.1.4
	 *
	 * @param Wcsdm_Location        $destination The destination location (address or coordinates).
	 * @param Wcsdm_Location        $origin      The origin location (address or coordinates).
	 * @param Wcsdm_Shipping_Method $instance    The shipping method instance with saved settings.
	 *
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance or error.
	 */
	public function calculate_distance( Wcsdm_Location $destination, Wcsdm_Location $origin, Wcsdm_Shipping_Method $instance ):Wcsdm_Calculate_Distance_Result {
		// Retrieve the HERE API key from saved options (needed for geocoding).
		$api_key = $instance->get_option( $this->get_field_key( 'api_key' ) );

		// Convert address-based locations to coordinates if needed via HERE Geocoding API.
		$destination = $this->maybe_geocode_location( $destination, $api_key );
		$origin      = $this->maybe_geocode_location( $origin, $api_key );

		// Populate request parameters from saved settings, injecting the route endpoints and return type.
		$request_params = $this->populate_request_params(
			$instance,
			'calculation',
			array(
				'origin'      => $this->format_location( $origin ),
				'destination' => $this->format_location( $destination ),
				'return'      => 'summary',
			)
		);

		// Populate request headers for the calculation context.
		$request_headers = $this->populate_request_headers( $instance, 'calculation' );

		return $this->dispatch_request( $request_params, $request_headers );
	}

	/**
	 * Dispatch a request to the HERE Routing API v8.
	 *
	 * Executes the HTTP request against the HERE Routing API and parses the
	 * response to extract distance information from the route summary. Handles
	 * both successful responses and various error conditions.
	 *
	 * @since 3.1.4
	 *
	 * @param Wcsdm_Request_Params  $request_params  Query parameters including apikey, transportMode, origin, destination, return, and optional avoid[features].
	 * @param Wcsdm_Request_Headers $request_headers HTTP headers for the request.
	 *
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance in meters or error.
	 */
	private function dispatch_request(
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers
	):Wcsdm_Calculate_Distance_Result {
		// Create and configure the request dispatcher.
		$dispatcher = Wcsdm_Request_Dispatcher::get(
			'https://router.hereapi.com/v8/routes',
			$request_params,
			$request_headers,
			array( $this, 'masking_callback' )
		);

		// Execute the HTTP request and get the response from HERE API.
		$response = $dispatcher->get_response();

		// Check if the request failed at the HTTP level (network error, timeout, etc.).
		if ( is_wp_error( $response ) ) {
			return Wcsdm_Calculate_Distance_Result::error(
				$response->get_error_message(),
				$dispatcher
			);
		}

		// Extract distance in meters from the route summary.
		// HERE Routing API v8 response: routes[0].sections[0].summary.length (meters).
		$distance_in_meters = (int) $dispatcher->get_response_body_json_item( array( 'routes', 0, 'sections', 0, 'summary', 'length' ), 0 );

		if ( $distance_in_meters ) {
			return Wcsdm_Calculate_Distance_Result::distance(
				Wcsdm_Distance::from_m( (string) $distance_in_meters ),
				$dispatcher
			);
		}

		// Attempt to extract an error message from the response body.
		$error_message = $dispatcher->get_response_body_json_item(
			array( 'title' ),
			$dispatcher->get_response_body_json_item(
				array( 'error_description' ),
				__( 'API request failed.', 'wcsdm' )
			)
		);

		return Wcsdm_Calculate_Distance_Result::error(
			$error_message,
			$dispatcher
		);
	}

	/**
	 * Format a location object into HERE's coordinate string format.
	 *
	 * HERE Routing API requires coordinates in "latitude,longitude" format,
	 * which is the standard geographic coordinate order.
	 *
	 * @since 3.1.4
	 *
	 * @param Wcsdm_Location $location The location object with coordinates.
	 *
	 * @return string The formatted coordinate string (e.g., '-6.2088,106.8456').
	 */
	private function format_location( Wcsdm_Location $location ):string {
		return $location->get_coordinates_latitude() . ',' . $location->get_coordinates_longitude();
	}

	/**
	 * Convert address-based locations to coordinates using HERE Geocoding & Search API.
	 *
	 * If the location is provided as an address (string or array), this method
	 * geocodes it using the HERE Geocoding & Search API v1 to obtain coordinates.
	 * Locations already in coordinate format are returned unchanged.
	 *
	 * Supports two address types:
	 * - address_array: WooCommerce address array from checkout form
	 * - address: Plain address string from settings
	 *
	 * @since 3.1.4
	 *
	 * @param Wcsdm_Location $location The location to potentially geocode.
	 * @param string         $api_key  The HERE API key for authentication.
	 *
	 * @return Wcsdm_Location The location with coordinates (geocoded or original).
	 */
	private function maybe_geocode_location( Wcsdm_Location $location, string $api_key ):Wcsdm_Location {
		// Initialize variable to store the address string for geocoding.
		$address_to_geocode = '';

		// Check if location is provided as a WooCommerce address array (e.g., from checkout form).
		// Address arrays contain structured data like street, city, state, country, postal code.
		if ( $location->get_location_type() === 'address_array' ) {
			// Format the address array into a comma-separated string suitable for geocoding.
			// WooCommerce's formatter handles proper ordering and formatting based on country.
			$address_to_geocode = wcsdm_format_address_array( $location->get_address_array() );
		}

		// Check if location is provided as a plain address string (e.g., from settings).
		// String addresses are already formatted and ready to geocode.
		if ( $location->get_location_type() === 'address' ) {
			$address_to_geocode = $location->get_address();
		}

		// If we have an address to geocode, make the geocoding API request.
		if ( $address_to_geocode ) {
			// Create and configure the geocoding request dispatcher for HERE Geocoding API v1.
			$dispatcher = Wcsdm_Request_Dispatcher::get(
				// HERE Geocoding & Search API v1 forward geocoding endpoint.
				'https://geocode.search.hereapi.com/v1/geocode',
				// Request parameters: API key, address query, and limit to 1 result.
				new Wcsdm_Request_Params(
					array(
						'apikey' => $api_key,
						'q'      => $address_to_geocode,
						'limit'  => 1,
					)
				),
				// No special request headers needed for this endpoint.
				new Wcsdm_Request_Headers(),
				// Mask the API key in logs.
				array( $this, 'masking_callback' )
			);

			// Extract coordinates from the first matching result.
			// HERE Geocoding API response: items[0].position.lat and items[0].position.lng.
			$latitude  = $dispatcher->get_response_body_json_item( array( 'items', 0, 'position', 'lat' ) );
			$longitude = $dispatcher->get_response_body_json_item( array( 'items', 0, 'position', 'lng' ) );

			// If geocoding was successful and coordinates were found, return a new coordinate-based location.
			if ( is_numeric( $latitude ) && is_numeric( $longitude ) ) {
				return Wcsdm_Location::from_coordinates( (float) $latitude, (float) $longitude );
			}
		}

		// Return the original location if no geocoding was needed or if it failed.
		return $location;
	}

	/**
	 * Callback function to mask sensitive API key data in logs.
	 *
	 * This callback is used by the request dispatcher to mask sensitive information
	 * (the HERE API key) in request logs for security purposes. It checks if the
	 * data path ends with 'apikey' and applies masking if found.
	 *
	 * @since 3.1.4
	 *
	 * @param mixed $value The value to potentially mask.
	 * @param array $path  The data path as an array of keys.
	 *
	 * @return mixed The original value or masked version if it's the API key.
	 */
	public function masking_callback( $value, array $path ) {
		// Convert path array to dot-notation string for pattern matching.
		$path_joined = implode( '.', $path );

		// Check if the path represents the HERE API key.
		if ( wcsdm_str_ends_with( $path_joined, '.apikey' ) ) {
			// Mask the API key value for security.
			return map_deep( $value, 'wcsdm_mask_string' );
		}

		// Return the value unchanged if it's not sensitive data.
		return $value;
	}
}
