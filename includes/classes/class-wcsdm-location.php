<?php
/**
 * WooReer Location Class
 *
 * This file defines the Wcsdm_Location class which handles location data
 * management for the WooReer plugin. It provides a flexible interface for
 * working with location data in multiple formats including address strings,
 * address component arrays, and geographic coordinates.
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 * @license    GPL-2.0-or-later
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling location data in the WooReer plugin.
 *
 * This class manages location information in three different formats:
 * - Address string: A complete address as a single string
 * - Address array: Address broken down into components (street, city, state, etc.)
 * - Coordinates: Geographic coordinates (latitude and longitude)
 *
 * The class uses a factory pattern with static methods (from_address, from_address_array,
 * from_coordinates) to create properly validated location instances. Each instance is
 * typed and maintains validation state to ensure data integrity.
 *
 * @since 3.0
 */
class Wcsdm_Location {

	/**
	 * Location type constant for address string format.
	 *
	 * @since 3.0.2
	 * @var string
	 */
	const LOCATION_TYPE_ADDRESS = 'address';

	/**
	 * Location type constant for address array format.
	 *
	 * @since 3.0.2
	 * @var string
	 */
	const LOCATION_TYPE_ADDRESS_ARRAY = 'address_array';

	/**
	 * Location type constant for coordinates format.
	 *
	 * @since 3.0.2
	 * @var string
	 */
	const LOCATION_TYPE_COORDINATES = 'coordinates';

	/**
	 * Array to hold error messages.
	 *
	 * @since 3.0.2
	 * @var array
	 */
	private $errors = array();

	/**
	 * The type of location ('address', 'address_array', or 'coordinates').
	 *
	 * Indicates which format the location data is stored in and determines
	 * which getter methods are valid for this instance.
	 *
	 * @since 3.0
	 * @var string The location type identifier.
	 */
	private $location_type;

	/**
	 * The address string.
	 *
	 * Contains the complete address as a single string when location_type is 'address'.
	 * Null if location type is different or validation failed.
	 *
	 * @since 3.0
	 * @var string|null The address string or null if not applicable.
	 */
	private $address;

	/**
	 * The address components array.
	 *
	 * Contains address broken down into individual components (street, city, state,
	 * country, postcode) when location_type is 'address_array'. Null if location
	 * type is different or validation failed.
	 *
	 * @since 3.0
	 * @var array|null The address components array or null if not applicable.
	 */
	private $address_array;

	/**
	 * The coordinates array containing latitude and longitude.
	 *
	 * Contains geographic coordinates when location_type is 'coordinates'.
	 * Array structure: ['latitude' => float, 'longitude' => float]
	 * Null if location type is different or validation failed.
	 *
	 * @since 3.0
	 * @var array|null The coordinates array or null if not applicable.
	 */
	private $coordinates;

	/**
	 * Constructor.
	 *
	 * Initializes a new location instance with the specified location type.
	 * This constructor is private as locations should be created through the
	 * static factory methods (from_address, from_address_array, from_coordinates)
	 * to ensure proper initialization and validation.
	 *
	 * @since 3.0
	 * @param string $location_type The type of location ('address', 'address_array' or 'coordinates').
	 * @throws Exception If an invalid location type is provided.
	 */
	private function __construct( string $location_type ) {
		$this->set_location_type( $location_type );
	}

	/**
	 * Gets all available location types.
	 *
	 * Returns an array of all valid location type constants that can be used
	 * when creating or working with location instances.
	 *
	 * @since 3.0.2
	 * @return array Array of location type constants.
	 */
	public function get_location_types(): array {
		return array(
			self::LOCATION_TYPE_ADDRESS,
			self::LOCATION_TYPE_ADDRESS_ARRAY,
			self::LOCATION_TYPE_COORDINATES,
		);
	}

	/**
	 * Sets the location type.
	 *
	 * Validates and sets the type of location for this instance. Only allows
	 * types defined in the allowed_location_types property. This method is called
	 * during construction to establish the location format.
	 *
	 * @since 3.0
	 * @param string $location_type The type of location ('address', 'address_array' or 'coordinates').
	 * @throws Exception If an invalid location type is provided.
	 * @return void
	 */
	private function set_location_type( string $location_type ) {
		// Validate that the location type is one of the allowed values (address, address_array, or coordinates).
		if ( ! in_array( $location_type, $this->get_location_types(), true ) ) {
			throw new Exception( 'Invalid location type!' );
		}

		$this->location_type = $location_type;
	}

	/**
	 * Creates a new location instance from an address string.
	 *
	 * Factory method to create a location object from a complete address string.
	 * The address is validated before being stored.
	 *
	 * @since 3.0
	 * @param string $address The address string to create the location from.
	 * @return Wcsdm_Location A new location instance with the specified address.
	 */
	public static function from_address( string $address ):Wcsdm_Location {
		$location = new self( self::LOCATION_TYPE_ADDRESS );

		$location->set_address( $address );

		return $location;
	}

	/**
	 * Sets the address string for the location.
	 *
	 * Validates and stores the address string. If validation fails, sets the
	 * error flag and stores null. Only works when location_type is 'address'.
	 *
	 * @since 3.0
	 * @param string $address The address string to set.
	 * @return void
	 */
	public function set_address( string $address ) {
		if ( ! wcsdm_validate_address( $address ) ) {
			$this->errors[ self::LOCATION_TYPE_ADDRESS ] = true;
		}

		$this->address = $address;
	}

	/**
	 * Gets the address string for the location.
	 *
	 * Returns the stored address string. Validates that the location type is correct
	 * and that no validation errors occurred during data assignment.
	 *
	 * @since 3.0
	 * @return string The address string.
	 * @throws Exception If location type is invalid or location data is in error state.
	 */
	public function get_address(): string {
		// Verify location type and error state before returning the address.
		$this->maybe_throw_error( self::LOCATION_TYPE_ADDRESS );

		return $this->address;
	}

	/**
	 * Normalizes an address array to contain only allowed address fields.
	 *
	 * This method filters and standardizes address component arrays by extracting
	 * only the fields defined by wcsdm_include_address_fields(). It handles the
	 * special case where 'address' can be used as a fallback for 'address_1'.
	 * Fields not present in the input array are omitted from the result.
	 *
	 * @since 3.0.2
	 * @param array $address_array The raw address array with various address components.
	 * @return array The normalized address array containing only allowed fields.
	 */
	private function normalize_address_array( array $address_array ): array {
		$normalized_address_array = array();

		// Get the list of allowed address fields from helper function.
		$allowed_fields = wcsdm_include_address_fields();

		// Iterate through allowed fields and extract values from package destination.
		foreach ( $allowed_fields as $allowed_field ) {
			$target_field = $allowed_field;

			// Fallback to 'address' field if 'address_1' is empty but 'address' is available.
			if ( 'address_1' === $target_field && empty( $address_array['address_1'] ) && ! empty( $address_array['address'] ) ) {
				$target_field = 'address';
			}

			if ( ! isset( $address_array[ $target_field ] ) ) {
				continue;
			}

			$normalized_address_array[ $allowed_field ] = $address_array[ $target_field ];
		}

		return $normalized_address_array;
	}

	/**
	 * Creates a new location instance from an address array.
	 *
	 * Factory method to create a location object from address components.
	 * The address array typically contains keys like street, city, state, country, postcode.
	 * The array is validated before being stored.
	 *
	 * @since 3.0
	 * @param array $address_array The address components array.
	 * @return Wcsdm_Location A new location instance with the specified address array.
	 */
	public static function from_address_array( array $address_array ):Wcsdm_Location {
		$location = new self( self::LOCATION_TYPE_ADDRESS_ARRAY );

		$location->set_address_array( $address_array );

		return $location;
	}

	/**
	 * Sets the address array for the location.
	 *
	 * Validates and stores the address components array. If validation fails,
	 * sets the error flag and stores null. Only works when location_type is 'address_array'.
	 *
	 * @since 3.0
	 * @param array $address_array The address components array to set.
	 * @return void
	 */
	public function set_address_array( array $address_array ) {
		$normalize_address_array = $this->normalize_address_array( $address_array );

		if ( ! wcsdm_validate_address_array( $normalize_address_array ) ) {
			$this->errors[ self::LOCATION_TYPE_ADDRESS_ARRAY ] = true;
		}

		$this->address_array = $normalize_address_array;
	}

	/**
	 * Gets the address array for the location.
	 *
	 * Returns the stored address components array. Validates that the location type
	 * is correct and that no validation errors occurred during data assignment.
	 *
	 * @since 3.0
	 * @return array The address components array.
	 * @throws Exception If location type is invalid or location data is in error state.
	 */
	public function get_address_array():array {
		// Verify location type and error state before returning the address array.
		$this->maybe_throw_error( self::LOCATION_TYPE_ADDRESS_ARRAY );

		return $this->address_array;
	}

	/**
	 * Creates a new location instance from latitude and longitude coordinates.
	 *
	 * Factory method to create a location object from geographic coordinates.
	 * The coordinates are validated before being stored.
	 *
	 * @since 3.0
	 * @param float $lat The latitude coordinate (typically -90 to 90).
	 * @param float $lng The longitude coordinate (typically -180 to 180).
	 * @return Wcsdm_Location A new location instance with the specified coordinates.
	 */
	public static function from_coordinates( float $lat, float $lng ): Wcsdm_Location {
		$location = new self( self::LOCATION_TYPE_COORDINATES );

		$location->set_coordinates( $lat, $lng );

		return $location;
	}

	/**
	 * Sets the coordinates for the location.
	 *
	 * Validates and stores the latitude and longitude coordinates. If validation
	 * fails, sets the error flag and stores null. Only works when location_type
	 * is 'coordinates'.
	 *
	 * @since 3.0
	 * @param float $lat The latitude coordinate (typically -90 to 90).
	 * @param float $lng The longitude coordinate (typically -180 to 180).
	 * @return void
	 */
	public function set_coordinates( float $lat, float $lng ) {
		if ( ! wcsdm_validate_coordinates( $lat, $lng ) ) {
			$this->errors[ self::LOCATION_TYPE_COORDINATES ] = true;
		}

		$this->coordinates = array(
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}

	/**
	 * Gets the coordinates for the location.
	 *
	 * Returns the stored coordinates array with latitude and longitude values.
	 * Validates that the location type is correct and that no validation errors
	 * occurred during data assignment.
	 *
	 * @since 3.0
	 * @return array{latitude: float, longitude: float} The coordinates as an associative array.
	 * @throws Exception If location type is invalid or location data is in error state.
	 */
	public function get_coordinates():array {
		// Verify location type and error state before returning the coordinates.
		$this->maybe_throw_error( self::LOCATION_TYPE_COORDINATES );

		return $this->coordinates;
	}

	/**
	 * Gets the latitude value from the coordinates.
	 *
	 * Extracts and returns only the latitude component from the stored
	 * coordinates. This is a convenience method for when you need just
	 * the latitude without retrieving the full coordinates array.
	 *
	 * @since 3.0
	 * @return float The latitude coordinate value.
	 * @throws Exception If location type is invalid or location data is in error state.
	 */
	public function get_coordinates_latitude():float {
		// Verify location type and error state before accessing coordinates.
		$this->maybe_throw_error( self::LOCATION_TYPE_COORDINATES );

		return $this->coordinates['latitude'];
	}

	/**
	 * Gets the longitude value from the coordinates.
	 *
	 * Extracts and returns only the longitude component from the stored
	 * coordinates. This is a convenience method for when you need just
	 * the longitude without retrieving the full coordinates array.
	 *
	 * @since 3.0
	 * @return float The longitude coordinate value.
	 * @throws Exception If location type is invalid or location data is in error state.
	 */
	public function get_coordinates_longitude():float {
		// Verify location type and error state before accessing coordinates.
		$this->maybe_throw_error( self::LOCATION_TYPE_COORDINATES );

		return $this->coordinates['longitude'];
	}

	/**
	 * Checks if the location data matches the expected type and throws an error if not.
	 *
	 * Internal validation method called by getter methods to ensure data integrity.
	 * Throws an exception if the location has validation errors or if there's a
	 * mismatch between the expected and actual location types.
	 *
	 * @since 3.0
	 * @param string $location_type The expected location type to check against.
	 * @throws Exception If location data is invalid or type doesn't match.
	 * @return void
	 */
	private function maybe_throw_error( string $location_type ):void {
		// Check if any validation error occurred during data assignment.
		if ( $this->is_error() ) {
			throw new Exception( 'Invalid location data!' );
		}

		// Verify that the location type matches the expected type for this getter.
		if ( $location_type !== $this->get_location_type() ) {
			throw new Exception( 'Location type mismatch!' );
		}
	}

	/**
	 * Gets the current location type.
	 *
	 * Returns the location type identifier that indicates which format the
	 * location data is stored in (address, address_array, or coordinates).
	 *
	 * @since 3.0
	 * @return string The location type ('address', 'address_array' or 'coordinates').
	 */
	public function get_location_type():string {
		return $this->location_type;
	}

	/**
	 * Checks if the location has any validation errors.
	 *
	 * Returns true if validation failed during data assignment, which typically
	 * occurs when invalid data is provided to setter methods or validation
	 * functions return false.
	 *
	 * @since 3.0
	 * @return bool True if there are errors, false otherwise.
	 */
	public function is_error():bool {
		return $this->errors[ $this->get_location_type() ] ?? false;
	}

	/**
	 * Gets all object variables as an array.
	 *
	 * Returns all instance properties as an associative array. Useful for
	 * debugging, serialization, or when you need to inspect the complete
	 * state of a location object.
	 *
	 * @since 3.0
	 * @return array An array containing all object variables and their values.
	 */
	public function to_array():array {
		return get_object_vars( $this );
	}

	/**
	 * Creates a new location instance from a location array.
	 *
	 * Factory method to create a location object from an array containing location data.
	 * The array must include a 'location_type' key that determines which factory
	 * method to use internally. Supports creating locations from address strings, address
	 * component arrays, or coordinate pairs.
	 *
	 * @since 3.0.2
	 * @param array $location_array The location data array with type and corresponding data.
	 * @return Wcsdm_Location A new location instance with the specified location data.
	 * @throws Exception If the location type is invalid or missing.
	 */
	public static function from_array( array $location_array ):Wcsdm_Location {
		$location_type = $location_array['location_type'] ?? '';

		switch ( $location_type ) {
			case self::LOCATION_TYPE_ADDRESS:
				$location = self::from_address( $location_array['address'] ?? '' );
				break;
			case self::LOCATION_TYPE_ADDRESS_ARRAY:
				$location = self::from_address_array( $location_array['address_array'] ?? array() );
				break;
			case self::LOCATION_TYPE_COORDINATES:
				$location = self::from_coordinates( $location_array['coordinates']['latitude'], $location_array['coordinates']['longitude'] );
				break;
			default:
				throw new Exception( 'Invalid location type!' );
		}

		return $location;
	}
}
