<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area. This class acts as the
 * central coordinator for WooReer plugin functionality, managing initialization,
 * asset loading, and integration with WooCommerce shipping system.
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      1.0.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class for WooReer.
 *
 * This is the main orchestrator class for WooReer, responsible for:
 * - Defining internationalization and loading plugin text domain
 * - Registering admin-specific hooks and action links
 * - Integrating with WooCommerce shipping methods system
 * - Managing frontend and backend asset enqueueing
 * - Declaring compatibility with WooCommerce features (HPOS, Custom Order Tables)
 * - Filtering REST API requests for shipping calculator functionality
 *
 * This class implements the Singleton pattern to ensure only one instance
 * exists throughout the plugin lifecycle.
 *
 * @since      1.0.0
 */
class Wcsdm {

	/**
	 * Hold an instance of the class
	 *
	 * Singleton instance of the Wcsdm class to ensure only one instance
	 * is created and used throughout the plugin lifecycle.
	 *
	 * @since 1.0.0
	 * @var Wcsdm|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * The object is created from within the class itself only if the class
	 * has no instance. This ensures that only one instance of the class
	 * exists throughout the plugin lifecycle (Singleton pattern).
	 *
	 * @since 1.0.0
	 * @return Wcsdm The singleton instance of the class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Wcsdm();
		}

		return self::$instance;
	}

	/**
	 * Class Constructor.
	 *
	 * Private constructor to prevent direct instantiation. This enforces the
	 * Singleton pattern - use get_instance() to obtain the class instance.
	 *
	 * Initializes the plugin by registering all necessary WordPress and WooCommerce hooks:
	 *
	 * - Loads plugin translations for internationalization
	 * - Adds settings link to the plugins list page
	 * - Registers the WooReer shipping method with WooCommerce
	 * - Enqueues admin assets (CSS/JS) for the settings interface
	 * - Declares compatibility with WooCommerce High-Performance Order Storage (HPOS)
	 * - Conditionally disables address validation for shipping calculator contexts to allow
	 *   shipping cost estimation with minimal address information (city, state, postcode only)
	 * - Sets data version when new shipping method instances are created for migration tracking
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		/**
		 * Load plugin textdomain for internationalization.
		 *
		 * Fires on plugins_loaded to ensure WordPress translation functions are available.
		 * Loads translation files from the /languages directory.
		 */
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		/**
		 * Add "Settings" link to plugin action links on the plugins page.
		 *
		 * Creates a direct link to the WooCommerce shipping settings page for WooReer
		 * configuration. The link appears alongside Activate/Deactivate/Delete actions.
		 */
		add_action( 'plugin_action_links_' . plugin_basename( WCSDM_FILE ), array( $this, 'plugin_action_links' ) );

		/**
		 * Register WooReer as a WooCommerce shipping method.
		 *
		 * Adds Wcsdm_Shipping_Method to the list of available shipping methods, making it
		 * selectable when configuring shipping zones in WooCommerce settings.
		 */
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

		/**
		 * Enqueue backend CSS and JavaScript assets for the admin interface.
		 *
		 * Loads styles and scripts on WooCommerce settings pages. Priority 999 ensures
		 * styles load after WooCommerce core styles for proper cascading.
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backend_assets' ), 999 );

		/**
		 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
		 *
		 * Registers plugin support for WooCommerce custom order tables feature, enabling
		 * improved performance for stores with large numbers of orders.
		 */
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility_custom_order_tables' ) );

		/**
		 * Skip address line validation for REST API shipping calculator requests.
		 *
		 * Detects block-based cart/checkout shipping calculator usage via Store API batch
		 * requests and disables address_1/address_2 validation to allow shipping estimation
		 * with partial address data (typically city, state, postcode only).
		 */
		add_filter( 'rest_pre_dispatch', array( $this, 'maybe_skip_address_fields_validation' ), 10, 3 );

		/**
		 * Skip address line validation for classic (non-block) shipping calculator.
		 *
		 * Detects traditional cart page shipping calculator form submissions and disables
		 * address_1/address_2 field validation to enable shipping cost estimation without
		 * requiring full street address details.
		 */
		add_filter( 'wp', array( $this, 'maybe_skip_address_fields_validation_classic' ), 10 );

		/**
		 * Skip address line validation when viewing the cart page.
		 *
		 * Automatically disables address_1/address_2 validation on cart pages to allow
		 * shipping rate calculation with minimal address information, improving user
		 * experience during the shopping process.
		 */
		add_filter( 'wp', array( $this, 'maybe_skip_address_fields_validation_on_cart_page' ), 10 );

		/**
		 * Initialize data version when a new shipping method instance is added to a zone.
		 *
		 * Sets the data_version option for new WooReer instances to track configuration
		 * schema version. This facilitates data migrations when the plugin is updated to
		 * newer versions with different settings structures.
		 */
		add_action( 'woocommerce_shipping_zone_method_added', array( $this, 'set_instance_data_version' ), 10, 2 );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wcsdm', false, basename( WCSDM_PATH ) . '/languages' );
	}

	/**
	 * Add plugin action links on the plugins page.
	 *
	 * Adds a "Settings" link to the WooReer plugin row on the WordPress plugins page
	 * (wp-admin/plugins.php). The link directs to the WooCommerce shipping settings page,
	 * specifically to the WooReer shipping method instance configuration.
	 *
	 * The method searches for an active WooReer shipping method instance across all
	 * shipping zones, prioritizing the default zone (zone 0), and constructs the
	 * appropriate settings URL.
	 *
	 * @since 1.1.3
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array        List of modified plugin action links with Settings link prepended.
	 */
	public function plugin_action_links( $links ) {
		$instance_id = 0;

		// Ensure WooCommerce shipping zones class is available.
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $links;
		}

		// Try to find WooReer shipping method instance from the default zone first (zone 0).
		$zone             = WC_Shipping_Zones::get_zone( 0 );
		$shipping_methods = $zone->get_shipping_methods( true );

		foreach ( $shipping_methods as $zone_shipping_method ) {
			if ( $zone_shipping_method instanceof Wcsdm_Shipping_Method ) {
				$instance_id = $zone_shipping_method->get_instance_id();
				break;
			}
		}

		// If not found in default zone, search through all other configured shipping zones.
		if ( ! $instance_id ) {
			foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
				if ( empty( $zone['shipping_methods'] ) || empty( $zone['zone_id'] ) ) {
					continue;
				}

				foreach ( $zone['shipping_methods'] as $zone_shipping_method ) {
					if ( $zone_shipping_method instanceof Wcsdm_Shipping_Method ) {
						$instance_id = $zone_shipping_method->get_instance_id();
						break;
					}
				}

				if ( $instance_id ) {
					break;
				}
			}
		}

		// Build the query arguments for the settings URL.
		$query_args = array(
			'page' => 'wc-settings',
			'tab'  => 'shipping',
		);

		if ( $instance_id ) {
			// Direct link to the specific WooReer instance settings.
			$query_args['instance_id'] = $instance_id;
		} else {
			// Fallback to zone ID 0 (Locations not covered by your other zones).
			$query_args['zone_id'] = 0;
		}

		// Prepend the Settings link to the existing plugin action links.
		$links = array_merge(
			array(
				'<a href="' . esc_url(
					add_query_arg(
						$query_args,
						admin_url( 'admin.php' )
					)
				) . '">' . __( 'Settings', 'wcsdm' ) . '</a>',
			),
			$links
		);

		return $links;
	}

	/**
	 * Enqueue backend scripts and styles for WooReer admin interface.
	 *
	 * Loads the CSS and JavaScript assets required for WooReer's admin settings
	 * interface in the WooCommerce shipping settings page. Assets are only loaded
	 * on the WooCommerce settings pages to avoid unnecessary resource loading.
	 *
	 * In development environment (determined by wcsdm_is_dev_env()), non-minified
	 * versions are loaded with cache-busting timestamp parameters for easier debugging.
	 *
	 * The JavaScript file receives localized data including internationalization strings
	 * for use in the admin interface.
	 *
	 * @since 1.0.0
	 * @param string $hook Passed screen ID in admin area.
	 */
	public function enqueue_backend_assets( $hook = null ) {
		if ( false === strpos( $hook, 'wc-settings' ) ) {
			return;
		}

		$is_dev_env = wcsdm_is_dev_env();

		// Define the styles URL.
		$css_url = WCSDM_URL . 'assets/css/wcsdm-backend.css';
		if ( $is_dev_env ) {
			$css_url = add_query_arg( array( 't' => time() ), $css_url );
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'wcsdm-backend', // Give the script a unique ID.
			$css_url, // Define the path to the CSS file.
			array(), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			false // Specify whether to put in footer (leave this false).
		);

		// Define the scripts URL.
		$js_url = WCSDM_URL . 'assets/js/wcsdm-backend.js';
		if ( $is_dev_env ) {
			$js_url = add_query_arg( array( 't' => time() ), $js_url );
		}

		// Enqueue admin scripts.
		wp_enqueue_script(
			'wcsdm-backend', // Give the script a unique ID.
			$js_url, // Define the path to the JS file.
			array( 'jquery' ), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			true // Specify whether to put in footer (leave this true).
		);
	}

	/**
	 * Register shipping method to WooCommerce.
	 *
	 * @since 1.0.0
	 * @param array $methods registered shipping methods.
	 */
	public function register_shipping_method( $methods ) {
		if ( class_exists( 'Wcsdm_Shipping_Method' ) ) {
			$methods[ WCSDM_METHOD_ID ] = 'Wcsdm_Shipping_Method';
		}

		return $methods;
	}

	/**
	 * Set the data version for a shipping method instance.
	 *
	 * This method is triggered when a new shipping method instance is added to a WooCommerce
	 * shipping zone. It initializes the method's settings by setting the data_version option,
	 * which is used to track the schema version of the shipping method's configuration data.
	 * This is particularly useful for managing data migrations when the plugin is updated.
	 *
	 * @since 3.0
	 *
	 * @param int    $instance_id The unique instance ID of the shipping method being added.
	 * @param string $type        The shipping method type/ID being added.
	 * @return void
	 */
	public function set_instance_data_version( int $instance_id, string $type ) {
		if ( WCSDM_METHOD_ID !== $type ) {
			return;
		}

		$method = new Wcsdm_Shipping_Method( $instance_id );

		update_option(
			$method->get_instance_option_key(),
			array(
				'data_version' => WCSDM_DATA_VERSION,
			),
			'yes'
		);
	}



	/**
	 * Declares compatibility with WooCommerce Custom Order Tables (HPOS).
	 *
	 * This method integrates WooReer with WooCommerce's High-Performance Order Storage (HPOS)
	 * feature by declaring this plugin's compatibility with custom order tables.
	 * HPOS is WooCommerce's modern approach to storing order data in dedicated custom tables
	 * instead of using WordPress post types, which significantly improves performance for
	 * stores with large numbers of orders.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function declare_compatibility_custom_order_tables() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// Declare compatibility with WooCommerce Custom Order Tables feature.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCSDM_FILE, true );
		}
	}

	/**
	 * Skip address fields validation for REST API shipping calculator requests.
	 *
	 * This method filters REST API requests to detect when the shipping calculator
	 * is being used in the WooCommerce Store API (block-based cart/checkout).
	 * It analyzes batch requests to /wc/store/v1/batch endpoint and identifies
	 * cart customer update requests that only include shipping_address without
	 * billing_address, which indicates a shipping calculator form submission.
	 *
	 * When the shipping calculator is detected, validation for address_1 and address_2
	 * fields is disabled to allow shipping cost calculation with minimal address
	 * information (typically just city, state, and postcode).
	 *
	 * @since 3.0
	 *
	 * @param mixed           $result  Response to replace the requested version with. May be null, WP_REST_Response, or WP_Error.
	 * @param WP_REST_Server  $server  Server instance handling the request.
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return mixed The unmodified $result (this filter is used for side effects only).
	 */
	public function maybe_skip_address_fields_validation( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		// Check if this is a batch request to the WooCommerce Store API.
		if ( '/wc/store/v1/batch' === untrailingslashit( $request->get_route() ) ) {
			$batch_requests = $request->get_param( 'requests' );

			if ( $batch_requests && is_array( $batch_requests ) ) {
				$calc_shipping_request = wcsdm_array_find(
					$batch_requests,
					function ( $request_item ) {
						return wcsdm_str_ends_with( $request_item['path'], '/wc/store/v1/cart/update-customer' ) || wcsdm_str_ends_with( $request_item['path'], '/wc/store/v1/cart/select-shipping-rate' );
					}
				);

				if ( $calc_shipping_request ) {
					$referer  = $request->get_header( 'referer' );
					$cart_url = wc_get_cart_url();

					if ( $referer && strpos( $referer, $cart_url ) !== false ) {
						add_filter( 'wcsdm_validate_address_field_address_1', '__return_false' );
						add_filter( 'wcsdm_validate_address_field_address_2', '__return_false' );
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Skip address fields validation for classic shipping calculator.
	 *
	 * This method hooks into the 'wp' action to detect when the classic (non-block)
	 * shipping calculator is being used in the cart page. When detected, it disables
	 * validation for address_1 and address_2 fields to allow shipping cost calculation
	 * with minimal address information (typically just city, state, and postcode).
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function maybe_skip_address_fields_validation_classic() {
		if ( wcsdm_is_calc_shipping() ) {
			add_filter( 'wcsdm_validate_address_field_address_1', '__return_false' );
			add_filter( 'wcsdm_validate_address_field_address_2', '__return_false' );
		}
	}

	/**
	 * Skip address fields validation on the cart page.
	 *
	 * This method hooks into the 'wp' action to detect when the user is viewing
	 * the cart page. When on the cart page, it disables validation for address_1
	 * and address_2 fields to allow shipping cost calculation with minimal address
	 * information. This is useful for cart page scenarios where full address details
	 * may not be required yet.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function maybe_skip_address_fields_validation_on_cart_page() {
		if ( is_cart() ) {
			add_filter( 'wcsdm_validate_address_field_address_1', '__return_false' );
			add_filter( 'wcsdm_validate_address_field_address_2', '__return_false' );
		}
	}
}
