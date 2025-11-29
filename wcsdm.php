<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 * @since             1.0.0
 * @package           Wcsdm
 *
 * @wordpress-plugin
 * Plugin Name:       WooReer
 * Plugin URI:        https://wooreer.com
 * Description:       WooCommerce shipping rates calculator allows you to offer shipping rates based on distance and duration using Google Maps, Mapbox, or DistanceMatrix.ai.
 * Version:           3.0.0
 * Author:            Sofyan Sitorus
 * Author URI:        https://github.com/sofyansitorus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wcsdm
 * Domain Path:       /languages
 *
 * WC requires at least: 8.8.0
 * WC tested up to: 10.3.5
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Define plugin main constants.
define( 'WCSDM_FILE', __FILE__ );
define( 'WCSDM_PATH', plugin_dir_path( WCSDM_FILE ) );
define( 'WCSDM_URL', plugin_dir_url( WCSDM_FILE ) );

// Load the helpers.
require_once WCSDM_PATH . '/includes/constants.php';
require_once WCSDM_PATH . '/includes/helpers.php';

// Register the class autoload.
if ( function_exists( 'wcsdm_autoload' ) ) {
	spl_autoload_register( 'wcsdm_autoload' );
}

// Load the legacy helpers.
require_once WCSDM_PATH . '/legacy/constants.php';
require_once WCSDM_PATH . '/legacy/helpers.php';

// Register the legacy class autoload.
if ( function_exists( 'wcsdm_legacy_autoload' ) ) {
	spl_autoload_register( 'wcsdm_legacy_autoload' );
}

/**
 * Boot the plugin
 */
if ( wcsdm_is_plugin_active( 'woocommerce/woocommerce.php' ) && class_exists( 'Wcsdm' ) ) {
	// Initialize the Wcsdm class.
	Wcsdm::get_instance();
}
