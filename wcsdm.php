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
 * Description:       WooCommerce shipping rates calculator based on products shipping class and distances that calculated using Google Maps Distance Matrix API.
 * Version:           1.0.0
 * Author:            Sofyan Sitorus
 * Author URI:        https://github.com/sofyansitorus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wcsdm
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Load plugin textdomain.
 *
 * @since 1.0.0
 */
function wcsdm_load_textdomain() {
	load_plugin_textdomain( 'wcsdm', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wcsdm_load_textdomain' );


/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
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
			wp_enqueue_style( 'wcsdm-admin', plugin_dir_url( __FILE__ ) . 'assets/css/wcsdm-admin.css' );
			wp_enqueue_script( 'wcsdm-admin', plugin_dir_url( __FILE__ ) . 'assets/js/wcsdm-admin.js', array( 'jquery' ) );
		}
	}
	add_action( 'admin_enqueue_scripts', 'wcsdm_admin_enqueue_scripts' );
}// End if().
