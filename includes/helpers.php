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

/**
 * Check if plugin is active
 *
 * @param string $plugin_file Plugin file name.
 */
function wcsdm_is_plugin_active( $plugin_file ) {
	$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}

	return in_array( $plugin_file, $active_plugins, true ) || array_key_exists( $plugin_file, $active_plugins );
}

/**
 * Get i18n strings
 *
 * @param string $key Strings key.
 * @param string $default Default value.
 * @return mixed
 */
function wcsdm_i18n( $key = '', $default = '' ) {
	$i18n = array(
		'Add New Rate'                                 => __( 'Add New Rate', 'wcsdm' ),
		'Address'                                      => __( 'Address', 'wcsdm' ),
		'Apply Changes'                                => __( 'Apply Changes', 'wcsdm' ),
		'Cancel'                                       => __( 'Cancel', 'wcsdm' ),
		'Confirm Delete'                               => __( 'Confirm Delete', 'wcsdm' ),
		'Delete Selected Rates'                        => __( 'Delete Selected Rates', 'wcsdm' ),
		'Please drag this marker or enter your address in the input field above.' => __( 'Please drag this marker or enter your address in the input field above.', 'wcsdm' ),
		'Latitude'                                     => __( 'Latitude', 'wcsdm' ),
		'Longitude'                                    => __( 'Longitude', 'wcsdm' ),
		'Save Changes'                                 => __( 'Save Changes', 'wcsdm' ),
		'Store Location Picker'                        => __( 'Store Location Picker', 'wcsdm' ),
		// translators: %s = Field name.
		'%s field is required.'                        => __( '%s field is required.', 'wcsdm' ),
		// translators: %s = Field name.
		'%s field value must be numeric.'              => __( '%s field value must be numeric.', 'wcsdm' ),
		// translators: %1$s = Field name, %2$d = Minimum field value rule.
		'%1$s field value cannot be lower than %2$.d'  => __( '%1$s field value cannot be lower than %2$.d', 'wcsdm' ),
		// translators: %1$s = Field name, %2$d = Maximum field value rule.
		'%1$s field value cannot be greater than %2$d' => __( '%1$s field value cannot be greater than %2$d', 'wcsdm' ),
		// translators: %1$d = row number, %2$s = error message.
		'Shipping rules combination duplicate with rate row #%1$d: %2$s.' => __( 'Shipping rules combination duplicate with rate row #%1$d: %2$s.', 'wcsdm' ),
		// translators: %1$d = row number, %2$s = error message.
		'Table rate row #%1$d: %2$s.'                  => __( 'Table rate row #%1$d: %2$s.', 'wcsdm' ),
		'Table rates data is incomplete or invalid!'   => __( 'Table rates data is incomplete or invalid!', 'wcsdm' ),
	);

	if ( ! empty( $key ) && is_string( $key ) ) {
		if ( isset( $i18n[ $key ] ) ) {
			return $i18n[ $key ];
		}

		if ( '' !== $default ) {
			return $default;
		}

		return $key;
	}

	return $i18n;
}

/**
 * Get shipping method instances
 *
 * @since 2.0
 *
 * @param bool $enabled_only Filter to includes only enabled instances.
 * @return array
 */
function wcsdm_instances( $enabled_only = true ) {
	$instances = array();

	$zone_data_store = new WC_Shipping_Zone_Data_Store();

	$shipping_methods = $zone_data_store->get_methods( '0', $enabled_only );

	if ( $shipping_methods ) {
		foreach ( $shipping_methods as $shipping_method ) {
			if ( WCSDM_METHOD_ID !== $shipping_method->method_id ) {
				continue;
			}

			$instances[] = array(
				'zone_id'     => 0,
				'method_id'   => $shipping_method->method_id,
				'instance_id' => $shipping_method->instance_id,
			);
		}
	}

	$zones = WC_Shipping_Zones::get_zones();

	if ( ! empty( $zones ) ) {
		foreach ( $zones as $zone ) {
			$shipping_methods = $zone_data_store->get_methods( $zone['id'], $enabled_only );
			if ( $shipping_methods ) {
				foreach ( $shipping_methods as $shipping_method ) {
					if ( WCSDM_METHOD_ID !== $shipping_method->method_id ) {
						continue;
					}

					$instances[] = array(
						'zone_id'     => 0,
						'method_id'   => $shipping_method->method_id,
						'instance_id' => $shipping_method->instance_id,
					);
				}
			}
		}
	}

	return apply_filters( 'wcsdm_instances', $instances );
}

/**
 * Inserts a new key/value before the key in the array.
 *
 * @since 2.0.7
 *
 * @param string $before_key The key to insert before.
 * @param array  $array An array to insert in to.
 * @param string $new_key The new key to insert.
 * @param mixed  $new_value The new value to insert.
 *
 * @return array
 */
function wcsdm_array_insert_before( $before_key, $array, $new_key, $new_value ) {
	if ( ! array_key_exists( $before_key, $array ) ) {
		return $array;
	}

	$new = array();

	foreach ( $array as $k => $value ) {
		if ( $k === $before_key ) {
			$new[ $new_key ] = $new_value;
		}

		$new[ $k ] = $value;
	}

	return $new;
}

/**
 * Inserts a new key/value after the key in the array.
 *
 * @since 2.0.7
 *
 * @param string $after_key The key to insert after.
 * @param array  $array An array to insert in to.
 * @param string $new_key The new key to insert.
 * @param mixed  $new_value The new value to insert.
 *
 * @return array
 */
function wcsdm_array_insert_after( $after_key, $array, $new_key, $new_value ) {
	if ( ! array_key_exists( $after_key, $array ) ) {
		return $array;
	}

	$new = array();

	foreach ( $array as $k => $value ) {
		$new[ $k ] = $value;

		if ( $k === $after_key ) {
			$new[ $new_key ] = $new_value;
		}
	}

	return $new;
}

/**
 * Check is in development environment.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function wcsdm_is_dev_env() {
	if ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) {
		return true;
	}

	if ( function_exists( 'getenv' ) && getenv( 'WCSDM_DEV' ) ) {
		return true;
	}

	return false;
}

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

		if ( strpos( $class, 'wcsdm' ) !== 0 ) {
			return;
		}

		if ( strpos( $class, 'wcsdm_migration_' ) === 0 ) {
			require_once WCSDM_PATH . 'includes/migrations/class-' . str_replace( '_', '-', $class ) . '.php';
		} else {
			require_once WCSDM_PATH . 'includes/classes/class-' . str_replace( '_', '-', $class ) . '.php';
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
	function wcsdm_is_calc_shipping() {
		$field  = 'woocommerce-shipping-calculator-nonce';
		$action = 'woocommerce-shipping-calculator';

		if ( isset( $_POST['calc_shipping'], $_POST[ $field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
			return true;
		}

		return false;
	}
endif;

if ( ! function_exists( 'wcsdm_calc_shipping_field_value' ) ) :
	/**
	 * Get calculated shipping for fields value.
	 *
	 * @since 2.1.3
	 *
	 * @param string $input_name Input name.
	 *
	 * @return mixed|bool False on failure
	 */
	function wcsdm_calc_shipping_field_value( $input_name ) {
		$nonce_field  = 'woocommerce-shipping-calculator-nonce';
		$nonce_action = 'woocommerce-shipping-calculator';

		if ( isset( $_POST['calc_shipping'], $_POST[ $input_name ], $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) );
		}

		return false;
	}
endif;

if ( ! function_exists( 'wcsdm_sort_address_fields' ) ) :
	/**
	 * Get address fields.
	 *
	 * @since 2.1.6
	 *
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	function wcsdm_sort_address_fields( $a, $b ) {
		if ( $a['priority'] === $b['priority'] ) {
			return 0;
		}

		return ( $a['priority'] > $b['priority'] ) ? -1 : 1;
	}
endif;

if ( ! function_exists( 'wcsdm_include_address_fields' ) ) :
	/**
	 * Get included address fields keys.
	 *
	 * @since 2.1.6
	 *
	 * @return array
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

if ( ! function_exists( 'wcsdm_address_fields' ) ) :
	/**
	 * Get address fields.
	 *
	 * @since 2.1.6
	 *
	 * @param string $address_type Address type: billing or shipping.
	 * @return array|bool Will return false on failure.
	 */
	function wcsdm_address_fields( $address_type ) {
		if ( 'shipping' === $address_type && wc_ship_to_billing_address_only() ) {
			$address_type = 'billing';
		}

		$includes = wcsdm_include_address_fields();

		$fields = array();

		foreach ( WC()->checkout->get_checkout_fields( $address_type ) as $key => $field ) {
			$keys = explode( '_', $key );

			if ( isset( $keys[0] ) && $address_type === $keys[0] ) {
				unset( $keys[0] );
			}

			$key = implode( '_', $keys );

			if ( ! in_array( $key, $includes, true ) ) {
				continue;
			}

			$fields[ $key ] = $field;
		}

		if ( $fields ) {
			uasort( $fields, 'wcsdm_sort_address_fields' );
		}

		return $fields;
	}
endif;

if ( ! function_exists( 'wcsdm_shipping_fields' ) ) :
	/**
	 * Get shipping fields.
	 *
	 * @since 2.1.5
	 *
	 * @return array
	 */
	function wcsdm_shipping_fields() {
		return wcsdm_address_fields( 'shipping' );
	}
endif;

if ( ! function_exists( 'wcsdm_billing_fields' ) ) :
	/**
	 * Get billing fields.
	 *
	 * @since 2.1.6
	 *
	 * @return array
	 */
	function wcsdm_billing_fields() {
		return wcsdm_address_fields( 'billing' );
	}
endif;

if ( ! function_exists( 'wcsdm_calc_shipping_fields' ) ) :
	/**
	 * Get shipping calculator fields.
	 *
	 * @since 2.1.6
	 *
	 * @return array
	 */
	function wcsdm_calc_shipping_fields() {
		$fields = array();

		foreach ( wcsdm_address_fields( 'shipping' ) as $key => $field ) {
			if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_' . $key, true ) ) { // phpcs:ignore WordPress.NamingConventions
				continue;
			}

			$fields[ $key ] = $field;
		}

		return $fields;
	}
endif;
