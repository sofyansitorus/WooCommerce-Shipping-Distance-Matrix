<?php
/**
 * Distance value object class for WooReer
 *
 * This file contains the Wcsdm_Distance class which implements a value object
 * pattern for handling distance measurements and conversions. It provides a clean
 * interface for working with distances in different units (meters, kilometers, miles)
 * and supports optional ceiling operations for rounding results upward.
 *
 * Key Features:
 * - Immutable value object design for distance measurements
 * - Support for three distance units: meters (m), kilometers (km), and miles (mi)
 * - Automatic unit conversion with high precision (2 decimal places)
 * - Optional ceiling operation for rounding up to nearest integer
 * - Type-safe constructor with unit validation
 * - Fluent API for conversions (in_km(), in_mi(), in_m())
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
 * Distance value object class.
 *
 * This class provides a robust implementation of the value object pattern for
 * distance measurements. It encapsulates a distance value with its unit and
 * provides conversion methods to other units with consistent precision handling.
 *
 * The class supports three standard distance units:
 * - Meters (m): Base metric unit
 * - Kilometers (km): Metric unit for longer distances
 * - Miles (mi): Imperial unit commonly used in the US
 *
 * All conversions use standard conversion factors:
 * - 1 kilometer = 1000 meters
 * - 1 mile = 1609.34 meters
 * - 1 mile = 1.60934 kilometers
 *
 * @since 3.0
 */
class Wcsdm_Distance {

	/**
	 * Allowed unit types.
	 *
	 * Defines the valid distance units supported by this class.
	 * Currently supports metric (m, km) and imperial (mi) units.
	 *
	 * @since 3.0
	 * @var array Array of allowed unit strings: 'm' (meters), 'km' (kilometers), 'mi' (miles).
	 */
	private $allowed_units = array( 'm', 'km', 'mi' );

	/**
	 * Distance value.
	 *
	 * The numeric distance value in the unit specified by $unit property.
	 * This value is immutable after construction.
	 *
	 * @since 3.0
	 * @var string The distance value.
	 */
	private $distance;

	/**
	 * Distance unit.
	 *
	 * The unit of measurement for the distance value.
	 * Must be one of the values defined in $allowed_units.
	 *
	 * @since 3.0
	 * @var string The unit identifier: 'm', 'km', or 'mi'.
	 */
	private $unit;

	/**
	 * Whether to apply ceiling to the result.
	 *
	 * When enabled, all conversion results will be rounded up to the nearest integer
	 * using PHP's ceil() function. This is useful for shipping calculations where
	 * partial units should be counted as full units.
	 *
	 * @since 3.0
	 * @var bool True to apply ceiling, false for standard rounding.
	 */
	private $ceiling = false;

	/**
	 * Constructor.
	 *
	 * Initializes a new distance value object with the specified distance and unit.
	 * Validates that the provided unit is one of the supported types before construction.
	 *
	 * @since 3.0
	 *
	 * @param string $distance Distance value.
	 * @param string $unit     Distance unit identifier: 'm' (meters), 'km' (kilometers), or 'mi' (miles).
	 *
	 * @throws Exception If invalid unit type is provided (not in allowed_units).
	 */
	public function __construct( string $distance, string $unit ) {
		// Validate that the unit is one of the supported types.
		if ( ! in_array( $unit, $this->allowed_units, true ) ) {
			throw new Exception( 'Invalid unit type!' );
		}

		// Store the distance value and unit for later conversions.
		$this->distance = $distance;
		$this->unit     = $unit;
	}

	/**
	 * Create instance from meters.
	 *
	 * Factory method to create a new Wcsdm_Distance instance using meters as the unit.
	 *
	 * @since 3.0
	 *
	 * @param string $meters Distance in meters.
	 *
	 * @return Wcsdm_Distance New instance initialized with meters.
	 */
	public static function from_m( string $meters ):Wcsdm_Distance {
		$instance = new self( $meters, 'm' );

		return $instance;
	}

	/**
	 * Create instance from kilometers.
	 *
	 * Factory method to create a new Wcsdm_Distance instance using kilometers as the unit.
	 *
	 * @since 3.0
	 *
	 * @param string $kilometers Distance in kilometers.
	 *
	 * @return Wcsdm_Distance New instance initialized with kilometers.
	 */
	public static function from_km( string $kilometers ):Wcsdm_Distance {
		$instance = new self( $kilometers, 'km' );

		return $instance;
	}

	/**
	 * Create instance from miles.
	 *
	 * Factory method to create a new Wcsdm_Distance instance using miles as the unit.
	 *
	 * @since 3.0
	 *
	 * @param string $miles Distance in miles.
	 *
	 * @return Wcsdm_Distance New instance initialized with miles.
	 */
	public static function from_mi( string $miles ):Wcsdm_Distance {
		$instance = new self( $miles, 'mi' );

		return $instance;
	}

	/**
	 * Set whether to apply ceiling to conversion results.
	 *
	 * When enabled, all conversion methods (in_km, in_mi, in_m) will round their
	 * results up to the nearest integer using ceil(). This is particularly useful
	 * in shipping cost calculations where partial distance units should be treated
	 * as full units (e.g., 5.1 km becomes 6 km).
	 *
	 * @since 3.0
	 *
	 * @param bool $ceiling True to round up conversion results, false to use standard formatting.
	 *
	 * @return void
	 */
	public function set_ceiling( bool $ceiling ):void {
		$this->ceiling = $ceiling;
	}

	/**
	 * Get distance in kilometers.
	 *
	 * Converts the stored distance value to kilometers using standard conversion factors.
	 * The result is formatted to 2 decimal places and optionally ceiled if set_ceiling(true) was called.
	 *
	 * Conversion factors used:
	 * - From meters: divide by 1000
	 * - From miles: multiply by 1.60934
	 * - From kilometers: no conversion needed
	 *
	 * @since 3.0
	 *
	 * @return string Distance in kilometers, formatted to 2 decimal places.
	 */
	public function in_km():string {
		// Convert from the current unit to kilometers.
		switch ( $this->unit ) {
			case 'm':
				// Convert meters to kilometers (1 km = 1000 m).
				$value = $this->distance / 1000;
				break;

			case 'mi':
				// Convert miles to kilometers (1 mi = 1.60934 km).
				$value = $this->distance * 1.60934;
				break;

			case 'km':
			default:
				// Already in kilometers, no conversion needed.
				$value = $this->distance;
				break;
		}

		// Apply formatting and optional ceiling.
		return $this->number_format( $value );
	}

	/**
	 * Get distance in miles.
	 *
	 * Converts the stored distance value to miles using standard conversion factors.
	 * The result is formatted to 2 decimal places and optionally ceiled if set_ceiling(true) was called.
	 *
	 * Conversion factors used:
	 * - From meters: divide by 1609.34
	 * - From kilometers: divide by 1.60934
	 * - From miles: no conversion needed
	 *
	 * @since 3.0
	 *
	 * @return string Distance in miles, formatted to 2 decimal places.
	 */
	public function in_mi():string {
		// Convert from the current unit to miles.
		switch ( $this->unit ) {
			case 'm':
				// Convert meters to miles (1 mi = 1609.34 m).
				$value = $this->distance / 1609.34;
				break;

			case 'km':
				// Convert kilometers to miles (1 mi = 1.60934 km).
				$value = $this->distance / 1.60934;
				break;

			case 'mi':
			default:
				// Already in miles, no conversion needed.
				$value = $this->distance;
				break;
		}

		// Apply formatting and optional ceiling.
		return $this->number_format( $value );
	}

	/**
	 * Get distance in meters.
	 *
	 * Converts the stored distance value to meters using standard conversion factors.
	 * The result is formatted to 2 decimal places and optionally ceiled if set_ceiling(true) was called.
	 *
	 * Conversion factors used:
	 * - From kilometers: multiply by 1000
	 * - From miles: multiply by 1609.34
	 * - From meters: no conversion needed
	 *
	 * @since 3.0
	 *
	 * @return string Distance in meters, formatted to 2 decimal places.
	 */
	public function in_m():string {
		// Convert from the current unit to meters.
		switch ( $this->unit ) {
			case 'km':
				// Convert kilometers to meters (1 km = 1000 m).
				$value = $this->distance * 1000;
				break;

			case 'mi':
				// Convert miles to meters (1 mi = 1609.34 m).
				$value = $this->distance * 1609.34;
				break;

			case 'm':
			default:
				// Already in meters, no conversion needed.
				$value = $this->distance;
				break;
		}

		// Apply formatting and optional ceiling.
		return $this->number_format( $value );
	}

	/**
	 * Format distance value with optional ceiling.
	 *
	 * This method applies the ceiling operation to the distance value if enabled
	 * via set_ceiling(). When ceiling is active, values are rounded up to the
	 * nearest integer, which is useful for shipping calculations where partial
	 * distance units should be treated as full units.
	 *
	 * @since 3.0
	 *
	 * @param string $value The distance value to format.
	 *
	 * @return string The formatted distance value (ceiled if ceiling is enabled).
	 */
	private function number_format( string $value ):string {
		// Apply ceiling if enabled (rounds up to nearest integer).
		// Example: 5.01 becomes 6, 5.99 becomes 6, 5.00 stays 5.
		if ( $this->ceiling ) {
			$value = ceil( $value );
		}

		return wc_format_decimal( $value, '' );
	}
}
