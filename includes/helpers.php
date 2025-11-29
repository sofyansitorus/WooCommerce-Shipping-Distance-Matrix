<?php
/**
 * Helpers file
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.5.0
 *
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! function_exists( 'wcsdm_is_plugin_active' ) ) :
	/**
	 * Check if plugin is active
	 *
	 * @since 1.5.0
	 *
	 * @param string $plugin_file Plugin file name.
	 * @return bool True if the plugin is active, false otherwise.
	 */
	function wcsdm_is_plugin_active( $plugin_file ) {
		$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, (array) get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( $plugin_file, $active_plugins, true ) || array_key_exists( $plugin_file, $active_plugins );
	}
endif;

if ( ! function_exists( 'wcsdm_is_dev_env' ) ) :
	/**
	 * Check is in development environment.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if in development environment, false otherwise.
	 */
	function wcsdm_is_dev_env():bool {
		return 'development' === wp_get_environment_type();
	}
endif;

if ( ! function_exists( 'wcsdm_autoload' ) ) :
	/**
	 * Class autoload
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Class name.
	 *
	 * @return void
	 */
	function wcsdm_autoload( $class ) {
		$class = strtolower( $class );

		if ( false === strpos( $class, 'wcsdm' ) ) {
			return;
		}

		// Exclude legacy classes.
		if ( false !== strpos( $class, 'wcsdm_legacy_' ) ) {
			return;
		}

		if ( strpos( $class, 'wcsdm_api_provider_' ) === 0 ) {
			require_once WCSDM_PATH . 'includes/api-providers/' . wcsdm_convert_class_name_to_file_name( $class );
		} else {
			require_once WCSDM_PATH . 'includes/classes/' . wcsdm_convert_class_name_to_file_name( $class );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_is_calc_shipping' ) ) :
	/**
	 * Check if current request is shipping calculator form.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function wcsdm_is_calc_shipping():bool {
		$field  = 'woocommerce-shipping-calculator-nonce';
		$action = 'woocommerce-shipping-calculator';

		if ( isset( $_POST['calc_shipping'], $_POST[ $field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			return true;
		}

		return false;
	}
endif;

if ( ! function_exists( 'wcsdm_include_address_fields' ) ) :
	/**
	 * Get the list of WooCommerce address field keys used to calculate shipping distance.
	 *
	 * @since 2.1.6
	 *
	 * @return array An array of address field keys without the 'shipping_' prefix.
	 */
	function wcsdm_include_address_fields() {
		return array(
			'address_1',
			'address_2',
			'city',
			'state',
			'country',
			'postcode',
		);
	}
endif;

if ( ! function_exists( 'wcsdm_convert_file_path_to_class_name' ) ) :
	/**
	 * Convert a file path to a WooReer class name.
	 *
	 * This function transforms a hyphenated file name into an underscore-separated class name
	 * following WordPress coding standards. It removes the specified prefix and suffix from
	 * the file name, then converts each hyphenated segment into a capitalized word (or uses
	 * custom mappings for specific terms like 'api' -> 'API').
	 *
	 * Example usage:
	 * ```php
	 * wcsdm_convert_file_path_to_class_name('class-wcsdm-api-provider.php');
	 * // Returns: 'Wcsdm_API_Provider'
	 *
	 * wcsdm_convert_file_path_to_class_name('class-wcsdm-shipping-method.php');
	 * // Returns: 'Wcsdm_Shipping_Method'
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string      $file_path      The full file path or file name to convert.
	 * @param string|null $prefix         The prefix to remove from the file name (e.g., 'class'). Default 'class'.
	 * @param string|null $suffix         The file extension to remove (e.g., '.php'). Default '.php'.
	 * @param array|null  $custom_mapping Optional. Associative array mapping lowercase parts to their
	 *                                    preferred capitalization (e.g., ['api' => 'API']). Default is ['api' => 'API'].
	 * @return string The converted class name with underscores (e.g., 'Wcsdm_API_Provider').
	 */
	function wcsdm_convert_file_path_to_class_name( string $file_path, ?string $prefix = 'class', ?string $suffix = '.php', ?array $custom_mapping = null ):string {
		$file_basename = basename( $file_path, $suffix );
		$parts         = explode( '-', $file_basename );

		if ( null === $custom_mapping ) {
			$custom_mapping = array(
				'api' => 'API',
			);
		}

		$populated = array_reduce(
			$parts,
			function( $carry, $part ) use ( $custom_mapping, $prefix ) {
				if ( $part === $prefix ) {
					return $carry;
				}

				if ( isset( $custom_mapping[ $part ] ) ) {
					$carry[] = $custom_mapping[ $part ];
				} else {
					$carry[] = ucwords( $part );
				}

				return $carry;
			},
			array()
		);

		return implode( '_', $populated );
	}
endif;

if ( ! function_exists( 'wcsdm_convert_class_name_to_file_name' ) ) :
	/**
	 * Convert a WooReer class name to a file path.
	 *
	 * This function transforms an underscore-separated class name into a hyphenated file name
	 * following WordPress coding standards. It adds the specified prefix and suffix to
	 * the file name, then converts each underscore-separated segment into a lowercase word
	 * (or uses custom mappings for specific terms like 'API' -> 'api').
	 *
	 * Example usage:
	 * ```php
	 * wcsdm_convert_class_name_to_file_name('Wcsdm_API_Provider');
	 * // Returns: 'class-wcsdm-api-provider.php'
	 *
	 * wcsdm_convert_class_name_to_file_name('Wcsdm_Shipping_Method');
	 * // Returns: 'class-wcsdm-shipping-method.php'
	 * ```
	 *
	 * @since 1.0.0
	 *
	 * @param string      $class_name     The class name to convert (e.g., 'Wcsdm_API_Provider').
	 * @param string|null $prefix         The prefix to add to the file name (e.g., 'class'). Default 'class'.
	 * @param string|null $suffix         The file extension to add (e.g., '.php'). Default '.php'.
	 * @param array|null  $custom_mapping Optional. Associative array mapping uppercase parts to their
	 *                                    preferred lowercase form (e.g., ['API' => 'api']). Default is ['API' => 'api'].
	 * @return string The converted file name with hyphens (e.g., 'class-wcsdm-api-provider.php').
	 */
	function wcsdm_convert_class_name_to_file_name( string $class_name, ?string $prefix = 'class', ?string $suffix = '.php', ?array $custom_mapping = null ):string {
		$parts = explode( '_', $class_name );

		if ( null === $custom_mapping ) {
			$custom_mapping = array(
				'API' => 'api',
			);
		}

		$populated = array_reduce(
			$parts,
			function( $carry, $part ) use ( $custom_mapping ) {
				if ( isset( $custom_mapping[ $part ] ) ) {
					$carry[] = $custom_mapping[ $part ];
				} else {
					$carry[] = strtolower( $part );
				}

				return $carry;
			},
			$prefix ? array( $prefix ) : array()
		);

		return implode( '-', $populated ) . $suffix;
	}
endif;

if ( ! function_exists( 'wcsdm_validate_address_fields' ) ) :
	/**
	 * Validates and retrieves allowed shipping address fields with their requirements for WooReer.
	 *
	 * This function processes WooCommerce shipping address fields from the checkout configuration
	 * and filters them based on the fields allowed by WooReer (as defined by wcsdm_include_address_fields()).
	 * It merges the original checkout field properties with custom validation requirements that can be
	 * modified through filters, allowing developers to customize field requirements per address component.
	 *
	 * @since 2.1.6
	 *
	 * @return array An associative array of allowed shipping address fields. Each key is the field name
	 *               (without 'shipping_' prefix) and the value is an array containing:
	 *               - All original WooCommerce checkout field properties (label, type, class, etc.)
	 *               - 'required' (bool): Whether the field is required for validation
	 *               Returns an empty array if WC_Checkout is not available.
	 */
	function wcsdm_validate_address_fields():array {
		$fields          = array();
		$checkout_fields = WC_Checkout::instance()->get_checkout_fields( 'shipping' );
		$allowed_fields  = wcsdm_include_address_fields();

		foreach ( $allowed_fields as $allowed_field ) {
			$checkout_field = $checkout_fields[ 'shipping_' . $allowed_field ] ?? null;

			if ( ! $checkout_field ) {
				continue;
			}

			/**
			 * Filters whether a specific address field is required for WooReer shipping calculations.
			 *
			 * This dynamic filter allows developers to customize which address fields are required
			 * for distance calculations. The filter name includes the field name (e.g., 'city', 'state').
			 *
			 * @since 2.1.6
			 *
			 * @param bool $is_required Whether the field is required. Default from checkout field config.
			 */
			$is_required = apply_filters( 'wcsdm_validate_address_field_' . $allowed_field, ( $checkout_field['required'] ?? false ) );

			$fields[ $allowed_field ] = array_merge(
				$checkout_field,
				array(
					'required' => $is_required,
				)
			);
		}

		return $fields;
	}
endif;

if ( ! function_exists( 'wcsdm_validate_address' ) ) :
	/**
	 * Validates a comma-separated address string for WooReer shipping calculations.
	 *
	 * This function checks if the provided comma-separated address string contains enough
	 * components to satisfy the required shipping address fields as configured in the
	 * WooCommerce checkout settings. It splits the address by commas and compares the
	 * number of parts against the number of required fields.
	 *
	 * @since 2.1.6
	 *
	 * @param string $address A comma-separated string containing address components
	 *                        (e.g., "123 Main St, City, State, 12345, Country").
	 * @return bool True if the address has enough comma-separated parts to satisfy all
	 *              required fields, false otherwise.
	 */
	function wcsdm_validate_address( string $address ):bool {
		$address_parts   = explode( ',', $address );
		$allowed_fields  = wcsdm_validate_address_fields();
		$required_fields = array();

		foreach ( $allowed_fields as $key => $allowed_field ) {
			$is_required = $allowed_field['required'] ?? false;

			if ( $is_required ) {
				$required_fields[] = $key;
			}
		}

		return count( $address_parts ) >= count( $required_fields );
	}
endif;

if ( ! function_exists( 'wcsdm_validate_address_array' ) ) :
	/**
	 * Validates an array of address fields for WooReer shipping calculations.
	 *
	 * This function performs comprehensive validation on an address array by checking:
	 * 1. All provided fields are in the list of allowed WooReer address fields
	 * 2. All required fields have non-empty values (after trimming whitespace)
	 *
	 * Each field is validated against the WooCommerce checkout configuration to ensure
	 * it meets the requirements for shipping distance calculations.
	 *
	 * @since 2.1.6
	 *
	 * @param array $address_array An associative array of address fields where keys are field names
	 *                             (without 'shipping_' prefix) and values are the field values.
	 *                             Example: ['address_1' => '123 Main St', 'city' => 'New York', ...].
	 * @return bool True if all validations pass (only allowed fields present and all required fields
	 *              have non-empty values), false otherwise.
	 */
	function wcsdm_validate_address_array( array $address_array ):bool {
		$allowed_fields = wcsdm_validate_address_fields();

		foreach ( $address_array as $key => $value ) {
			$checkout_field = $allowed_fields[ $key ] ?? null;

			if ( ! $checkout_field ) {
				return false;
			}

			$is_required = $checkout_field['required'] ?? false;

			if ( $is_required && ! strlen( trim( $value ) ) ) {
				return false;
			}
		}

		return true;
	}
endif;

if ( ! function_exists( 'wcsdm_validate_coordinates' ) ) :
	/**
	 * Validate if a pair of geographic coordinates are valid for WooReer.
	 *
	 * This function validates both latitude and longitude values to ensure they fall within
	 * valid geographic coordinate ranges. It's a convenience wrapper that combines both
	 * wcsdm_validate_latitude() and wcsdm_validate_longitude() checks.
	 *
	 * @since 3.0
	 *
	 * @param string $latitude  The latitude value to validate (should be between -90 and 90 degrees).
	 * @param string $longitude The longitude value to validate (should be between -180 and 180 degrees).
	 * @return bool True if both latitude and longitude are within valid ranges, false if either is invalid.
	 */
	function wcsdm_validate_coordinates( string $latitude, string $longitude ):bool {
		return wcsdm_validate_latitude( $latitude ) && wcsdm_validate_longitude( $longitude );
	}
endif;

if ( ! function_exists( 'wcsdm_validate_latitude' ) ) :
	/**
	 * Validate if a latitude value is within valid geographic range.
	 *
	 * Latitude values must fall between -90 (South Pole) and 90 (North Pole) degrees.
	 * This function is used by WooReer to validate store and customer locations before
	 * performing distance calculations.
	 *
	 * @since 3.0
	 *
	 * @param string $latitude The latitude value to validate in decimal degrees.
	 * @return bool True if latitude is between -90 and 90 degrees (inclusive), false otherwise.
	 */
	function wcsdm_validate_latitude( string $latitude ):bool {
		return ( $latitude >= -90 && $latitude <= 90 );
	}
endif;

if ( ! function_exists( 'wcsdm_validate_longitude' ) ) :
	/**
	 * Validate if a longitude value is within valid geographic range.
	 *
	 * Longitude values must fall between -180 (International Date Line, west) and 180
	 * (International Date Line, east) degrees. This function is used by WooReer to validate
	 * store and customer locations before performing distance calculations.
	 *
	 * @since 3.0
	 *
	 * @param string $longitude The longitude value to validate in decimal degrees.
	 * @return bool True if longitude is between -180 and 180 degrees (inclusive), false otherwise.
	 */
	function wcsdm_validate_longitude( string $longitude ):bool {
		return ( $longitude >= -180 && $longitude <= 180 );
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_required' ) ) :
	/**
	 * Validates if a WooReer option field value meets the required field criteria.
	 *
	 * This function checks if a field marked as required has a non-empty value. It performs
	 * validation for string and scalar values by checking for empty strings (after trimming)
	 * and null values. For array values, use wcsdm_validate_option_required_array() instead.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (string, number, or other scalar type).
	 * @param array $field The field configuration array containing:
	 *                     - 'is_required' (bool): Whether the field is required.
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field is marked as required ('is_required' => true) but the value
	 *                   is empty (empty string after trim) or null. The exception message includes
	 *                   the field title for user-friendly error reporting.
	 */
	function wcsdm_validate_option_required( $value, array $field ):void {
		$is_required = $field['is_required'] ?? false;

		if ( $is_required && ( ! strlen( trim( $value ) ) || is_null( $value ) ) ) {
			// translators: %s is the field title.
			throw new Exception( wp_sprintf( __( '%s field is required', 'wcsdm' ), $field['title'] ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_required_array' ) ) :
	/**
	 * Validates if a WooReer array-type option field value meets the required field criteria.
	 *
	 * This function is specifically designed for array-type fields (such as multiselect fields)
	 * where the required validation needs to check if the array has at least one element.
	 * It uses PHP's empty() function which returns true for empty arrays.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (expected to be an array for proper validation).
	 * @param array $field The field configuration array containing:
	 *                     - 'is_required' (bool): Whether the field is required.
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field is marked as required ('is_required' => true) but the value
	 *                   is empty (evaluates to true with empty()). The exception message includes
	 *                   the field title for user-friendly error reporting.
	 */
	function wcsdm_validate_option_required_array( $value, array $field ):void {
		$is_required = $field['is_required'] ?? false;

		if ( $is_required && empty( $value ) ) {
			// translators: %s is the field title.
			throw new Exception( wp_sprintf( __( '%s field is required', 'wcsdm' ), $field['title'] ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_min' ) ) :
	/**
	 * Validates if a WooReer numeric option field value meets the minimum value requirement.
	 *
	 * This function checks if a numeric field value is greater than or equal to the minimum
	 * value specified in the field's custom attributes. The validation only runs if the value
	 * is not empty and a minimum value is defined in the field configuration.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (should be numeric).
	 * @param array $field The field configuration array containing:
	 *                     - 'custom_attributes' (array): Contains 'min' key with the minimum allowed value.
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field value is less than the minimum value defined in
	 *                   $field['custom_attributes']['min']. The exception message includes
	 *                   the field title and minimum value for user-friendly error reporting.
	 */
	function wcsdm_validate_option_min( $value, array $field ):void {
		if ( ! strlen( $value ) ) {
			return;
		}

		$min = $field['custom_attributes']['min'] ?? null;

		if ( isset( $min ) && $value < $min ) {
			// translators: %1$s is the field title, %2$d is the minimum value.
			throw new Exception( wp_sprintf( __( '%1$s field value cannot be lower than %2$d', 'wcsdm' ), $field['title'], $field['custom_attributes']['min'] ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_max' ) ) :
	/**
	 * Validates if a WooReer numeric option field value meets the maximum value requirement.
	 *
	 * This function checks if a numeric field value is less than or equal to the maximum
	 * value specified in the field's custom attributes. The validation only runs if the value
	 * is not empty and a maximum value is defined in the field configuration.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (should be numeric).
	 * @param array $field The field configuration array containing:
	 *                     - 'custom_attributes' (array): Contains 'max' key with the maximum allowed value.
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field value is greater than the maximum value defined in
	 *                   $field['custom_attributes']['max']. The exception message includes
	 *                   the field title and maximum value for user-friendly error reporting.
	 */
	function wcsdm_validate_option_max( $value, array $field ):void {
		if ( ! strlen( $value ) ) {
			return;
		}

		$max = $field['custom_attributes']['max'] ?? null;

		if ( isset( $max ) && $value > $max ) {
			// translators: %1$s is the field title, %2$d is the maximum value.
			throw new Exception( wp_sprintf( __( '%1$s field value cannot be greater than %2$d', 'wcsdm' ), $field['title'], $max ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_step' ) ) :
	/**
	 * Validates if a WooReer numeric option field value meets the step increment requirement.
	 *
	 * This function checks if a numeric field value is a multiple of the step value specified
	 * in the field's custom attributes. The step attribute defines the granularity of allowed
	 * values (e.g., step="5" means only multiples of 5 are valid: 0, 5, 10, 15, etc.).
	 * If the step is set to "any", no validation is performed. The validation only runs if
	 * the value is not empty and a valid integer step is defined.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (must be an integer when step validation applies).
	 * @param array $field The field configuration array containing:
	 *                     - 'custom_attributes' (array): Contains 'step' key with the increment value or 'any'.
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field value is not an integer or is not a multiple of the step value
	 *                   defined in $field['custom_attributes']['step']. The exception message includes
	 *                   the field title and step value for user-friendly error reporting.
	 */
	function wcsdm_validate_option_step( $value, array $field ):void {
		if ( ! strlen( $value ) ) {
			return;
		}

		$step = $field['custom_attributes']['step'] ?? 'any';

		if ( 'any' === $step ) {
			return;
		}

		$step = filter_var( $step, FILTER_VALIDATE_INT );

		if ( ! $step ) {
			return;
		}

		if ( false === filter_var( $value, FILTER_VALIDATE_INT ) ) {
			// translators: %s is the field title.
			throw new Exception( wp_sprintf( __( '%s field value must be an integer', 'wcsdm' ), $field['title'] ) );
		}

		if ( 0 !== ( $value % $step ) ) {
			// translators: %1$s is the field title, %2$d is the step value.
			throw new Exception( wp_sprintf( __( '%1$s field value must be a multiple of %2$d', 'wcsdm' ), $field['title'], $step ) );
		}
	}
endif;


if ( ! function_exists( 'wcsdm_validate_option_type_latitude' ) ) :
	/**
	 * Validates if a WooReer option field value is a valid latitude coordinate.
	 *
	 * This function performs comprehensive validation to ensure a field value is a valid
	 * latitude coordinate. It checks that the value is numeric and falls within the valid
	 * latitude range of -90 to 90 degrees. The validation only runs if the value is not empty.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (should be numeric or numeric string).
	 * @param array $field The field configuration array containing:
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field value is not numeric or is outside the valid latitude
	 *                   range (-90 to 90 degrees). The exception message includes the field
	 *                   title for user-friendly error reporting.
	 */
	function wcsdm_validate_option_type_latitude( $value, array $field ):void {
		if ( ! strlen( $value ) ) {
			return;
		}

		if ( ! ( is_numeric( $value ) && $value >= -90 && $value <= 90 ) ) {
			// translators: %s is the field title.
			throw new Exception( wp_sprintf( __( '%s field value is invalid', 'wcsdm' ), $field['title'] ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_type_longitude' ) ) :
	/**
	 * Validates if a WooReer option field value is a valid longitude coordinate.
	 *
	 * This function performs comprehensive validation to ensure a field value is a valid
	 * longitude coordinate. It checks that the value is numeric and falls within the valid
	 * longitude range of -180 to 180 degrees. The validation only runs if the value is not empty.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (should be numeric or numeric string).
	 * @param array $field The field configuration array containing:
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the field value is not numeric or is outside the valid longitude
	 *                   range (-180 to 180 degrees). The exception message includes the field
	 *                   title for user-friendly error reporting.
	 */
	function wcsdm_validate_option_type_longitude( $value, array $field ):void {
		if ( ! strlen( $value ) ) {
			return;
		}

		if ( ! ( is_numeric( $value ) && $value >= -180 && $value <= 180 ) ) {
			// translators: %s is the field title.
			throw new Exception( wp_sprintf( __( '%s field value is invalid', 'wcsdm' ), $field['title'] ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_type_multiselect' ) ) :
	/**
	 * Validates if a WooReer multiselect option field value is valid.
	 *
	 * This function performs validation for multiselect field types by:
	 * 1. Ensuring the value is an array (multiselect fields must be arrays)
	 * 2. Verifying that each selected value exists in the field's available options
	 *
	 * The validation only runs if the value is not empty/falsy.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (should be an array of selected option keys).
	 * @param array $field The field configuration array containing:
	 *                     - 'options' (array): Associative array of valid options (key => label).
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the value is not an array or when any selected value is not found
	 *                   in the field's options array. The exception message includes the field
	 *                   title for user-friendly error reporting.
	 */
	function wcsdm_validate_option_type_multiselect( $value, array $field ):void {
		if ( $value ) {
			if ( ! is_array( $value ) ) {
				// translators: %s is the field title.
				throw new Exception( wp_sprintf( __( '%s field value is invalid', 'wcsdm' ), $field['title'] ) );
			}

			// Check if all selected values are valid options.
			foreach ( $value as $selected_value ) {
				if ( ! array_key_exists( $selected_value, $field['options'] ) ) {
					// translators: %s is the field title.
					throw new Exception( wp_sprintf( __( '%s field value is invalid', 'wcsdm' ), $field['title'] ) );
				}
			}
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option_type_select' ) ) :
	/**
	 * Validates if a WooReer select option field value is valid.
	 *
	 * This function ensures that the selected value exists in the field's available options.
	 * It checks if the provided value is a valid key in the options array defined for the
	 * select field. The validation only runs if the value is not empty.
	 *
	 * @since 3.0
	 *
	 * @param mixed $value The field value to validate (should be a string matching an option key).
	 * @param array $field The field configuration array containing:
	 *                     - 'options' (array): Associative array of valid options (key => label).
	 *                     - 'title' (string): The field title/label used in error messages.
	 * @return void
	 * @throws Exception When the selected value is not found as a key in the field's options array.
	 *                   The exception message includes the field title for user-friendly error reporting.
	 */
	function wcsdm_validate_option_type_select( $value, array $field ):void {
		if ( ! strlen( $value ) ) {
			return;
		}

		if ( ! array_key_exists( $value, $field['options'] ) ) {
			// translators: %s is the field title.
			throw new Exception( wp_sprintf( __( '%s field value is invalid', 'wcsdm' ), $field['title'] ) );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_validate_option' ) ) :
	/**
	 * Validate a WooReer shipping method option field value.
	 *
	 * This is the main validation orchestrator function that routes field validation to the
	 * appropriate validation functions based on the field type. It handles validation for
	 * various field types including multiselect, select, latitude, longitude, numeric fields,
	 * and text fields. All validation errors are caught and returned as WP_Error objects.
	 *
	 * @since 3.0
	 *
	 * @param mixed                 $value    The field value to validate.
	 * @param array                 $field    The field configuration array containing field properties
	 *                                        such as 'type', 'title', 'is_required', 'custom_attributes', etc.
	 * @param string                $key      The unique field key/identifier.
	 * @param Wcsdm_Shipping_Method $instance The WooReer shipping method instance containing this field.
	 * @return WP_Error|null Returns WP_Error object with error code 'wcsdm_field_validation_error_{$key}'
	 *                       and error message if validation fails, null if validation passes.
	 */
	function wcsdm_validate_option( $value, array $field, string $key, Wcsdm_Shipping_Method $instance ):?WP_Error {
		try {
			switch ( $instance->get_field_type( $field ) ) {
				case 'multiselect':
					wcsdm_validate_option_required_array( $value, $field );
					wcsdm_validate_option_type_multiselect( $value, $field );
					break;

				case 'select':
					wcsdm_validate_option_required( $value, $field );
					wcsdm_validate_option_type_select( $value, $field );
					break;

				case 'latitude':
					wcsdm_validate_option_required( $value, $field );
					wcsdm_validate_option_type_latitude( $value, $field );
					break;

				case 'longitude':
					wcsdm_validate_option_required( $value, $field );
					wcsdm_validate_option_type_longitude( $value, $field );
					break;

				case 'number':
					wcsdm_validate_option_required( $value, $field );
					wcsdm_validate_option_min( $value, $field );
					wcsdm_validate_option_max( $value, $field );
					wcsdm_validate_option_step( $value, $field );
					break;

				case 'decimal':
				case 'price':
					wcsdm_validate_option_required( $value, $field );
					wcsdm_validate_option_min( $value, $field );
					wcsdm_validate_option_max( $value, $field );
					break;

				default:
					wcsdm_validate_option_required( $value, $field );
					break;
			}

			/**
			 * Fires after built-in WooReer option field validation.
			 *
			 * This action hook allows developers to add custom validation logic for specific
			 * option fields or implement additional validation rules beyond the built-in
			 * validators. Throw an Exception to indicate validation failure.
			 *
			 * @since 3.0
			 *
			 * @param mixed                 $value    The field value being validated.
			 * @param string                $key      The field key/identifier.
			 * @param Wcsdm_Shipping_Method $instance The shipping method instance.
			 * @throws Exception Should throw an Exception with a user-friendly error message when validation fails.
			 */
			do_action( 'wcsdm_validate_option', $value, $key, $instance );

			return null;
		} catch ( Exception $e ) {
			return new WP_Error( 'wcsdm_field_validation_error_' . $key, $e->getMessage() );
		}
	}
endif;

if ( ! function_exists( 'wcsdm_str_starts_with' ) ) :
	/**
	 * Check if a string starts with a given substring (polyfill for PHP 8+).
	 *
	 * This function provides backward compatibility for the PHP 8.0+ str_starts_with() function.
	 * It checks whether the haystack string begins with the needle substring. If the native
	 * PHP function exists, it delegates to that; otherwise, it provides a fallback implementation.
	 *
	 * @since 3.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for at the start of the haystack.
	 * @return bool True if the haystack starts with the needle, false otherwise.
	 *              Returns true if the needle is an empty string.
	 */
	function wcsdm_str_starts_with( string $haystack, string $needle ):bool {
		if ( function_exists( 'str_starts_with' ) ) {
			return str_starts_with( $haystack, $needle );
		}

		$length = strlen( $needle );

		if ( 0 === $length ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
endif;

if ( ! function_exists( 'wcsdm_str_ends_with' ) ) :
	/**
	 * Check if a string ends with a given substring (polyfill for PHP 8+).
	 *
	 * This function provides backward compatibility for the PHP 8.0+ str_ends_with() function.
	 * It checks whether the haystack string ends with the needle substring. If the native
	 * PHP function exists, it delegates to that; otherwise, it provides a fallback implementation.
	 *
	 * @since 3.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for at the end of the haystack.
	 * @return bool True if the haystack ends with the needle, false otherwise.
	 *              Returns true if the needle is an empty string.
	 */
	function wcsdm_str_ends_with( string $haystack, string $needle ):bool {
		if ( function_exists( 'str_ends_with' ) ) {
			return str_ends_with( $haystack, $needle );
		}

		$length = strlen( $needle );

		if ( 0 === $length ) {
			return true;
		}

		return substr( $haystack, -$length ) === $needle;
	}
endif;

if ( ! function_exists( 'wcsdm_mask_string' ) ) :
	/**
	 * Masks a string by replacing middle characters for security/privacy purposes.
	 *
	 * This function is useful for displaying sensitive information (like API keys, tokens, or
	 * passwords) in WooReer settings while keeping some characters visible for identification.
	 * For strings longer than a threshold (10 characters by default), it shows a specified
	 * number of characters at the start and end, masking the middle. For shorter strings,
	 * it masks all characters.
	 *
	 * @since 3.0
	 *
	 * @param string $string     The string to be masked (e.g., API key, token).
	 * @param string $mask_char  The character to use for masking. Defaults to '*'.
	 * @param int    $show_start Number of characters to keep visible at the start. Defaults to 3.
	 * @param int    $show_end   Number of characters to keep visible at the end. Defaults to 3.
	 * @return string The masked string. Format for long strings: 'abc***xyz' where 'abc' is the start,
	 *                '***' represents masked middle characters, and 'xyz' is the end. Short strings
	 *                are fully masked (e.g., '******').
	 */
	function wcsdm_mask_string( $string, string $mask_char = '*', int $show_start = 3, int $show_end = 3 ):string {
		$string          = (string) $string;
		$length          = mb_strlen( $string );
		$mask_all_length = $show_start + $show_end + 4;

		// If string is longer than 10 characters.
		if ( $length > $mask_all_length ) {
			$first         = mb_substr( $string, 0, $show_start );
			$last          = mb_substr( $string, ( 0 - $show_end ) );
			$middle_length = $length - ( $show_start + $show_end );

			$masked = $first . str_repeat( $mask_char, $middle_length ) . $last;
		} else {
			// Mask all characters.
			$masked = str_repeat( $mask_char, $length );
		}

		return $masked;
	}
endif;

if ( ! function_exists( 'wcsdm_array_find' ) ) :
	/**
	 * Finds the first element in an array that satisfies a callback function.
	 *
	 * This function iterates through an array and applies a callback function to each element.
	 * It returns the first element for which the callback returns true. If no element matches,
	 * it returns null.
	 *
	 * @since 3.0
	 *
	 * @param array    $array    The array to search.
	 * @param callable $callback The callback function to execute for each item.
	 *                           Signature: function($item, $key)
	 *                           Should return true to stop searching and return the current item.
	 * @return mixed|null The found element or null if not found.
	 */
	function wcsdm_array_find( array $array, callable $callback ) {
		foreach ( $array as $key => $item ) {
			if ( call_user_func( $callback, $item, $key ) ) {
				return $item;
			}
		}

		return null;
	}
endif;

if ( ! function_exists( 'wcsdm_array_has' ) ) :
	/**
	 * Checks if a nested path exists in an array.
	 *
	 * This function traverses through a nested/multidimensional array structure using a path
	 * specified as an array of keys. It verifies that each level of the path exists and that
	 * intermediate values are arrays (except the final key, which can be any type).
	 *
	 * Example usage:
	 * ```php
	 * $data = ['user' => ['profile' => ['name' => 'John']]];
	 * wcsdm_array_has($data, ['user', 'profile', 'name']); // Returns true
	 * wcsdm_array_has($data, ['user', 'profile', 'age']); // Returns false
	 * ```
	 *
	 * @since 3.0
	 *
	 * @param array $array The array to check for the path.
	 * @param array $path  An array of keys representing the nested path to check.
	 *                     Example: ['key1', 'key2', 'key3'] checks for $array['key1']['key2']['key3'].
	 * @return bool True if the complete path exists in the array, false otherwise.
	 */
	function wcsdm_array_has( array $array, array $path ):bool {
		if ( ! $path ) {
			return false;
		}

		$cursor = $array;

		foreach ( $path as $key ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
				return false;
			}

			$cursor = $cursor[ $key ];
		}

		return true;
	}
endif;

if ( ! function_exists( 'wcsdm_array_get' ) ) :
	/**
	 * Safely retrieves a value from a nested array using a path array.
	 *
	 * This function provides safe access to deeply nested array values without triggering
	 * PHP notices or warnings. It traverses the array structure using the provided path,
	 * returning a default value if any part of the path is missing or invalid.
	 *
	 * Example usage:
	 * ```php
	 * $data = ['user' => ['profile' => ['name' => 'John']]];
	 * wcsdm_array_get($data, ['user', 'profile', 'name']); // Returns 'John'
	 * wcsdm_array_get($data, ['user', 'profile', 'age'], 25); // Returns 25 (default)
	 * wcsdm_array_get($data, []); // Returns the entire $data array
	 * ```
	 *
	 * @since 3.0
	 *
	 * @param array $array   The array to retrieve the value from.
	 * @param array $path    An array of keys that form the path to the desired value.
	 *                       Example: ['key1', 'key2', 'key3'] accesses $array['key1']['key2']['key3'].
	 *                       Pass an empty array to return the entire input array.
	 * @param mixed $default The value to return if the path doesn't exist or any intermediate
	 *                       value is not an array. Defaults to null.
	 * @return mixed The value at the specified path, or $default if the path is invalid or doesn't exist.
	 */
	function wcsdm_array_get( array $array, array $path, $default = null ) {
		// If path is empty, return the array itself.
		if ( empty( $path ) ) {
			return $array;
		}

		$current = $array;

		foreach ( $path as $key ) {
			// Check if current value is an array and key exists.
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return $default;
			}

			$current = $current[ $key ] ?? null;
		}

		// Return null as default if the final value is null, otherwise return the value.
		return $current ?? $default;
	}
endif;

if ( ! function_exists( 'wcsdm_array_set' ) ) :
	/**
	 * Sets a value in a nested array using a path array (immutable operation).
	 *
	 * This function provides an immutable way to set values in deeply nested arrays. Instead
	 * of modifying the original array, it returns a new array with the value set at the
	 * specified path. It automatically creates intermediate array structures as needed.
	 *
	 * The function maintains immutability through a copy-on-write approach, making it safe
	 * for use in functional programming patterns and preventing unintended side effects.
	 *
	 * Example usage:
	 * ```php
	 * $data = ['user' => ['profile' => ['name' => 'John']]];
	 * $updated = wcsdm_array_set($data, ['user', 'profile', 'age'], 30);
	 * // $updated = ['user' => ['profile' => ['name' => 'John', 'age' => 30]]]
	 * // $data remains unchanged
	 * ```
	 *
	 * @since 3.0
	 *
	 * @param array $array    The input array to modify (not modified directly; a copy is returned).
	 * @param array $segments An array of keys that form the path to the target location.
	 *                        Example: ['key1', 'key2', 'key3'] sets $array['key1']['key2']['key3'].
	 *                        Pass an empty array for a no-op that returns the original array unchanged.
	 * @param mixed $value    The value to set at the target location.
	 * @return array A new array with the value set at the specified path. Intermediate arrays
	 *               are created automatically if they don't exist.
	 */
	function wcsdm_array_set( array $array, array $segments, $value ):array {
		if ( ! $segments ) {
			return $array; // no-op, keep immutable contract.
		}

		$key = array_shift( $segments );

		// Copy-on-write clone of current level.
		$next = isset( $array[ $key ] ) && is_array( $array[ $key ] ) ? $array[ $key ] : array();

		if ( $segments ) {
			$array[ $key ] = wcsdm_array_set( $next, $segments, $value );
		} else {
			$array[ $key ] = $value;
		}

		return $array;
	}
endif;

if ( ! function_exists( 'wcsdm_array_map_deep' ) ) :
	/**
	 * Recursively applies a callback function to all leaf values in an array.
	 *
	 * This function traverses through a nested/multidimensional array structure and applies
	 * a callback function to every non-array (leaf) value. It provides path tracking, passing
	 * the current path to the callback as an array of keys. This is useful for deep transformations
	 * where you need to know the location of each value in the nested structure.
	 *
	 * The function processes arrays recursively, maintaining the original array structure while
	 * transforming only the leaf values (strings, numbers, objects, etc.). The callback receives
	 * both the value and its path in the array hierarchy.
	 *
	 * Example usage:
	 * ```php
	 * $data = ['user' => ['profile' => ['name' => 'john', 'age' => '25']]];
	 * $result = wcsdm_array_map_deep($data, function($value, $path) {
	 *     // Uppercase string values
	 *     return is_string($value) ? strtoupper($value) : $value;
	 * });
	 * // Result: ['user' => ['profile' => ['name' => 'JOHN', 'age' => '25']]]
	 * ```
	 *
	 * @since 3.0
	 *
	 * @param mixed    $value       The value to process. Can be an array (for recursion) or any other type
	 *                              (which will be passed to the callback).
	 * @param callable $callback    The callback function to apply to leaf values.
	 *                              Signature: function($value, $path) where:
	 *                              - $value (mixed): The current leaf value.
	 *                              - $path (array): Array of keys representing the path to this value.
	 * @param array    $parent_path Internal parameter for tracking the path during recursion.
	 *                              Should not be provided by callers (defaults to empty array).
	 * @return mixed The processed value with the callback applied to all leaf values.
	 *               Arrays maintain their structure; leaf values are transformed by the callback.
	 */
	function wcsdm_array_map_deep( $value, callable $callback, array $parent_path = array() ) {
		if ( is_array( $value ) ) {
			// Recurse into arrays.
			foreach ( $value as $index => $item ) {
				$value[ $index ] = wcsdm_array_map_deep(
					$item,
					$callback,
					array_merge( $parent_path, array( $index ) )
				);
			}
		} elseif ( is_object( $value ) ) {
			// Recurse into objects.
			foreach ( get_object_vars( $value ) as $property => $property_value ) {
				$value->{$property} = wcsdm_array_map_deep(
					$property_value,
					$callback,
					array_merge( $parent_path, array( $property ) )
				);
			}
		} else {
			// Apply callback to leaf values.
			$value = call_user_func( $callback, $value, $parent_path );
		}

		return $value;
	}
endif;
