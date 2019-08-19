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
 * Plugin Name:       WooReer (formerly WooCommerce Shipping Distance Matrix)
 * Plugin URI:        https://wooreer.com
 * Description:       WooCommerce shipping rates calculator that allows you to easily offer shipping rates based on the distance that calculated using Google Maps Distance Matrix Service API.
 * Version:           2.0.7
 * Author:            Sofyan Sitorus
 * Author URI:        https://github.com/sofyansitorus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wcsdm
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Defines plugin named constants.
if ( ! defined( 'WCSDM_FILE' ) ) {
	define( 'WCSDM_FILE', __FILE__ );
}
if ( ! defined( 'WCSDM_PATH' ) ) {
	define( 'WCSDM_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WCSDM_URL' ) ) {
	define( 'WCSDM_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WCSDM_DEFAULT_LAT' ) ) {
	define( 'WCSDM_DEFAULT_LAT', '-6.178784361374902' );
}
if ( ! defined( 'WCSDM_DEFAULT_LNG' ) ) {
	define( 'WCSDM_DEFAULT_LNG', '106.82303292695315' );
}
if ( ! defined( 'WCSDM_TEST_LAT' ) ) {
	define( 'WCSDM_TEST_LAT', '-6.181472315327319' );
}
if ( ! defined( 'WCSDM_TEST_LNG' ) ) {
	define( 'WCSDM_TEST_LNG', '106.8170462364319' );
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$wcsdm_plugin_data = get_plugin_data( WCSDM_FILE, false, false );

if ( ! defined( 'WCSDM_VERSION' ) ) {
	$wcsdm_version = isset( $wcsdm_plugin_data['Version'] ) ? $wcsdm_plugin_data['Version'] : '1.0.0';
	define( 'WCSDM_VERSION', $wcsdm_version );
}

if ( ! defined( 'WCSDM_METHOD_ID' ) ) {
	$wcsdm_method_id = isset( $wcsdm_plugin_data['TextDomain'] ) ? $wcsdm_plugin_data['TextDomain'] : 'wcsdm';
	define( 'WCSDM_METHOD_ID', $wcsdm_method_id );
}

if ( ! defined( 'WCSDM_METHOD_TITLE' ) ) {
	$wcsdm_method_title = isset( $wcsdm_plugin_data['Name'] ) ? $wcsdm_plugin_data['Name'] : 'WooCommerce Shipping Distance Matrix';
	define( 'WCSDM_METHOD_TITLE', $wcsdm_method_title );
}

/**
 * Include required core files.
 */
require_once WCSDM_PATH . '/includes/helpers.php';
require_once WCSDM_PATH . '/includes/class-wcsdm-api.php';

/**
 * Check if WooCommerce plugin is active
 */
if ( ! wcsdm_is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	return;
}

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
	$links = array_merge(
		array(
			'<a href="' . esc_url(
				add_query_arg(
					array(
						'page'           => 'wc-settings',
						'tab'            => 'shipping',
						'zone_id'        => 0,
						'wcsdm_settings' => true,
					),
					admin_url( 'admin.php' )
				)
			) . '">' . __( 'Settings', 'wcsdm' ) . '</a>',
		),
		$links
	);

	if ( ! wcsdm_is_pro() ) {
		$link_pro = array(
			'<a href="https://wooreer.com/?utm_source=wp-admin&utm_medium=action_links" target="_blank">' . __( 'Get Pro Version', 'wcsdm' ) . '</a>',
		);

		$links = array_merge(
			$links,
			$link_pro
		);
	}

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
 * Enqueue both scripts and styles in the backend area.
 *
 * @since    1.0.0
 * @param    string $hook Current admin page hook.
 */
function wcsdm_enqueue_scripts_backend( $hook ) {
	if ( false !== strpos( $hook, 'wc-settings' ) ) {
		$is_debug = defined( 'WCSDM_DEV' ) && WCSDM_DEV;

		// Define the styles URL.
		$css_url = WCSDM_URL . 'assets/css/wcsdm-backend.min.css';
		if ( $is_debug ) {
			$css_url = add_query_arg( array( 't' => time() ), str_replace( '.min', '', $css_url ) );
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'wcsdm-backend', // Give the script a unique ID.
			$css_url, // Define the path to the JS file.
			array(), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			false // Specify whether to put in footer (leave this false).
		);

		// Define the scripts URL.
		$js_url = WCSDM_URL . 'assets/js/wcsdm-backend.min.js';
		if ( $is_debug ) {
			$js_url = add_query_arg( array( 't' => time() ), str_replace( '.min', '', $js_url ) );
		}

		// Enqueue admin scripts.
		wp_enqueue_script(
			'wcsdm-backend', // Give the script a unique ID.
			$js_url, // Define the path to the JS file.
			array( 'jquery' ), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			true // Specify whether to put in footer (leave this true).
		);

		// Localize the script data.
		wp_localize_script(
			'wcsdm-backend',
			'wcsdm_backend',
			array(
				'showSettings'           => isset( $_GET['wcsdm_settings'] ) && is_admin(), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'methodId'               => WCSDM_METHOD_ID,
				'methodTitle'            => wcsdm_is_pro() ? WCSDM_PRO_METHOD_TITLE : WCSDM_METHOD_TITLE,
				'marker'                 => WCSDM_URL . 'assets/img/marker.png',
				'defaultLat'             => WCSDM_DEFAULT_LAT,
				'defaultLng'             => WCSDM_DEFAULT_LNG,
				'testLat'                => WCSDM_TEST_LAT,
				'testLng'                => WCSDM_TEST_LNG,
				'language'               => get_locale(),
				'isPro'                  => wcsdm_is_pro(),
				'isDebug'                => $is_debug,
				'i18n'                   => wcsdm_i18n(),
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'validate_api_key_nonce' => wp_create_nonce( 'wcsdm_validate_api_key_server' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'wcsdm_enqueue_scripts_backend' );

/**
 * Enqueue scripts in the frontend area.
 *
 * @since    2.0
 */
function wcsdm_enqueue_scripts_frontend() {
	// Bail early if there is no instances enabled.
	if ( ! wcsdm_instances() ) {
		return;
	}

	// Define scripts URL.
	$js_url = WCSDM_URL . 'assets/js/wcsdm-frontend.min.js';
	if ( defined( 'WCSDM_DEV' ) && WCSDM_DEV ) {
		$js_url = add_query_arg( array( 't' => time() ), str_replace( '.min', '', $js_url ) );
	}

	// Enqueue scripts.
	wp_enqueue_script(
		'wcsdm-frontend', // Give the script a unique ID.
		$js_url, // Define the path to the JS file.
		array( 'jquery', 'wp-util' ), // Define dependencies.
		WCSDM_VERSION, // Define a version (optional).
		true // Specify whether to put in footer (leave this true).
	);

	$fields = array(
		'postcode',
		'state',
		'city',
		'address_1',
		'address_2',
	);

	// Localize the script data.
	$wcsdm_frontend = array();
	foreach ( $fields as $field ) {
		$wcsdm_frontend[ 'shipping_calculator_' . $field ] = apply_filters( 'woocommerce_shipping_calculator_enable_' . $field, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}
	wp_localize_script( 'wcsdm-frontend', 'wcsdm_frontend', $wcsdm_frontend );
}
add_action( 'wp_enqueue_scripts', 'wcsdm_enqueue_scripts_frontend' );

/**
 * Print hidden element for the custom address 1 field and address 2 field value
 * in shipping calculator form.
 *
 * @since 2.0
 * @return void
 */
function wcsdm_after_shipping_calculator() {
	// Bail early if there is no instances enabled.
	if ( ! wcsdm_instances() ) {
		return;
	}

	// Address 1 hidden field.
	if ( apply_filters( 'woocommerce_shipping_calculator_enable_address_1', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$address_1 = WC()->cart->get_customer()->get_shipping_address();
		?>
		<input type="hidden" id="wcsdm-calc-shipping-field-value-address_1" value="<?php echo esc_attr( $address_1 ); ?>" />
		<?php
	}

	// Address 2 hidden field.
	if ( apply_filters( 'woocommerce_shipping_calculator_enable_address_2', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$address_2 = WC()->cart->get_customer()->get_shipping_address_2();
		?>
		<input type="hidden" id="wcsdm-calc-shipping-field-value-address_2" value="<?php echo esc_attr( $address_2 ); ?>" />
		<?php
	}
}
add_action( 'woocommerce_after_shipping_calculator', 'wcsdm_after_shipping_calculator' );

// Show fields in the shipping calculator form.
add_filter( 'woocommerce_shipping_calculator_enable_postcode', '__return_true' );
add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_true' );
add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_true' );
add_filter( 'woocommerce_shipping_calculator_enable_address_1', '__return_true' );
add_filter( 'woocommerce_shipping_calculator_enable_address_2', '__return_true' );

/**
 * AJAX handler for Server Side API Key validation.
 *
 * @since 2.0.8
 *
 * @return void
 */
function wcsdm_validate_api_key_server() {
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wcsdm_validate_api_key_server' ) ) {
		wp_send_json_error( 'Sorry, your nonce did not verify.', 400 );
	}

	if ( ! $key ) {
		$key = 'InvalidKey';
	}

	$api = new Wcsdm_API();

	$distance = $api->calculate_distance(
		array(
			'key' => $key,
		),
		true
	);

	if ( is_wp_error( $distance ) ) {
		wp_send_json_error( $distance->get_error_message(), 400 );
	}

	wp_send_json_success( $distance );
}
add_action( 'wp_ajax_wcsdm_validate_api_key_server', 'wcsdm_validate_api_key_server' );
