<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.0.0
 *
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
class Wcsdm {

	/**
	 * Hold an instance of the class
	 *
	 * @var Wcsdm
	 */
	private static $instance = null;

	/**
	 * The object is created from within the class itself
	 * only if the class has no instance.
	 *
	 * @return Wcsdm
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Wcsdm();
		}

		return self::$instance;
	}

	/**
	 * Class Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Hook to load plugin textdomain.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Hook to add plugin action links.
		add_action( 'plugin_action_links_' . plugin_basename( WCSDM_FILE ), array( $this, 'plugin_action_links' ) );

		// Hook to register the shipping method.
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

		// Hook to AJAX actions.
		add_action( 'wp_ajax_wcsdm_validate_api_key_server', array( $this, 'validate_api_key_server' ) );

		// Hook to modify after shipping calculator form.
		add_action( 'woocommerce_after_shipping_calculator', array( $this, 'after_shipping_calculator' ) );

		// Hook to woocommerce_cart_shipping_packages to inject filed address_2.
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'inject_cart_shipping_packages' ) );

		// Hook to enqueue scripts & styles assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backend_assets' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 999 );

		// Hook to declare compatibility with the High-Performance Order Storage.
		add_action(
			'before_woocommerce_init',
			function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCSDM_FILE, true );
				}
			}
		);

		// Hook to declare incompatibility with the Cart and Checkout Blocks.
		add_action(
			'before_woocommerce_init',
			function() {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WCSDM_FILE, false );
				}
			}
		);
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
	 * Add plugin action links.
	 *
	 * Add a link to the settings page on the plugins.php page.
	 *
	 * @since 1.1.3
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array         List of modified plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$zone_id = 0;

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $links;
		}

		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( empty( $zone['shipping_methods'] ) || empty( $zone['zone_id'] ) ) {
				continue;
			}

			foreach ( $zone['shipping_methods'] as $zone_shipping_method ) {
				if ( $zone_shipping_method instanceof Wcsdm_Shipping_Method ) {
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
						),
						admin_url( 'admin.php' )
					)
				) . '">' . __( 'Settings', 'wcsdm' ) . '</a>',
			),
			$links
		);

		return $links;
	}

	/**
	 * Enqueue backend scripts.
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
		$css_url = WCSDM_URL . 'assets/css/wcsdm-backend-legacy.min.css';
		if ( $is_dev_env ) {
			$css_url = add_query_arg( array( 't' => time() ), str_replace( '.min', '', $css_url ) );
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'wcsdm-backend-legacy', // Give the script a unique ID.
			$css_url, // Define the path to the JS file.
			array(), // Define dependencies.
			WCSDM_VERSION, // Define a version (optional).
			false // Specify whether to put in footer (leave this false).
		);

		if ( version_compare( WC()->version, '8.4.0', '>=' ) ) {
			// Define the styles URL.
			$css_url = WCSDM_URL . 'assets/css/wcsdm-backend.min.css';
			if ( $is_dev_env ) {
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
		}

		// Define the scripts URL.
		$js_url = WCSDM_URL . 'assets/js/wcsdm-backend.min.js';
		if ( $is_dev_env ) {
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
			'wcsdmBackendVars',
			array(
				'showSettings'           => isset( $_GET['wcsdm_settings'] ) && is_admin(), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'methodId'               => WCSDM_METHOD_ID,
				'methodTitle'            => WCSDM_METHOD_TITLE,
				'marker'                 => WCSDM_URL . 'assets/img/marker.png',
				'defaultLat'             => WCSDM_DEFAULT_LAT,
				'defaultLng'             => WCSDM_DEFAULT_LNG,
				'testLat'                => WCSDM_TEST_LAT,
				'testLng'                => WCSDM_TEST_LNG,
				'language'               => get_locale(),
				'isDevEnv'               => $is_dev_env,
				'i18n'                   => wcsdm_i18n(),
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'validate_api_key_nonce' => wp_create_nonce( 'wcsdm_validate_api_key_server' ),
			)
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		// Bail early if there is no instances enabled.
		if ( ! wcsdm_instances() ) {
			return;
		}

		$is_cart_page     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout_page = function_exists( 'is_checkout' ) && is_checkout();

		$enqueue_frontend_assets = apply_filters( 'wcsdm_enqueue_frontend_assets', ( $is_cart_page | $is_checkout_page ) );

		if ( ! $enqueue_frontend_assets ) {
			return;
		}

		$is_dev_env = wcsdm_is_dev_env();

		// Define scripts URL.
		$js_url = WCSDM_URL . 'assets/js/wcsdm-frontend.min.js';

		if ( $is_dev_env ) {
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

		$wcsdm_frontend_vars = apply_filters(
			'wcsdm_frontend_vars',
			array(
				'forms' => array(
					'calc_shipping' => array(
						'wrapper' => '.shipping-calculator-form',
						'fields'  => wcsdm_calc_shipping_fields(),
					),
					'billing'       => array(
						'wrapper' => '.woocommerce-billing-fields__field-wrapper',
						'fields'  => wcsdm_billing_fields(),
					),
					'shipping'      => array(
						'wrapper' => '.woocommerce-shipping-fields__field-wrapper',
						'fields'  => wcsdm_shipping_fields(),
					),
				),
			)
		);

		wp_localize_script( 'wcsdm-frontend', 'wcsdmFrontendVars', $wcsdm_frontend_vars );
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
	 * Print hidden element for the custom address 1 field and address 2 field value
	 * in shipping calculator form.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function after_shipping_calculator() {
		// Bail early if there is no instances enabled.
		if ( ! wcsdm_instances() ) {
			return;
		}

		$calc_shipping_fields = wcsdm_calc_shipping_fields();

		if ( ! $calc_shipping_fields ) {
			return;
		}

		$custom_fields = array(
			'address_1',
			'address_2',
		);

		foreach ( $calc_shipping_fields as $key => $field ) {
			if ( ! in_array( $key, $custom_fields, true ) ) {
				continue;
			}

			$value = call_user_func( array( WC()->cart->get_customer(), 'get_shipping_' . $key ) );
			?>
			<input type="hidden" id="wcsdm-calc-shipping-value-field--<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<script type="text/template" id="tmpl-wcsdm-calc-shipping-custom-field--<?php echo esc_attr( $key ); ?>">
				<p class="form-row form-row-wide" id="calc_shipping_{{ data.fieldKey }}_field">
					<input type="text" class="input-text" value="{{ data.value }}" placeholder="{{ data.placeholder }}" name="calc_shipping_{{ data.fieldKey }}" id="calc_shipping_{{ data.fieldKey }}" />
				</p>
			</script>
			<?php
		}
	}

	/**
	 * AJAX handler for Server Side API Key validation.
	 *
	 * @since 2.0.8
	 *
	 * @return void
	 */
	public function validate_api_key_server() {
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wcsdm_validate_api_key_server' ) ) {
			wp_send_json_error( 'Sorry, your nonce did not verify.', 400 );
		}

		if ( ! $key ) {
			$key = 'InvalidKey';
		}

		$distance = Wcsdm_API::calculate_distance(
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

	/**
	 * Inject cart cart packages to calculate shipping for address fields.
	 *
	 * @since 1.0.0
	 * @param array $packages Current cart contents packages.
	 * @return array
	 */
	public function inject_cart_shipping_packages( $packages ) {
		if ( ! wcsdm_is_calc_shipping() ) {
			return $packages;
		}

		$address_fields = array(
			'address_1' => false,
			'address_2' => false,
		);

		foreach ( array_keys( $address_fields ) as $field_key ) {
			$address_fields[ $field_key ] = wcsdm_calc_shipping_field_value( 'calc_shipping_' . $field_key );
		}

		foreach ( array_keys( $packages ) as $package_key ) {
			foreach ( $address_fields as $field_key => $field_value ) {
				if ( false === $field_value ) {
					continue;
				}

				// Set customer billing address.
				call_user_func( array( WC()->customer, 'set_billing_' . $field_key ), $field_value );

				// Set customer shipping address.
				call_user_func( array( WC()->customer, 'set_shipping_' . $field_key ), $field_value );

				// Set package destination address.
				$packages[ $package_key ]['destination'][ $field_key ] = $field_value;
			}
		}

		return $packages;
	}
}
