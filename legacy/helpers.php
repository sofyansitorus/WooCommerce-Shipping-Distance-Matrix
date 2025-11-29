<?php
/**
 * WooReer Legacy Helper Functions
 *
 * This file contains helper functions for backward compatibility with older versions
 * of WooReer. These functions maintain support for legacy features and provide
 * compatibility layers for code written against previous API versions.
 *
 * @package    Wcsdm
 * @subpackage Legacy
 * @since      1.5.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! function_exists( 'wcsdm_legacy_autoload' ) ) :
	/**
	 * Autoloader for WooReer legacy classes.
	 *
	 * This function automatically loads legacy classes when they are instantiated,
	 * providing backward compatibility for older WooReer versions. It handles both
	 * legacy migration classes and general legacy classes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class The fully-qualified class name to autoload.
	 * @return void
	 */
	function wcsdm_legacy_autoload( $class ) {
		$class = strtolower( $class );

		// Exclude non-legacy classes.
		if ( 0 !== strpos( $class, 'wcsdm_legacy_' ) ) {
			return;
		}

		if ( 0 === strpos( $class, 'wcsdm_legacy_migration_' ) ) {
			require_once WCSDM_PATH . 'legacy/migrations/class-' . str_replace( '_', '-', $class ) . '.php';
		} else {
			require_once WCSDM_PATH . 'legacy/classes/class-' . str_replace( '_', '-', $class ) . '.php';
		}
	}
endif;

if ( ! function_exists( 'wcsdm_calc_shipping_fields' ) ) :
	/**
	 * Get enabled shipping calculator fields for WooReer.
	 *
	 * This function retrieves all shipping address fields that are enabled
	 * in the WooCommerce shipping calculator. Fields can be filtered using
	 * the 'woocommerce_shipping_calculator_enable_{field_key}' filter.
	 *
	 * Used for legacy compatibility with older WooReer versions.
	 *
	 * @since 2.1.6
	 *
	 * @return array Array of enabled shipping calculator fields with field configurations.
	 */
	function wcsdm_calc_shipping_fields() {
		$fields = array();

		foreach ( wcsdm_address_fields( 'shipping' ) as $key => $field ) {
			/**
			 * Filters whether a specific shipping calculator field is enabled.
			 *
			 * The dynamic portion of the hook name, $key, refers to the field key.
			 *
			 * @since 2.1.6
			 *
			 * @param bool $enabled Whether the field is enabled. Default true.
			 */
			if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_' . $key, true ) ) { // phpcs:ignore WordPress.NamingConventions
				continue;
			}

			$fields[ $key ] = $field;
		}

		return $fields;
	}
endif;

if ( ! function_exists( 'wcsdm_shipping_fields' ) ) :
	/**
	 * Get WooCommerce shipping address fields for WooReer.
	 *
	 * Retrieves all configured shipping address fields from WooCommerce checkout.
	 * Used for legacy compatibility with older WooReer versions.
	 *
	 * @since 2.1.5
	 *
	 * @return array Array of shipping address fields with field configurations.
	 */
	function wcsdm_shipping_fields() {
		return wcsdm_address_fields( 'shipping' );
	}
endif;

if ( ! function_exists( 'wcsdm_billing_fields' ) ) :
	/**
	 * Get WooCommerce billing address fields for WooReer.
	 *
	 * Retrieves all configured billing address fields from WooCommerce checkout.
	 * Used for legacy compatibility with older WooReer versions.
	 *
	 * @since 2.1.6
	 *
	 * @return array
	 */
	function wcsdm_billing_fields() {
		return wcsdm_address_fields( 'billing' );
	}
endif;

if ( ! function_exists( 'wcsdm_instances' ) ) :
	/**
	 * Get WooReer shipping method instances across all shipping zones.
	 *
	 * This function retrieves all instances of the WooReer shipping method
	 * from all configured shipping zones in WooCommerce, including the default
	 * zone (zone 0). Optionally filters to only include enabled instances.
	 *
	 * Used for legacy compatibility with older WooReer versions.
	 *
	 * @since 2.0
	 *
	 * @param bool $enabled_only Whether to filter results to only include enabled instances. Default true.
	 * @return array Array of shipping method instances with zone_id, method_id, and instance_id.
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

		/**
		 * Filters the list of WooReer shipping method instances.
		 *
		 * @since 2.0
		 *
		 * @param array $instances Array of shipping method instances.
		 */
		return apply_filters( 'wcsdm_instances', $instances );
	}
endif;

if ( ! function_exists( 'wcsdm_array_insert_before' ) ) :
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
endif;

if ( ! function_exists( 'wcsdm_array_insert_after' ) ) :
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

		foreach ( WC_Checkout::instance()->get_checkout_fields( $address_type ) as $key => $field ) {
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
