<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/sofyansitorus
 * @since             1.0.0
 * @package           Wcsdm
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Shipping Distance Matrix
 * Plugin URI:        https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 * Description:       WooCommerce shipping rates calculator based on products shipping class and route distances that calculated using Google Maps Distance Matrix API.
 * Version:           1.2.8
 * Author:            Sofyan Sitorus
 * Author URI:        https://github.com/sofyansitorus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wcsdm
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.3.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if WooCommerce is active
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	return;
}// End if().

// Defines plugin named constants.
define( 'WCSDM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCSDM_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSDM_VERSION', '1.2.8' );
define( 'WCSDM_METHOD_ID', 'wcsdm' );
define( 'WCSDM_METHOD_TITLE', 'Shipping Distance Matrix' );

/**
 * Load plugin textdomain.
 *
 * @since 1.0.0
 */
function wcsdm_load_textdomain() {
	load_plugin_textdomain( 'wcsdm', false, basename( WCSDM_PATH ) . '/languages' );
}
add_action( 'plugins_loaded', 'wcsdm_load_textdomain' );

/**
 * Add plugin action links.
 *
 * Add a link to the settings page on the plugins.php page.
 *
 * @since 1.2.3
 *
 * @param  array $links List of existing plugin action links.
 * @return array         List of modified plugin action links.
 */
function wcsdm_plugin_action_links( $links ) {
	$zone_id = 0;
	$zones   = WC_Shipping_Zones::get_zones();
	foreach ( $zones as $zone ) {
		if ( empty( $zone['shipping_methods'] ) || empty( $zone['zone_id'] ) ) {
			continue;
		}
		foreach ( $zone['shipping_methods'] as $zone_shipping_method ) {
			if ( $zone_shipping_method instanceof Wcsdm ) {
				$zone_id = $zone['zone_id'];
				break;
			}
		}
	}

	$links = array_merge(
		array(
			'<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone_id ), 'wcsdm_settings', 'wcsdm_nonce' ) ) . '">' . __( 'Settings', 'wcsdm' ) . '</a>',
		),
		$links
	);

	return $links;
}
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wcsdm_plugin_action_links' );

/**
 * Load the main class
 *
 * @since    1.0.0
 */
function wcsdm_shipping_init() {
	include plugin_dir_path( __FILE__ ) . 'includes/class-wcsdm.php';
}
add_action( 'woocommerce_shipping_init', 'wcsdm_shipping_init' );

/**
 * Register shipping method
 *
 * @since    1.0.0
 * @param array $methods Existing shipping methods.
 */
function wcsdm_shipping_methods( $methods ) {
	$methods['wcsdm'] = 'Wcsdm';
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'wcsdm_shipping_methods' );

/**
 * Register the stylesheets and JavaScripts for the admin area.
 *
 * @since    1.0.0
 * @param    string $hook Current admin page hook.
 */
function wcsdm_admin_enqueue_scripts( $hook ) {
	if ( 'woocommerce_page_wc-settings' === $hook ) {
		// Enqueue admin styles.
		$wcsdm_admin_css = ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) ? add_query_arg( array( 't' => time() ), WCSDM_URL . 'assets/css/wcsdm-admin.css' ) : WCSDM_URL . 'assets/css/wcsdm-admin.min.css';
		wp_enqueue_style(
			'wcsdm-admin', // Give the script a unique ID.
			$wcsdm_admin_css, // Define the path to the JS file.
			array(), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			false // Specify whether to put in footer (leave this false).
		);

		// Enqueue admin scripts.
		$wcsdm_admin_js = ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) ? add_query_arg( array( 't' => time() ), WCSDM_URL . 'assets/js/wcsdm-admin.js' ) : WCSDM_URL . 'assets/js/wcsdm-admin.min.js';
		wp_enqueue_script(
			'wcsdm-admin', // Give the script a unique ID.
			$wcsdm_admin_js, // Define the path to the JS file.
			array( 'jquery' ), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			true // Specify whether to put in footer (leave this true).
		);
		wp_localize_script(
			'wcsdm-admin',
			'wcsdm_params',
			array(
				'show_settings' => ( isset( $_GET['wcsdm_nonce'] ) && wp_verify_nonce( $_GET['wcsdm_nonce'], 'wcsdm_settings' ) && is_admin() ),
				'method_id'     => WCSDM_METHOD_ID,
				'method_title'  => WCSDM_METHOD_TITLE,
				'txt'           => array(
					'drag_marker' => __( 'Drag this marker or search your address at the input above.', 'wcsdm' ),
				),
				'marker'        => WCSDM_URL . 'assets/img/marker.png',
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'wcsdm_admin_enqueue_scripts' );

// Show city field on the cart shipping calculator.
add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_true' );
