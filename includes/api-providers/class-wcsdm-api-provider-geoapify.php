<?php
/**
 * Geoapify API Provider for WooReer Distance Matrix
 *
 * Implements the Geoapify Routing API for distance calculations.
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.1.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://apidocs.geoapify.com/docs/routing/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Geoapify API provider class.
 *
 * Provides routing and geocoding integration with Geoapify APIs. Handles
 * request parameter mapping, request dispatching and response parsing for
 * distance calculations used by the shipping method.
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.1.0
 */
class Wcsdm_API_Provider_Geoapify extends Wcsdm_API_Provider_Base implements Wcsdm_API_Provider_Interface {

	/**
	 * Constructor.
	 *
	 * Initializes provider settings fields and registers settings validation
	 * callback for the provider selection option.
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		$this->init_settings_fields();

		// Hook into the option validation process to validate settings.
		add_action( 'wcsdm_validate_option', array( $this, 'validate_option' ), 10, 3 );
	}

	/**
	 * Initialize settings fields for Geoapify provider.
	 */
	private function init_settings_fields() {
		$this->settings_fields = array(
			'api_key' => array(
				'title'                  => __( 'API Key', 'wcsdm' ),
				'type'                   => 'password',
				'description'            => __( 'Geoapify API key.', 'wcsdm' ),
				'default'                => '',
				'is_required'            => true,
				'documentation'          => 'https://apidocs.geoapify.com/docs/routing/#quick-start',
				'api_request_params_key' => 'apiKey',
			),
			'mode'    => array(
				'title'                  => __( 'Travel Mode', 'wcsdm' ),
				'type'                   => 'select',
				'description'            => __( 'Mode of travel for route calculation.', 'wcsdm' ),
				'default'                => 'drive',
				'options'                => array(
					'drive'                 => 'drive',
					'light_truck'           => 'light_truck',
					'medium_truck'          => 'medium_truck',
					'truck'                 => 'truck',
					'heavy_truck'           => 'heavy_truck',
					'truck_dangerous_goods' => 'truck_dangerous_goods',
					'long_truck'            => 'long_truck',
					'bus'                   => 'bus',
					'scooter'               => 'scooter',
					'motorcycle'            => 'motorcycle',
					'bicycle'               => 'bicycle',
					'mountain_bike'         => 'mountain_bike',
					'road_bike'             => 'road_bike',
					'walk'                  => 'walk',
					'hike'                  => 'hike',
				),
				'documentation'          => 'https://apidocs.geoapify.com/docs/routing/#api',
				'api_request_params_key' => 'mode',
			),
			'type'    => array(
				'title'                  => __( 'Route Optimization', 'wcsdm' ),
				'type'                   => 'select',
				'description'            => __( 'Route optimization type, the default value is balanced. Check Route optimization type for more information.', 'wcsdm' ),
				'default'                => 'short',
				'options'                => array(
					'short'          => 'short',
					'balanced'       => 'balanced',
					'less_maneuvers' => 'less_maneuvers',
				),
				'documentation'          => 'https://apidocs.geoapify.com/docs/routing/#api',
				'api_request_params_key' => 'type',
			),
			'avoid'   => array(
				'title'                        => __( 'Route Avoidances', 'wcsdm' ),
				'type'                         => 'multiselect',
				'description'                  => __( 'List of road types or locations to be avoided by the router.', 'wcsdm' ),
				'options'                      => array(
					'tolls'    => 'tolls',
					'ferries'  => 'ferries',
					'highways' => 'highways',
				),
				'select_buttons'               => true,
				'documentation'                => 'https://apidocs.geoapify.com/docs/routing/#api',
				'api_request_params_key'       => 'avoid',
				'api_request_params_sanitizer' => function( $selected_options ):?string {
					if ( $selected_options ) {
						return implode( '|', $selected_options );
					}

					return null;
				},
			),
		);
	}

	/**
	 * Validate provider-specific options when the Geoapify provider is selected.
	 *
	 * This method is hooked into `wcsdm_validate_option` and will run during
	 * the settings save process. It performs additional validation for fields
	 * that require external verification (for example, verifying an API key)
	 * when this provider is selected.
	 *
	 * @since 3.1.0
	 *
	 * @param mixed                 $value    The submitted option value.
	 * @param string                $key      The option key being validated.
	 * @param Wcsdm_Shipping_Method $instance The shipping method instance.
	 *
	 * @return void
	 * @throws Exception When an external validation error occurs.
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

		$request_params = $this->populate_request_params(
			$instance,
			'settings',
			array(
				'waypoints' => $this->format_waypoints(
					Wcsdm_Location::from_coordinates( WCSDM_TEST_ORIGIN_LAT, WCSDM_TEST_ORIGIN_LNG ),
					Wcsdm_Location::from_coordinates( WCSDM_TEST_DESTINATION_LAT, WCSDM_TEST_DESTINATION_LNG )
				),
			)
		);

		$request_headers = $this->populate_request_headers( $instance, 'settings' );

		$result = $this->dispatch_request(
			$request_params,
			$request_headers
		);

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
	 * Get the unique slug identifier for this provider.
	 *
	 * @return string The provider slug: 'geoapify'.
	 */
	public function get_slug():string {
		return 'geoapify';
	}

	/**
	 * Get the display name for this provider.
	 *
	 * @return string The provider name.
	 */
	public function get_name():string {
		return __( 'Routing API by Geoapify', 'wcsdm' );
	}

	/**
	 * Calculate the distance between two locations using Geoapify Routing API.
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
		// Retrieve the API key from settings.
		$api_key = $instance->get_option( $this->get_field_key( 'api_key' ) );

		// Convert address-based locations to coordinates if needed via Mapbox Geocoding API.
		$destination = $this->maybe_geocode_location( $destination, $api_key );
		$origin      = $this->maybe_geocode_location( $origin, $api_key );

		// Build request parameters from settings.
		$request_params = $this->populate_request_params(
			$instance,
			'calculation',
			array(
				'waypoints' => $this->format_waypoints( $origin, $destination ),
			)
		);

		// Prepare request headers for the calculation context.
		$request_headers = $this->populate_request_headers( $instance, 'calculation' );

		// Calculate distance using the Mapbox API.
		return $this->dispatch_request(
			$request_params,
			$request_headers
		);
	}

	/**
	 * Dispatch a request to the Mapbox Matrix API.
	 *
	 * Constructs the API endpoint URL with routing profile and coordinates, executes
	 * the HTTP request, and parses the response to extract distance information.
	 * Handles both successful responses and various error conditions.
	 *
	 * @since 3.1.0
	 *
	 * @param Wcsdm_Request_Params  $request_params  Query parameters including access token.
	 * @param Wcsdm_Request_Headers $request_headers HTTP headers for the request.
	 *
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance in meters or error.
	 */
	private function dispatch_request(
		Wcsdm_Request_Params $request_params,
		Wcsdm_Request_Headers $request_headers
	):Wcsdm_Calculate_Distance_Result {
		// Create and configure the request dispatcher with endpoint, query params, and headers.
		$dispatcher = Wcsdm_Request_Dispatcher::get(
			'https://api.geoapify.com/v1/routing',
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

		$distance_in_meters = (int) $dispatcher->get_response_body_json_item( array( 'features', 0, 'properties', 'distance' ), 0 );

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
	 * @since 3.1.0
	 *
	 * @param Wcsdm_Location $location The location to potentially geocode.
	 * @param string         $api_key  The Geoapify API key.
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
			$address_to_geocode = WC()->countries->get_formatted_address( $location->get_address_array(), ', ' );
		}

		// Check if location is provided as a plain address string (e.g., from settings).
		// String addresses are already formatted and ready to geocode.
		if ( $location->get_location_type() === 'address' ) {
			$address_to_geocode = $location->get_address();
		}

		// If we have an address to geocode, make the geocoding API request.
		if ( $address_to_geocode ) {
			// Create and configure the geocoding request dispatcher for Mapbox Geocoding API v6.
			$dispatcher = Wcsdm_Request_Dispatcher::get(
				'https://api.geoapify.com/v1/geocode/search',
				// Request data (query parameters): access token and search query.
				new Wcsdm_Request_Params(
					array(
						'apiKey' => $api_key,
						'text'   => $address_to_geocode,
						'limit'  => 1,
					)
				),
				// Request headers (none required for this endpoint).
				new Wcsdm_Request_Headers(),
				// Mask the sensitive data from logs.
				array( $this, 'masking_callback' )
			);

			// Extract coordinates from the first matching result in the API response.
			$latitude  = $dispatcher->get_response_body_json_item( array( 'features', 0, 'properties', 'lat' ) );
			$longitude = $dispatcher->get_response_body_json_item( array( 'features', 0, 'properties', 'lon' ) );

			// If geocoding was successful and coordinates were found, create a new coordinate-based location.
			if ( $latitude && $longitude ) {
				return Wcsdm_Location::from_coordinates( (float) $latitude, (float) $longitude );
			}
		}

		// Return the original location if no geocoding was needed or if it failed.
		return $location;
	}

	/**
	 * Format two locations as a Geoapify waypoints string.
	 *
	 * The returned string contains two coordinate pairs separated by a pipe
	 * character (`|`). Each coordinate pair is formatted as
	 * "latitude,longitude" for the origin and destination respectively.
	 *
	 * Example: "lat1,lon1|lat2,lon2"
	 *
	 * @since 3.1.0
	 *
	 * @param Wcsdm_Location $origin      Origin location.
	 * @param Wcsdm_Location $destination Destination location.
	 * @return string Waypoints string formatted as "latitude,longitude|latitude,longitude".
	 */
	private function format_waypoints( Wcsdm_Location $origin, Wcsdm_Location $destination ):string {
		$waypoints = sprintf(
			'%1$s,%2$s|%3$s,%4$s',
			$origin->get_coordinates_latitude(),
			$origin->get_coordinates_longitude(),
			$destination->get_coordinates_latitude(),
			$destination->get_coordinates_longitude()
		);

		return $waypoints;
	}

	/**
	 * Callback function to mask sensitive data in logs.
	 *
	 * This callback is used by the request dispatcher to mask sensitive information
	 * (such as access tokens) in request logs for security purposes. It checks if
	 * the data path ends with 'access_token' and applies masking if found.
	 *
	 * @since 3.1.0
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
		if ( wcsdm_str_ends_with( $path_joined, '.apiKey' ) ) {
			// Mask the access token value for security.
			return map_deep( $value, 'wcsdm_mask_string' );
		}

		// Return the value unchanged if it's not sensitive data.
		return $value;
	}
}
