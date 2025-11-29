<?php
/**
 * Google Routes API provider implementation for WooReer.
 *
 * This file implements the Google Routes API v2 provider for calculating distances
 * and routes between locations. It handles authentication, route preferences, and
 * response parsing for the Google Maps Platform Routes API.
 *
 * ## Key Features
 *
 * - Support for multiple travel modes (driving, walking, cycling, transit, two-wheeler)
 * - Route optimization with avoidance options (tolls, highways, ferries, indoor)
 * - Field masking for efficient API responses
 * - Comprehensive error handling and validation
 * - Support for various location formats (coordinates, addresses, address arrays)
 *
 * ## API Integration
 *
 * Uses Google Routes API v2 (computeRoutes endpoint) with:
 * - API Key authentication via X-Goog-Api-Key header
 * - Field masks to optimize response payload
 * - JSON request/response format
 * - Distance returned in meters
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 *
 * @see        Wcsdm_API_Provider_Base For base provider functionality
 * @see        https://developers.google.com/maps/documentation/routes Google Routes API Documentation
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Routes API provider class.
 *
 * Implements distance calculation using Google's Routes API v2, providing
 * support for multiple travel modes and route preferences. Handles API
 * authentication, request formatting, and response parsing.
 *
 * @since 3.0
 */
class Wcsdm_API_Provider_Google extends Wcsdm_API_Provider_Base {

	/**
	 * Constructor.
	 *
	 * Initializes the Google API provider by setting up settings fields
	 * and registering the option validation hook.
	 *
	 * @since 3.0
	 */
	public function __construct() {
		$this->init_settings_fields();

		// Hook into the option validation process to validate settings.
		add_action( 'wcsdm_validate_option', array( $this, 'validate_option' ), 10, 3 );
	}

	/**
	 * Initialize settings fields for the Google API provider.
	 *
	 * @since 3.0
	 */
	private function init_settings_fields() {
		$this->settings_fields = array(
			// Google API Key - Required for authentication with Routes API v2.
			'api_key'           => array(
				'title'                   => __( 'API Key', 'wcsdm' ),
				'type'                    => 'text',
				'description'             => __( 'API key with Routes API enabled.', 'wcsdm' ),
				'default'                 => '',
				'is_required'             => true,
				'documentation'           => 'https://developers.google.com/maps/documentation/routes/get-api-key',

				// API request headers mapping.
				'api_request_headers_key' => 'X-Goog-Api-Key',
			),
			// Travel Mode - Determines the type of route calculation.
			'travel_mode'       => array(
				'title'                  => __( 'Travel Mode', 'wcsdm' ),
				'type'                   => 'select',
				'description'            => __( 'Specify the mode of travel.', 'wcsdm' ),
				'default'                => 'DRIVE',
				'options'                => array(
					'DRIVE'       => 'DRIVE',       // Standard automobile routing.
					'BICYCLE'     => 'BICYCLE',     // Bicycle-friendly routes.
					'WALK'        => 'WALK',        // Pedestrian routes.
					'TWO_WHEELER' => 'TWO_WHEELER', // Motorcycle/scooter routing.
					'TRANSIT'     => 'TRANSIT',     // Public transportation routes.
				),
				'documentation'          => 'https://developers.google.com/maps/documentation/routes/reference/rest/v2/RouteTravelMode',

				// API request data mapping.
				'api_request_params_key' => 'travelMode',
			),
			// Route Avoidances - Features to avoid when calculating routes.
			'route_avoidancess' => array(
				'title'                        => __( 'Route Avoidances', 'wcsdm' ),
				'type'                         => 'multiselect',
				'description'                  => __( 'Specify route features to avoid.', 'wcsdm' ),
				'options'                      => array(
					'avoidTolls'    => 'avoidTolls',    // Avoid toll roads.
					'avoidHighways' => 'avoidHighways', // Avoid highways/freeways.
					'avoidFerries'  => 'avoidFerries',  // Avoid ferry crossings.
					'avoidIndoor'   => 'avoidIndoor',   // Avoid indoor navigation.
				),
				'select_buttons'               => true,
				'documentation'                => 'https://developers.google.com/maps/documentation/routes/reference/rest/v2/RouteModifiers',

				// API request data mapping.
				'api_request_params_key'       => 'routeModifiers',
				'api_request_params_sanitizer' => function( $selected_options ):?array {
					// Return null if no options are selected to omit the parameter entirely.
					if ( ! $selected_options ) {
						return null;
					}

					// Transform selected options array into a modifiers object where each
					// option key becomes a property with boolean true value.
					$modifiers = array();

					foreach ( $selected_options as $option ) {
						$modifiers[ $option ] = true;
					}

					return $modifiers;
				},
			),
		);
	}

	/**
	 * Validate API provider option during settings save.
	 *
	 * Performs a test API request when the Google API provider is selected
	 * and an API key is provided. This ensures the API key is valid and the
	 * Routes API is properly enabled before saving the settings.
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

		$request_params  = $this->populate_request_params( $instance, 'settings' );
		$request_headers = $this->populate_request_headers( $instance, 'settings' );

		$result = $this->dispatch_request(
			Wcsdm_Location::from_coordinates( WCSDM_TEST_DESTINATION_LAT, WCSDM_TEST_DESTINATION_LNG ),
			Wcsdm_Location::from_coordinates( WCSDM_TEST_ORIGIN_LAT, WCSDM_TEST_ORIGIN_LNG ),
			$request_params,
			$request_headers
		);

		if ( $result->is_error() ) {
			$instance->maybe_write_log( 'error', $result->get_error(), $result->get_dispatcher()->vars() );

			if ( $instance->is_log_enabled() ) {
				throw new Exception( trim( $result->get_error(), '.' ) . ' » ' . __( 'Check the log for more details.', 'wcsdm' ) );
			} else {
				throw new Exception( $result->get_error() );
			}
		}
	}

	/**
	 * Get the unique slug identifier for this provider.
	 *
	 * @since 3.0
	 *
	 * @return string The provider slug: 'google'.
	 */
	public function get_slug():string {
		return 'google';
	}

	/**
	 * Get the display name for this provider.
	 *
	 * @since 3.0
	 *
	 * @return string The translated provider name displayed in the admin interface.
	 */
	public function get_name():string {
		return __( 'Routes API by Google', 'wcsdm' );
	}

	/**
	 * Calculate the distance between two locations.
	 *
	 * Implements the required interface method to calculate distance using
	 * Google Routes API. Populates request parameters and headers from the
	 * shipping method instance settings, then dispatches the API request.
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location        $destination The destination location.
	 * @param Wcsdm_Location        $origin      The origin location.
	 * @param Wcsdm_Shipping_Method $instance    The shipping method instance.
	 * @return Wcsdm_Calculate_Distance_Result The calculation result containing distance or error.
	 */
	public function calculate_distance(
		Wcsdm_Location $destination,
		Wcsdm_Location $origin,
		Wcsdm_Shipping_Method $instance
	):Wcsdm_Calculate_Distance_Result {
		$request_data = $this->populate_request_params( $instance, 'calculation' );

		$request_headers = $this->populate_request_headers( $instance, 'calculation' );

		return $this->dispatch_request(
			$destination,
			$origin,
			$request_data,
			$request_headers
		);
	}

	/**
	 * Dispatch an API request to Google Routes API.
	 *
	 * Handles the complete API request lifecycle including:
	 * - Adding origin and destination locations to request parameters
	 * - Setting required content type and field mask headers
	 * - Executing the POST request to the computeRoutes endpoint
	 * - Parsing the response for distance data
	 * - Error handling and result object creation
	 *
	 * The method uses field masking to request only the necessary data
	 * (distance and duration) to minimize response payload size.
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location        $destination      The destination location.
	 * @param Wcsdm_Location        $origin           The origin location.
	 * @param Wcsdm_Request_Params  $request_params   The populated request parameters.
	 * @param Wcsdm_Request_Headers $request_headers  The populated request headers.
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance or error.
	 */
	private function dispatch_request(
		Wcsdm_Location $destination,
		Wcsdm_Location $origin,
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers
	):Wcsdm_Calculate_Distance_Result {
		// Add origin location to request data. Format depends on location type (address or coordinates).
		$request_params->add_param(
			$this->format_location( $origin ),
			'origin'
		);

		// Add destination location to request data. Format depends on location type (address or coordinates).
		$request_params->add_param(
			$this->format_location( $destination ),
			'destination'
		);

		// Set content type to JSON as required by Google Routes API v2.
		$request_headers->add_header(
			'application/json',
			'Content-Type'
		);

		// Set field mask to optimize response by requesting only necessary fields.
		// This reduces bandwidth and improves API response time by excluding unused data.
		// We only need: routes.duration (travel time) and routes.distanceMeters (distance).
		$request_headers->add_header(
			'routes.duration,routes.distanceMeters',
			'X-Goog-FieldMask'
		);

		// Create and dispatch the HTTP POST request to Google Routes API v2 computeRoutes endpoint.
		// The endpoint uses the :computeRoutes method notation (gRPC transcoding for REST).
		$dispatcher = Wcsdm_Request_Dispatcher::post(
			'https://routes.googleapis.com/directions/v2:computeRoutes',
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
		$distance_in_meters = $dispatcher->get_response_body_json_item( array( 'routes', 0, 'distanceMeters' ) );

		if ( $distance_in_meters ) {
			return Wcsdm_Calculate_Distance_Result::distance(
				Wcsdm_Distance::from_m( $distance_in_meters ),
				$dispatcher
			);
		}

		return Wcsdm_Calculate_Distance_Result::error(
			$dispatcher->get_response_body_json_item( array( 'error', 'message' ), __( 'API request failed.', 'wcsdm' ) ),
			$dispatcher
		);
	}

	/**
	 * Format a location object for Google Routes API request.
	 *
	 * Converts a Wcsdm_Location object into the appropriate format required
	 * by the Google Routes API. Supports three location types:
	 * - 'coordinates': Converted to latLng object
	 * - 'address': Used as plain address string
	 * - 'address_array': Formatted using WooCommerce country formatting
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Location $location The location object to format.
	 * @return array The formatted location array for API request.
	 */
	private function format_location( Wcsdm_Location $location ):array {
		switch ( $location->get_location_type() ) {
			// Format as plain address string.
			case 'address':
				return array(
					'address' => $location->get_address(),
				);

			// Format address array using WooCommerce address formatting.
			case 'address_array':
				return array(
					'address' => WC()->countries->get_formatted_address( $location->get_address_array(), ', ' ),
				);

			// Format as latitude/longitude coordinates (default).
			case 'coordinates':
			default:
				return array(
					'location' => array(
						'latLng' => $location->get_coordinates(),
					),
				);
		}
	}

	/**
	 * Callback function to mask sensitive data in API requests.
	 *
	 * Used by the request dispatcher to sanitize sensitive information
	 * (like API keys) in logs and debug output. Masks the API key header
	 * to prevent exposure in debugging or logging contexts.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The value to potentially mask.
	 * @param array $path  The path array indicating the location of the value.
	 * @return mixed The original value or masked version if it's an API key.
	 */
	public function masking_callback( $value, array $path ) {
		// Convert path array to dot-notation string for pattern matching.
		$path_joined = implode( '.', $path );

		// Check if the path represents the Google API key header.
		if ( wcsdm_str_ends_with( $path_joined, '.X-Goog-Api-Key' ) ) {
			// Mask the API key value to prevent exposure in logs.
			return map_deep( $value, 'wcsdm_mask_string' );
		}

		// Return the value unchanged if it's not sensitive data.
		return $value;
	}
}
