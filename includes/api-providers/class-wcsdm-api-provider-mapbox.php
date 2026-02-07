<?php
/**
 * Mapbox Matrix API Provider for WooReer
 *
 * This file contains the Wcsdm_API_Provider_Mapbox class which implements
 * distance calculation using the Mapbox Matrix API for the WooReer plugin.
 * It provides integration with Mapbox's routing service, supporting multiple
 * routing profiles (driving, driving-traffic, cycling, walking) with high-precision
 * distance calculations and optional geocoding capabilities.
 *
 * Key Features:
 * - Support for multiple routing profiles (driving, driving-traffic, cycling, walking)
 * - Built-in geocoding via Mapbox Geocoding API v6 for address conversion
 * - API access token validation during configuration
 * - Comprehensive error handling and logging
 * - Support for address strings, address arrays, and coordinate-based locations
 * - Automatic sensitive data masking in logs (access token protection)
 * - High-precision distance calculations in meters
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
 * Mapbox Matrix API provider for distance calculation.
 *
 * This class implements the Wcsdm_API_Provider_Interface to provide distance
 * calculation functionality using Mapbox's Matrix API. It supports both
 * address-based and coordinate-based calculations with various routing profiles
 * (driving, driving-traffic, cycling, walking). The provider includes automatic
 * geocoding via Mapbox Geocoding API v6 for converting addresses to coordinates.
 *
 * The provider validates access tokens during configuration and handles all API
 * communication including request formatting, response parsing, and error handling.
 * It uses the Wcsdm_Request_Dispatcher for HTTP operations and returns standardized
 * Wcsdm_Calculate_Distance_Result objects with distances in meters.
 *
 * Implementation Details:
 * - Extends Wcsdm_API_Provider_Base for common provider functionality
 * - Implements Wcsdm_API_Provider_Interface for standardized provider behavior
 * - Uses Mapbox Matrix API v1 for distance calculations
 * - Uses Mapbox Geocoding API v6 for address-to-coordinate conversion
 * - Coordinates format: longitude,latitude (Mapbox-specific order)
 * - Supports field masking for access token security in logs
 * - Includes request/response logging with sensitive data protection
 *
 * @since 3.0
 */
class Wcsdm_API_Provider_Mapbox extends Wcsdm_API_Provider_Base {

	/**
	 * Constructor.
	 *
	 * Initializes the Mapbox API provider by setting up settings fields and
	 * registering the validation hook for options.
	 *
	 * @since 3.0
	 */
	public function __construct() {
		$this->init_settings_fields();

		// Hook into the option validation process to validate settings.
		add_action( 'wcsdm_validate_option', array( $this, 'validate_option' ), 10, 3 );
	}

	/**
	 * Initialize settings fields for the Mapbox API provider.
	 *
	 * Defines the configuration fields required for the Mapbox Matrix API integration,
	 * including the access token and routing profile. Each field includes validation
	 * rules, descriptions, and documentation links.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	private function init_settings_fields():void {
		$this->settings_fields = array(
			// Mapbox access token field - required for API authentication.
			'access_token' => array(
				'title'                  => __( 'Access Token', 'wcsdm' ),
				'type'                   => 'text',
				'description'            => __( 'Access token with Matrix API and Geocoding API enabled.', 'wcsdm' ),
				'is_required'            => true,
				'documentation'          => 'https://docs.mapbox.com/help/dive-deeper/access-tokens',

				// API Request mapping.
				'api_request_params_key' => 'access_token',
			),
			// Routing profile field - determines how routes are calculated (driving, cycling, walking).
			'profile'      => array(
				'title'         => __( 'Routing Profile', 'wcsdm' ),
				'type'          => 'select',
				'description'   => __( 'Choose the routing profile that best matches your delivery method. Each profile optimizes routes differently based on vehicle type and road restrictions.', 'wcsdm' ),
				'default'       => 'mapbox/driving',
				'options'       => array(
					'mapbox/driving'         => 'mapbox/driving',
					'mapbox/driving-traffic' => 'mapbox/driving-traffic',
					'mapbox/cycling'         => 'mapbox/cycling',
					'mapbox/walking'         => 'mapbox/walking',
				),
				'is_required'   => true,
				'documentation' => 'https://docs.mapbox.com/api/navigation/matrix/#retrieve-a-matrix',
			),
		);
	}

	/**
	 * Validate provider-specific options during settings save.
	 *
	 * This method validates the Mapbox access token by performing a test API request
	 * using predefined test coordinates. It only runs when this provider is being selected
	 * to avoid unnecessary validation overhead.
	 *
	 * @since 3.0
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

		// Get the access token from POST data for validation.
		// We retrieve from POST data (not saved options) to validate the new value being submitted.
		$access_token = $instance->get_post_data_value( $this->get_field_key( 'access_token' ), '' );

		// Bail early if access token is empty.
		// It will be caught by the required field validation in the parent class.
		if ( '' === $access_token ) {
			return;
		}

		// Create test locations using predefined coordinates for validation.
		$destination = Wcsdm_Location::from_coordinates( WCSDM_TEST_DESTINATION_LAT, WCSDM_TEST_DESTINATION_LNG );
		$origin      = Wcsdm_Location::from_coordinates( WCSDM_TEST_ORIGIN_LAT, WCSDM_TEST_ORIGIN_LNG );

		// Populate request data from settings context.
		$request_params = $this->populate_request_params(
			$instance,
			'settings',
			array(
				'annotations' => 'distance',
			)
		);

		// Populate request headers from settings context.
		$request_headers = $this->populate_request_headers( $instance, 'settings' );

		// Get the routing profile from POST data, defaulting to 'mapbox/driving' if not set.
		$profile = $instance->get_post_data_value( $this->get_field_key( 'profile' ), 'mapbox/driving' );

		// Perform a test API request to validate the credentials.
		$result = $this->dispatch_request(
			$destination,
			$origin,
			$request_params,
			$request_headers,
			$profile
		);

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
	 * @since 3.0
	 *
	 * @return string The provider slug.
	 */
	public function get_slug():string {
		return 'mapbox';
	}

	/**
	 * Get the human-readable name for this API provider.
	 *
	 * @since 3.0
	 *
	 * @return string The localized provider name.
	 */
	public function get_name():string {
		return __( 'Matrix API by Mapbox', 'wcsdm' );
	}

	/**
	 * Calculate distance between two locations using the Mapbox Matrix API.
	 *
	 * This method handles the complete distance calculation workflow:
	 * 1. Retrieves the access token from saved options
	 * 2. Geocodes address-based locations to coordinates if needed
	 * 3. Prepares request parameters and headers
	 * 4. Dispatches the API request to Mapbox Matrix API
	 * 5. Returns a standardized distance result
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location        $destination The destination location (address or coordinates).
	 * @param Wcsdm_Location        $origin      The origin location (address or coordinates).
	 * @param Wcsdm_Shipping_Method $instance    The shipping method instance with saved settings.
	 *
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance or error.
	 */
	public function calculate_distance( Wcsdm_Location $destination, Wcsdm_Location $origin, Wcsdm_Shipping_Method $instance ):Wcsdm_Calculate_Distance_Result {
		// Retrieve the Mapbox access token from saved shipping method options.
		$access_token = $instance->get_option( $this->get_field_key( 'access_token' ) );

		// Convert address-based locations to coordinates if needed via Mapbox Geocoding API.
		$destination = $this->maybe_geocode_location( $destination, $access_token );
		$origin      = $this->maybe_geocode_location( $origin, $access_token );

		// Prepare request data for the calculation context.
		$request_data = $this->populate_request_params(
			$instance,
			'calculation',
			array(
				'annotations' => 'distance',
			)
		);

		// Prepare request headers for the calculation context.
		$request_headers = $this->populate_request_headers( $instance, 'calculation' );

		// Get the configured routing profile for distance calculation.
		$profile = $instance->get_option( $this->get_field_key( 'profile' ), 'mapbox/driving' );

		// Calculate distance using the Mapbox API.
		return $this->dispatch_request(
			$destination,
			$origin,
			$request_data,
			$request_headers,
			$profile
		);
	}

	/**
	 * Dispatch a request to the Mapbox Matrix API.
	 *
	 * Constructs the API endpoint URL with routing profile and coordinates, executes
	 * the HTTP request, and parses the response to extract distance information.
	 * Handles both successful responses and various error conditions.
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location        $destination     The destination location with coordinates.
	 * @param Wcsdm_Location        $origin          The origin location with coordinates.
	 * @param Wcsdm_Request_Params  $request_params  Query parameters including access token.
	 * @param Wcsdm_Request_Headers $request_headers HTTP headers for the request.
	 * @param string                $profile         The routing profile (e.g., 'mapbox/driving').
	 *
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance in meters or error.
	 */
	private function dispatch_request(
		Wcsdm_Location $destination,
		Wcsdm_Location $origin,
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers,
		string $profile
	):Wcsdm_Calculate_Distance_Result {
		// Construct the Mapbox Matrix API endpoint URL.
		// Format: https://api.mapbox.com/directions-matrix/v1/{profile}/{coordinates}
		// Coordinates are separated by semicolons: origin;destination.
		$endpoint = 'https://api.mapbox.com/directions-matrix/v1/' . $profile . '/' . $this->format_location( $origin ) . ';' . $this->format_location( $destination );

		// Create and configure the request dispatcher with endpoint, query params, and headers.
		$dispatcher = Wcsdm_Request_Dispatcher::get(
			$endpoint,
			$request_params,
			$request_headers,
			array( $this, 'masking_callback' )
		);

		// Execute the HTTP request and get the response from Mapbox API.
		$response = $dispatcher->get_response();

		// Check if the request failed at the HTTP level (network error, timeout, etc.).
		if ( is_wp_error( $response ) ) {
			return Wcsdm_Calculate_Distance_Result::error(
				$response->get_error_message(),
				$dispatcher
			);
		}

		$distance_in_meters = (int) $dispatcher->get_response_body_json_item( array( 'distances', 0, 1 ), 0 );

		if ( $distance_in_meters ) {
			return Wcsdm_Calculate_Distance_Result::distance(
				Wcsdm_Distance::from_m( (string) $distance_in_meters ),
				$dispatcher
			);
		}

		return Wcsdm_Calculate_Distance_Result::error(
			$dispatcher->get_response_body_json_item( array( 'message' ), __( 'API request failed.', 'wcsdm' ) ),
			$dispatcher
		);
	}

	/**
	 * Format a location object into Mapbox's coordinate string format.
	 *
	 * Mapbox requires coordinates in longitude,latitude order, which differs from
	 * many other APIs that use latitude,longitude. This method ensures proper
	 * formatting for Mapbox API requests.
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location $location The location object with coordinates.
	 *
	 * @return string The formatted coordinate string (e.g., '106.8456,-6.2088').
	 */
	private function format_location( Wcsdm_Location $location ):string {
		// Return in Mapbox's required format: longitude,latitude (longitude first!).
		// Note: This differs from many other APIs that use latitude,longitude order.
		return $location->get_coordinates_longitude() . ',' . $location->get_coordinates_latitude();
	}

	/**
	 * Convert address-based locations to coordinates using Mapbox Geocoding API.
	 *
	 * If the location is provided as an address (string or array), this method
	 * geocodes it using the Mapbox Geocoding API v6 to obtain coordinates.
	 * Locations already in coordinate format are returned unchanged.
	 *
	 * Supports two address types:
	 * - address_array: WooCommerce address array from checkout form
	 * - address: Plain address string from settings
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location $location      The location to potentially geocode.
	 * @param string         $access_token  The Mapbox access token for API authentication.
	 *
	 * @return Wcsdm_Location The location with coordinates (geocoded or original).
	 */
	private function maybe_geocode_location( Wcsdm_Location $location, string $access_token ):Wcsdm_Location {
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
			// Build request data with authentication and search query.
			$request_params = new Wcsdm_Request_Params();
			// Add access token (masked in logs for security).
			$request_params->add_param( $access_token, 'access_token' );
			// Add search query parameter (the address to geocode).
			$request_params->add_param( $address_to_geocode, 'q' );

			// Limit to a single best result.
			$request_params->add_param( 1, 'limit' );

			// Server-side geocoding should not rely on autocomplete.
			$request_params->add_param( 'false', 'autocomplete' );

			// Create and configure the geocoding request dispatcher for Mapbox Geocoding API v6.
			$dispatcher = Wcsdm_Request_Dispatcher::get(
				// Request URL - Mapbox Geocoding API v6 forward geocoding endpoint.
				// Forward geocoding converts addresses to coordinates (vs. reverse which does the opposite).
				'https://api.mapbox.com/search/geocode/v6/forward',
				// Request data (query parameters): access token and search query.
				$request_params,
				// Request headers (none required for this endpoint).
				new Wcsdm_Request_Headers(),
				// Mask the sensitive data from logs.
				array( $this, 'masking_callback' )
			);

			// Extract coordinates from the first matching result in the API response.
			$latitude  = $dispatcher->get_response_body_json_item( array( 'features', 0, 'properties', 'coordinates', 'latitude' ) );
			$longitude = $dispatcher->get_response_body_json_item( array( 'features', 0, 'properties', 'coordinates', 'longitude' ) );

			// If geocoding was successful and coordinates were found, create a new coordinate-based location.
			if ( $latitude && $longitude ) {
				return Wcsdm_Location::from_coordinates( (float) $latitude, (float) $longitude );
			}

			// Fallback: GeoJSON coordinates array is [longitude, latitude].
			$geojson_lng = $dispatcher->get_response_body_json_item( array( 'features', 0, 'geometry', 'coordinates', 0 ) );
			$geojson_lat = $dispatcher->get_response_body_json_item( array( 'features', 0, 'geometry', 'coordinates', 1 ) );

			if ( is_numeric( $geojson_lat ) && is_numeric( $geojson_lng ) ) {
				return Wcsdm_Location::from_coordinates( $geojson_lat, $geojson_lng );
			}
		}

		// Return the original location if no geocoding was needed or if it failed.
		return $location;
	}

	/**
	 * Callback function to mask sensitive data in logs.
	 *
	 * This callback is used by the request dispatcher to mask sensitive information
	 * (such as access tokens) in request logs for security purposes. It checks if
	 * the data path ends with 'access_token' and applies masking if found.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The value to potentially mask.
	 * @param array $path  The data path as an array of keys.
	 *
	 * @return mixed The original value or masked version if it's an access token.
	 */
	public function masking_callback( $value, array $path ) {
		// Convert path array to dot-notation string for pattern matching.
		$path_joined = implode( '.', $path );

		// Check if the path represents the access token.
		if ( wcsdm_str_ends_with( $path_joined, '.access_token' ) ) {
			// Mask the access token value for security.
			return map_deep( $value, 'wcsdm_mask_string' );
		}

		// Return the value unchanged if it's not sensitive data.
		return $value;
	}
}
