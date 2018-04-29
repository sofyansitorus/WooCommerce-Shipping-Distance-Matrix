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
 * Version:           1.4
 * Author:            Sofyan Sitorus
 * Author URI:        https://github.com/sofyansitorus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wcsdm
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.3.5
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if plugin is active
 *
 * @param string $plugin_file Plugin file name.
 */
function wcsdm_is_plugin_active( $plugin_file ) {

	$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}

	return in_array( $plugin_file, $active_plugins, true ) || array_key_exists( $plugin_file, $active_plugins );
}

/**
 * Check if WooCommerce plugin is active
 */
if ( ! wcsdm_is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	return;
}

// Defines plugin named constants.
define( 'WCSDM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCSDM_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSDM_VERSION', '1.4' );
define( 'WCSDM_METHOD_ID', 'wcsdm' );
define( 'WCSDM_METHOD_TITLE', 'Shipping Distance Matrix' );
define( 'WCSDM_MAP_SECRET_KEY', 'QUl6YVN5Qk82MVFJUm52Zkc5c2tKTW1HV1JVbWhsSU5lcUZXaTdV' );

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
	foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
		if ( empty( $zone['shipping_methods'] ) || empty( $zone['zone_id'] ) ) {
			continue;
		}
		foreach ( $zone['shipping_methods'] as $zone_shipping_method ) {
			if ( $zone_shipping_method instanceof Wcsdm ) {
				$zone_id = $zone['zone_id'];
				break;
			}
		}
		if ( $zone_id ) {
			break;
		}
	}

	$links = array_merge(
		array(
			'<a href="' . esc_url(
				add_query_arg(
					array(
						'page'           => 'wc-settings',
						'tab'            => 'shipping',
						'zone_id'        => $zone_id,
						'wcsdm_settings' => true,
					), admin_url( 'admin.php' )
				)
			) . '">' . __( 'Settings', 'wcsdm' ) . '</a>',
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
		$wcsdm_admin_css = ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) ? add_query_arg( array( 't' => time() ), WCSDM_URL . 'assets/css/wcsdm.css' ) : WCSDM_URL . 'assets/css/wcsdm.min.css';
		wp_enqueue_style(
			'wcsdm', // Give the script a unique ID.
			$wcsdm_admin_css, // Define the path to the JS file.
			array(), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			false // Specify whether to put in footer (leave this false).
		);

		// Enqueue admin scripts.
		$wcsdm_admin_js = ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) ? add_query_arg( array( 't' => time() ), WCSDM_URL . 'assets/js/wcsdm.js' ) : WCSDM_URL . 'assets/js/wcsdm.min.js';
		wp_enqueue_script(
			'wcsdm', // Give the script a unique ID.
			$wcsdm_admin_js, // Define the path to the JS file.
			array( 'jquery' ), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			true // Specify whether to put in footer (leave this true).
		);
		wp_localize_script(
			'wcsdm',
			'wcsdm_params',
			array(
				'show_settings' => isset( $_GET['wcsdm_settings'] ) && is_admin(),
				'method_id'     => WCSDM_METHOD_ID,
				'method_title'  => WCSDM_METHOD_TITLE,
				'txt'           => array(
					'drag_marker' => __( 'Drag this marker or search your address at the input above.', 'wcsdm' ),
					'per_unit_km' => __( 'Per Kilometer', 'wcsdm' ),
					'per_unit_mi' => __( 'Per Mile', 'wcsdm' ),
				),
				'marker'        => WCSDM_URL . 'assets/img/marker.png',
				'language'      => get_locale(),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'wcsdm_admin_enqueue_scripts' );
