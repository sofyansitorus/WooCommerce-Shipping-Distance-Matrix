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
class Wcsdm extends WC_Shipping_Method {

	/**
	 * URL of Google Maps Distance Matrix API
	 *
	 * @since    1.0.0
	 * @var string
	 */
	private $_google_api_url = 'https://maps.googleapis.com/maps/api/distancematrix/json';

	/**
	 * All options data
	 *
	 * @since    1.4.2
	 * @var array
	 */
	private $_options = array();

	/**
	 * All debugs data
	 *
	 * @since    1.4.2
	 * @var array
	 */
	private $_debugs = array();

	/**
	 * Rate fields data
	 *
	 * @since    2.0
	 * @var array
	 */
	private $_instance_rate_fields = array();

	/**
	 * Default data
	 *
	 * @since    2.0
	 * @var array
	 */
	private $_field_default = array(
		'title'             => '',
		'disabled'          => false,
		'class'             => '',
		'css'               => '',
		'placeholder'       => '',
		'type'              => 'text',
		'desc_tip'          => false,
		'description'       => '',
		'default'           => '',
		'custom_attributes' => array(),
		'is_required'       => false,
		'is_pro'            => false,
	);

	/**
	 * Constructor for your shipping class
	 *
	 * @since    1.0.0
	 * @param int $instance_id ID of shipping method instance.
	 */
	public function __construct( $instance_id = 0 ) {
		// ID for your shipping method. Should be unique.
		$this->id = WCSDM_METHOD_ID;

		// Title shown in admin.
		$this->method_title = $this->is_pro() ? WCSDM_PRO_METHOD_TITLE : WCSDM_METHOD_TITLE;

		// Title shown in admin.
		$this->title = $this->method_title;

		// Description shown in admin.
		$this->method_description = __( 'Shipping rates calculator that allows you to easily offer shipping rates based on the distance.', 'wcsdm' );

		$this->enabled = $this->get_option( 'enabled' );

		$this->instance_id = absint( $instance_id );

		$this->supports = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init_hooks();
		$this->init();
	}

	/**
	 * Register actions/filters hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		// Check if this shipping method is availbale for current order.
		add_filter( 'woocommerce_shipping_' . $this->id . '_is_available', array( $this, 'check_is_available' ), 10, 2 );

		// Sanitize settings fields.
		add_filter( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', array( $this, 'instance_settings_values' ), 10 );

		// Hook to woocommerce_cart_shipping_packages to inject filed address_2.
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'inject_cart_shipping_packages' ), 10 );
	}

	/**
	 * Init settings
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function init() {
		// Load the settings API.
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		$this->init_rate_fields(); // Init rate fields.

		// Define user set variables.
		foreach ( $this->instance_form_fields as $key => $field ) {
			$default = isset( $field['default'] ) ? $field['default'] : null;

			$this->_options[ $key ] = $this->get_option( $key, $default );

			$this->{$key} = $this->_options[ $key ];
		}
	}

	/**
	 * Init form fields.
	 *
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$instance_form_fields = array(
			'title'                 => array(
				'title'       => __( 'Label', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'text',
				'description' => __( 'This controls the label which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'tax_status'            => array(
				'title'       => __( 'Tax Status', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Tax status of fee.', 'woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'taxable',
				'options'     => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'api_key_browser'       => array(
				'title'       => __( 'Browser API Key', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'api_key',
				'description' => __( 'Google maps platform API Key for usage in browser side request. This API Key will be used by Store Location Latitude/Longitude setting fields and customer checkout Address Picker that available in Pro Version. This API Key MUST be has HTTP referrers restriction setting activated.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
			),
			'api_key_server'        => array(
				'title'       => __( 'Server API Key', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'api_key',
				'description' => __( 'Google maps platform API Key for usage in server side request. This API Key will be used to calculate the distance of the customer during checkout. This API Key MUST be has IP addresses restriction setting activated.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'api_request' => 'key',
			),
			'origin_lat'            => array(
				'title'       => __( 'Store Location Latitude', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'store_location',
				'description' => __( 'Store location latitude coordinates', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'disabled'    => true,
			),
			'origin_lng'            => array(
				'title'       => __( 'Store Location Longitude', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'store_location',
				'description' => __( 'Store location longitude coordinates', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'disabled'    => true,
			),
			'origin_address'        => array(
				'title'       => __( 'Store Location Address', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'store_location',
				'description' => __( 'Store location full address', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
				'disabled'    => true,
			),
			'store_location_picker' => array(
				'title'       => __( 'Store Location Picker', 'wcsdm' ),
				'type'        => 'store_location_picker',
				'description' => __( 'Drag the store icon marker or search your address in the input box below.', 'wcsdm' ),
			),
			'origin_type'           => array(
				'title'       => __( 'Origin Type', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Set whih data will be used as the origin info when calculating the distance.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'address',
				'options'     => array(
					'address'    => __( 'Address', 'wcsdm' ),
					'coordinate' => __( 'Coordinate', 'wcsdm' ),
				),
			),
			'travel_mode'           => array(
				'title'       => __( 'Travel Mode', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Google Maps Distance Matrix API travel mode parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'driving',
				'options'     => array(
					'driving'   => __( 'Driving', 'wcsdm' ),
					'walking'   => __( 'Walking', 'wcsdm' ),
					'bicycling' => __( 'Bicycling', 'wcsdm' ),
				),
				'api_request' => 'mode',
			),
			'route_restrictions'    => array(
				'title'       => __( 'Route Restrictions', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Google Maps Distance Matrix API route restrictions parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'options'     => array(
					''         => __( 'None', 'wcsdm' ),
					'tolls'    => __( 'Avoid Tolls', 'wcsdm' ),
					'highways' => __( 'Avoid Highways', 'wcsdm' ),
					'ferries'  => __( 'Avoid Ferries', 'wcsdm' ),
					'indoor'   => __( 'Avoid Indoor', 'wcsdm' ),
				),
				'api_request' => 'avoid',
			),
			'distance_unit'         => array(
				'title'       => __( 'Distance Units', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Google Maps Distance Matrix API distance units parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'metric',
				'options'     => array(
					'metric'   => __( 'Kilometer', 'wcsdm' ),
					'imperial' => __( 'Mile', 'wcsdm' ),
				),
				'api_request' => 'units',
			),
			'preferred_route'       => array(
				'title'       => __( 'Preferred Route', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Prefered route that will be used for calculation if API provide several routes', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'shortest_distance',
				'options'     => array(
					'shortest_distance' => __( 'Shortest Distance', 'wcsdm' ),
					'longest_distance'  => __( 'Longest Distance', 'wcsdm' ),
					'shortest_duration' => __( 'Shortest Duration', 'wcsdm' ),
					'longest_duration'  => __( 'Longest Duration', 'wcsdm' ),
				),
			),
			'round_up_distance'     => array(
				'title'       => __( 'Round Up Distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Round the distance up to the nearest absolute number.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'show_distance'         => array(
				'title'       => __( 'Show Distance Info', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Show the distance info to customer during checkout.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'enable_address_picker' => array(
				'title'       => __( 'Enable Address Picker', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Enable the map address picker to user during checkout so can get more accurate distance and the address form will be autocomplete upon an address selected.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'is_pro'      => true,
			),
			'table_rates'           => array(
				'type'  => 'table_rates',
				'title' => __( 'Table Rates Settings', 'wcsdm' ),
			),
			'table_advanced'        => array(
				'type'  => 'table_advanced',
				'title' => __( 'Advanced Table Rate Settings', 'wcsdm' ),
			),
			'js_template'           => array(
				'type' => 'js_template',
			),
		);

		$this->instance_form_fields = apply_filters( $this->id . '_form_fields', $instance_form_fields, $this->get_instance_id() );
	}

	/**
	 * Init rate fields.
	 *
	 * @since    2.0
	 */
	public function init_rate_fields() {
		$instance_rate_fields = array(
			'section_shipping_rules' => array(
				'type'        => 'section',
				'title'       => __( 'Shipping Rules', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'max_distance'           => array(
				'type'              => 'text',
				'title'             => __( 'Maximum Distances', 'wcsdm' ),
				'description'       => __( 'The maximum distances rule for the shipping rate. This input is required.', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '1',
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '1',
				),
			),
			'min_order_quantity'     => array(
				'type'              => 'text',
				'title'             => __( 'Minimum Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order quantity. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'max_order_quantity'     => array(
				'type'              => 'text',
				'title'             => __( 'Maximum Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order quantity. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'min_order_amount'       => array(
				'type'              => 'text',
				'title'             => __( 'Minimum Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order amount. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'max_order_amount'       => array(
				'type'              => 'text',
				'title'             => __( 'Maximum Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order amount. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'is_pro'            => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'section_shipping_rates' => array(
				'type'        => 'section',
				'title'       => __( 'Shipping Rates', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'cost_type'              => array(
				'type'        => 'select',
				'title'       => __( 'Distance Cost Type', 'wcsdm' ),
				'default'     => 'fixed',
				'options'     => array(
					'fixed'    => __( 'Fixed', 'wcsdm' ),
					'flexible' => __( 'Flexible', 'wcsdm' ),
				),
				'description' => __( 'Determine rate type either fixed or flexible rate. This input is required.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_advanced' => true,
				'is_dummy'    => true,
				'is_hidden'   => true,
				'is_required' => true,
			),
			'rate_class_0'           => array(
				'type'              => 'text',
				'title'             => __( 'Shipping Rate', 'wcsdm' ),
				'description'       => __( 'The shipping rate within the distances range. Zero value will be assumed as free shipping.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rate'           => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'section_total_cost'     => array(
				'type'        => 'section',
				'title'       => __( 'Total Cost', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'surcharge'              => array(
				'type'              => 'text',
				'title'             => __( 'Surcharge', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Surcharge that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'total_cost_type'        => array(
				'type'        => 'select',
				'title'       => __( 'Total Cost Type', 'wcsdm' ),
				'default'     => 'flat__highest',
				'options'     => array(
					'flat__highest'                   => __( 'Max - Set highest item cost as total (Flat)', 'wcsdm' ),
					'flat__average'                   => __( 'Average - Set average item cost as total (Flat)', 'wcsdm' ),
					'flat__lowest'                    => __( 'Min - Set lowest item cost as total (Flat)', 'wcsdm' ),
					'progressive__per_shipping_class' => __( 'Per Class - Accumulate total by grouping the product shipping class (Progressive)', 'wcsdm' ),
					'progressive__per_product'        => __( 'Per Product - Accumulate total by grouping the product ID (Progressive)', 'wcsdm' ),
					'progressive__per_item'           => __( 'Per Piece - Accumulate total by multiplying the quantity (Progressive)', 'wcsdm' ),
					'formula'                         => __( 'Advanced - Use math formula to calculate the total', 'wcsdm' ) . ( $this->is_pro() ? '' : ' (' . __( 'Pro Version', 'wcsdm' ) . ')' ),
				),
				'description' => __( 'Determine how is the total shipping cost calculated when the cart contents is more than 1 item.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => true,
				'is_required' => true,
			),
			'section_miscellaneous'  => array(
				'type'        => 'section',
				'title'       => __( 'Miscellaneous', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'title_rate'             => array_merge(
				$this->instance_form_fields['title'], array(
					'description' => $this->instance_form_fields['title']['description'] . ' ' . __( 'Leave blank to use the global label setting.', 'wcsdm' ),
					'default'     => '',
					'desc_tip'    => true,
					'is_advanced' => true,
					'is_dummy'    => true,
					'is_hidden'   => true,
				)
			),
			'link_advanced'          => array(
				'type'        => 'link_advanced',
				'title'       => __( 'Advanced', 'wcsdm' ),
				'is_advanced' => false,
				'is_dummy'    => true,
				'is_hidden'   => false,
			),
		);

		$shipping_classes = array();
		foreach ( WC()->shipping->get_shipping_classes() as $shipping_classes_key => $shipping_classes_value ) {
			$shipping_classes[ $shipping_classes_value->term_id ] = $shipping_classes_value;
		}

		if ( $shipping_classes ) {
			$new_fields = array();
			foreach ( $instance_rate_fields as $key => $field ) {
				$new_fields[ $key ] = $field;
				if ( 'rate_class_0' === $key ) {
					foreach ( $shipping_classes as $class_id => $class_obj ) {
						$new_fields[ 'rate_class_' . $class_id ] = array_merge(
							$field, array(
								// translators: %s is Product shipping class name.
								'title'       => sprintf( __( '"%s" Shipping Class Rate', 'wcsdm' ), $class_obj->name ),
								// translators: %s is Product shipping class name.
								'description' => sprintf( __( 'Rate for "%s" shipping class products. Leave blank to use defined default rate above.', 'wcsdm' ), $class_obj->name ),
								'default'     => '',
								'desc_tip'    => true,
								'is_advanced' => true,
								'is_dummy'    => false,
								'is_hidden'   => true,
								'is_required' => false,
							)
						);
					}
				}
			}
			$instance_rate_fields = $new_fields;
		}

		$this->_instance_rate_fields = apply_filters( $this->id . '_rate_fields', $instance_rate_fields, $this->get_instance_id() );
	}

	/**
	 * Get rate fields
	 *
	 * @since    1.4.2
	 *
	 * @param string $context Data context filter.
	 * @return array
	 */
	public function get_rates_fields( $context = '' ) {
		$rates_fields = array();

		foreach ( $this->_instance_rate_fields as $key => $field ) {
			if ( ! empty( $context ) && ! $field[ 'is_' . $context ] ) {
				continue;
			}

			$rate_field_default = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array(),
				'default'           => '',
			);

			$rate_field = wp_parse_args( $field, $rate_field_default );

			$field_type = isset( $rate_field['orig_type'] ) ? $rate_field['orig_type'] : $rate_field['type'];

			$rate_field_class = array(
				'wcsdm-field',
				'wcsdm-field--rate',
				'wcsdm-field--rate--' . $context,
				'wcsdm-field--rate--' . $context . '--' . $field_type,
				'wcsdm-field--rate--' . $context . '--' . $key,
			);

			if ( 'dummy' === $context ) {
				$rate_field_class[] = 'wcsdm-fullwidth';
			}

			if ( ! empty( $rate_field['class'] ) ) {
				$rate_field_class[] = array_merge( array_filter( explode( ' ', $rate_field['class'] ) ), $rate_field_class );
			}

			$rate_field['class'] = implode( ' ', array_unique( $rate_field_class ) );

			$custom_attributes = array(
				'data-type'     => $field_type,
				'data-id'       => $this->get_field_key( $key ),
				'data-required' => empty( $rate_field['is_required'] ) ? '0' : '1',
				'data-title'    => isset( $rate_field['title'] ) ? $rate_field['title'] : $key,
				'data-options'  => isset( $rate_field['options'] ) ? wp_json_encode( $rate_field['options'] ) : wp_json_encode( array() ),
				'data-validate' => isset( $rate_field['validate'] ) ? $rate_field['validate'] : 'text',
			);

			$rate_field['custom_attributes'] = isset( $rate_field['custom_attributes'] ) ? array_merge( $rate_field['custom_attributes'], $custom_attributes ) : $custom_attributes;

			$rates_fields[ $key ] = $rate_field;
		}

		return $rates_fields;
	}

	/**
	 * Generate wcsdm HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_wcsdm_html( $key, $data ) {
		$data = $this->populate_field( $data );

		if ( isset( $data['orig_type'] ) ) {
			$data['type'] = $data['orig_type'];
		}

		if ( 'wcsdm' === $data['type'] ) {
			$data['type'] = 'text';
		}

		if ( $data['is_required'] ) {
			$data['custom_attributes']['required'] = 'required';
		}

		if ( $data['is_pro'] && ! $this->is_pro() ) {
			$data['title'] = $data['title'] . ' (' . __( 'Pro Version', 'wcsdm' ) . ')';

			$data['disabled'] = true;
		}

		return $this->generate_settings_html( array( $key => $data ), false );
	}

	/**
	 * Generate JS templates.
	 *
	 * @since 1.2.4
	 */
	public function generate_js_template_html() {
		ob_start();
		?>
		<script type="text/template" id="tmpl-wcsdm-errors">
			<div id="{{ data.id }}" class="wcsdm-errors">
				<ul class="notice notice-error">
					<li class="wcsdm-errors--heading"><?php esc_html_e( 'Errors', 'wcsdm' ); ?>:</li>
					<# _.each(data.errors, function(error, key) { #>
					<li id="wcsdm-errors--{{ key }}">{{ error }}</li>
					<# }); #>
				</ul>
			</div>
		</script>

		<script type="text/template" id="tmpl-wcsdm-buttons">
			<div id="wcsdm-buttons" class="wcsdm-buttons">
				<# if(data.btn_left) { #>
				<button id="wcsdm-btn--{{data.btn_left.id}}" class="button button-primary button-large wcsdm-buttons-item wcsdm-buttons-item--left"><span class="dashicons dashicons-{{data.btn_left.icon}}"></span> {{data.btn_left.label}}</button>
				<# } #>
				<# if(data.btn_right) { #>
				<button id="wcsdm-btn--{{data.btn_right.id}}" class="button button-primary button-large wcsdm-buttons-item wcsdm-buttons-item--right"><span class="dashicons dashicons-{{data.btn_right.icon}}"></span> {{data.btn_right.label}}</button>
				<# } #>
			</div>
		</script>
		<script type="text/template" id="tmpl-wcsdm-map-search-panel">
			<div id="wcsdm-map-search-panel" class="wcsdm-map-search-panel">
				<a href="#" id="wcsdm-map-search-panel-toggle" class="wcsdm-map-search-panel-toggle wcsdm-link"><span class="dashicons dashicons-dismiss"></span></a>
				<div id="wcsdm-map-search-panel-main">
					<h3><?php esc_html_e( 'Store Location Picker', 'wcsdm' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Drag the store icon marker or search your address in the input box below.', 'wcsdm' ); ?></p>
					<input id="wcsdm-map-search-input" class="wcsdm-fullwidth wcsdm-map-search-input" type="search" placeholder="Search your store address here" autocomplete="off">
				</div>
			</div>
		</script>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate api_key HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_api_key_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" />
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" id="<?php echo esc_attr( $field_key ); ?>--dummy" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" readonly="readonly" /> 
					<a href="#" class="button button-primary button-small wcsdm-edit-api-key wcsdm-link" id="<?php echo esc_attr( $key ); ?>"><span class="dashicons dashicons-edit"></span></a> 
					<a href="#" class="button button-secondary button-small wcsdm-edit-api-key-cancel wcsdm-link wcsdm-hidden"><span class="dashicons dashicons-undo"></span></a>
					<span class="spinner wcsdm-spinner"></span>
					<div>
					<a href="#" class="wcsdm-show-instructions wcsdm-link"><?php esc_html_e( 'How to Get API Key?', 'wcsdm' ); ?></a>
					</div>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate store_location HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_store_location_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" readonly="readonly" /> 
					<a href="#" class="button button-primary button-small wcsdm-link wcsdm-edit-location"><span class="dashicons dashicons-location-alt"></span></a>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate store_location_picker HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_store_location_picker_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
			'default'           => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top" id="wcsdm-row-map-picker" class="wcsdm-row wcsdm-row-map-picker wcsdm-hidden">
			<td colspan="2" class="wcsdm-no-padding">
				<table id="wcsdm-table-map-picker" class="form-table wcsdm-table wcsdm-table-map-picker" cellspacing="0">
					<tr valign="top">
						<td colspan="2" class="wcsdm-no-padding">
							<div id="wcsdm-map-wrap" class="wcsdm-map-wrap">
								<div id="wcsdm-map-canvas" class="wcsdm-map-canvas"></div>
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr valign="top" id="wcsdm-row-map-instructions" class="wcsdm-row wcsdm-row-map-instructions wcsdm-hidden">
			<td colspan="2" class="wcsdm-no-padding">
				<div id="wcsdm-map-instructions">
					<div class="wcsdm-map-instructions">
						<p><?php echo wp_kses_post( __( 'This plugin uses Google Maps Platform APIs where users are required to have a valid API key to be able to use their APIs. Make sure you checked 3 the checkboxes as shown below when creating the API Key.', 'wcsdm' ) ); ?></p>
						<img src="<?php echo esc_attr( WCSDM_URL ); ?>assets/img/map-instructions.jpg" />
					</div>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table rates HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_table_rates_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top" id="wcsdm-row-dummy" class="wcsdm-row wcsdm-row-dummy">
			<td colspan="2" class="wcsdm-no-padding">
				<h3 class="wcsdm-settings-form-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<table id="wcsdm-table-dummy" class="form-table wcsdm-table wcsdm-table-dummy" cellspacing="0">
					<thead>
						<tr>
							<td class="wcsdm-col wcsdm-col--select-item">
								<input class="select-item" type="checkbox">
							</td>
							<?php foreach ( $this->get_rates_fields( 'dummy' ) as $key => $field ) : ?>
								<td class="wcsdm-col wcsdm-col--<?php echo esc_html( $key ); ?>">
									<label><?php echo esc_html( $field['title'] ); ?><?php echo $this->get_tooltip_html( $field ); // WPCS: XSS ok. ?></label>
								</td>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( $this->table_rates ) :
							foreach ( $this->table_rates as $table_rate ) :
								$this->generate_rate_row_body( $field_key, $table_rate );
							endforeach;
						endif;
						?>
					</tbody>
				</table>
				<script type="text/template" id="tmpl-wcsdm-dummy-row">
					<?php $this->generate_rate_row_body( $field_key ); ?>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table rate fieldumns
	 *
	 * @param string $field_key Table rate column key.
	 * @param array  $rate Table rate data.
	 * @return void
	 */
	private function generate_rate_row_body( $field_key, $rate = array() ) {
		?>
		<tr>
			<td class="wcsdm-col wcsdm-col--select-item">
				<input class="select-item" type="checkbox">
			</td>
			<?php foreach ( $this->get_rates_fields( 'dummy' ) as $key => $data ) : ?>
			<td class="wcsdm-col wcsdm-col--<?php echo esc_html( $key ); ?>">
				<?php
				$field_value = isset( $rate[ $key ] ) ? $rate[ $key ] : $data['default'];

				switch ( $data['type'] ) {
					case 'link_advanced':
						?>
						<a href="#" class="<?php echo esc_attr( $data['class'] ); ?>" title="<?php echo esc_attr( $data['title'] ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
						<?php
						foreach ( $this->get_rates_fields( 'hidden' ) as $hidden_key => $hidden_data ) :
							$hidden_field_value = isset( $rate[ $hidden_key ] ) ? $rate[ $hidden_key ] : $hidden_data['default'];
						?>
						<input class="<?php echo esc_attr( $hidden_data['class'] ); ?>" type="hidden" name="<?php echo esc_attr( $field_key ); ?>__<?php echo esc_attr( $hidden_key ); ?>[]" value="<?php echo esc_attr( $hidden_field_value ); ?>" <?php echo $this->get_custom_attribute_html( $hidden_data ); // WPCS: XSS ok. ?> />
						<?php
						endforeach;
						break;

					default:
						$html = $this->generate_settings_html( array( 'dummy-key---' . $key => $data ), false );

						preg_match( '/<fieldset>(.*?)<\/fieldset>/s', $html, $matches );

						if ( ! empty( $matches[0] ) ) {
							$find    = 'select' === $data['type'] ? 'value="' . $field_value . '"' : 'value=""';
							$replace = 'select' === $data['type'] ? 'value="' . $field_value . '" ' . selected( true, true, false ) : 'value="' . $field_value . '"';

							echo preg_replace( '#\s(name|id)="[^"]+"#', '', str_replace( $find, $replace, $matches[0] ) ); // WPCS: XSS ok.
						}
						break;
				}
				?>
			</td>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * Generate advanced settings form
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_table_advanced_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top" id="wcsdm-row-advanced" class="wcsdm-row wcsdm-row-advanced wcsdm-hidden">
			<td colspan="2" class="wcsdm-no-padding">
				<h3 class="wcsdm-settings-form-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<table id="wcsdm-table-advanced" class="form-table wcsdm-table wcsdm-table-advanced" cellspacing="0">
					<?php
					foreach ( $this->get_rates_fields( 'advanced' ) as $key => $data ) {
						echo preg_replace( '#\s(name)="[^"]+"#', '', $this->generate_wcsdm_html( $key, $data ) ); // WPCS: XSS ok.
					}
					?>
				</table>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate section field type HTML output
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_section_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'       => '',
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<td colspan="2" class="wcsdm-no-padding">
				<h3 class="wcsdm-settings-sub-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<?php if ( ! empty( $data['description'] ) ) : ?>
				<p><?php echo wp_kses_post( $data['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate WCSDM Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param  string $key Field key.
	 * @param  string $value Posted Value.
	 * @param  bool   $is_rate_field Is this validating rate field.
	 * @throws Exception If the field value is invalid.
	 * @return string
	 */
	public function validate_wcsdm_field( $key, $value, $is_rate_field = false ) {
		if ( $is_rate_field ) {
			$field = isset( $this->_instance_rate_fields[ $key ] ) ? $this->_instance_rate_fields[ $key ] : false;
		} else {
			$field = isset( $this->instance_form_fields[ $key ] ) ? $this->instance_form_fields[ $key ] : false;
		}

		if ( $field ) {
			$field = $this->populate_field( $field );

			if ( isset( $field['orig_type'] ) ) {
				$field['type'] = $field['orig_type'];
			}

			$type = $this->get_field_type( $field );

			if ( 'wcsdm' === $type ) {
				$type = 'text';
			}

			// Look for a validate_FIELDTYPE_field method.
			if ( is_callable( array( $this, 'validate_' . $type . '_field' ) ) ) {
				$value = $this->{'validate_' . $type . '_field'}( $key, $value );
			} else {
				$value = $this->validate_text_field( $key, $value );
			}

			// Validate required field value.
			if ( $field['is_required'] && ( ! strlen( trim( $value ) ) || is_null( $value ) ) ) {
				throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.field_required' ), $field['title'] ) );
			}

			if ( strlen( $value ) ) {
				// Validate min field value.
				if ( isset( $field['custom_attributes']['min'] ) && $value < $field['custom_attributes']['min'] ) {
					throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.field_min_value' ), $field['title'], $field['custom_attributes']['min'] ) );
				}

				// Validate max field value.
				if ( isset( $field['custom_attributes']['max'] ) && $value > $field['custom_attributes']['max'] ) {
					throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.field_max_value' ), $field['title'], $field['custom_attributes']['max'] ) );
				}
			}

			// Validate pro field.
			if ( $field['is_pro'] && ! $this->is_pro() && $value !== $field['default'] ) {
				throw new Exception( wp_sprintf( wcsdm_i18n( 'errors.need_upgrade.general' ), $field['title'] ) );
			}

			return $value;
		}

		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Validate and format table_rates settings field.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param string $value Input field currenet value.
	 * @throws Exception If the field value is invalid.
	 * @return array
	 */
	public function validate_table_rates_field( $key, $value ) {
		$rates = array();

		$post_data = $this->get_post_data();

		$rate_fields = $this->get_rates_fields( 'hidden' );

		$errors = array();

		foreach ( $rate_fields as $rate_field_key => $rate_field ) {
			$field_key = $this->get_field_key( $key . '__' . $rate_field_key );

			$values = isset( $post_data[ $field_key ] ) ? (array) $post_data[ $field_key ] : array();

			foreach ( $values as $index => $value ) {
				try {
					$value = $this->validate_wcsdm_field( $rate_field_key, $value, true );

					if ( 'total_cost_type' === $rate_field_key && ! $this->is_pro() && 'formula' === $value ) {
						throw new Exception( wcsdm_i18n( 'errors.need_upgrade.total_cost_type' ) );
					}

					$rates[ $index ][ $rate_field_key ] = $value;
				} catch ( Exception $e ) {
					// translators: %1$d = row number, %2$s = error message.
					$errors[] = wp_sprintf( __( 'Table rates row %1$d: %2$s', 'wcsdm' ), ( $index + 1 ), $e->getMessage() );
				}
			}
		}

		if ( $errors ) {
			throw new Exception( implode( '</p><p>', $errors ) );
		}

		$rule_fields = array();

		foreach ( $rate_fields as $rate_field_key => $rate_field ) {
			if ( ! isset( $rate_field['is_rule'] ) || ! $rate_field['is_rule'] ) {
				continue;
			}

			$rule_fields[] = $rate_field_key;
		}

		$filtered = array();

		$errors = array();
		foreach ( $rates as $index => $rate ) {
			try {
				$rules = array();

				foreach ( $rule_fields as $rule_field ) {
					$rules[ $rule_field ] = isset( $rate[ $rule_field ] ) ? $rate[ $rule_field ] : false;
				}

				$rate_key = implode( '___', array_values( $rules ) );

				if ( isset( $filtered[ $rate_key ] ) ) {
					$error_msg = array();
					foreach ( $rules as $rule_key => $rule_value ) {
						if ( false === $rule_value ) {
							continue;
						}

						$error_msg[] = wp_sprintf( '%s: %s', $rate_fields[ $rule_key ]['title'], $rule_value );
					}

					throw new Exception( implode( ', ', $error_msg ) );
				}

				$filtered[ $rate_key ] = $rate;
			} catch ( Exception $e ) {
				$errors[] = wp_sprintf( wcsdm_i18n( 'errors.duplicate_rate' ), ( $index + 1 ), $e->getMessage() );
			}
		}

		if ( $errors ) {
			throw new Exception( implode( '</p><p>', $errors ) );
		}

		if ( empty( $filtered ) ) {
			throw new Exception( __( 'Shipping rates table is empty', 'wcsdm' ) );
		}

		$filtered = array_values( $filtered );

		// get a list of sort columns and their data to pass to array_multisort.
		$sorted = array();
		foreach ( $filtered as $k => $v ) {
			foreach ( $rule_fields as $rule_field ) {
				$sorted[ $rule_field ][ $k ] = $v[ $rule_field ];
			}
		}

		// sort by event_type desc and then title asc.
		array_multisort(
			$sorted['max_distance'],
			SORT_ASC,
			$sorted['min_order_quantity'],
			SORT_ASC,
			$sorted['max_order_quantity'],
			SORT_ASC,
			$sorted['min_order_amount'],
			SORT_ASC,
			$sorted['max_order_amount'],
			SORT_ASC,
			$filtered
		);

		return apply_filters( $this->id . '_validate_table_rates', $filtered, $this->get_instance_id() );
	}

	/**
	 * Validate and format api_key settings field.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param string $value Input field currenet value.
	 * @throws Exception If the field value is invalid.
	 * @return array
	 */
	public function validate_api_key_field( $key, $value ) {
		if ( 'api_key_server' === $key && ! empty( $value ) ) {
			$response = $this->api_request(
				array(
					'origin'      => array( WCSDM_DEFAULT_LAT, WCSDM_DEFAULT_LNG ),
					'destination' => array( WCSDM_TEST_LAT, WCSDM_TEST_LNG ),
					'settings'    => array( 'api_key_server' => $value ),
				), false
			);

			if ( is_wp_error( $response ) ) {
				// translators: %s = API response error message.
				throw new Exception( sprintf( __( 'Server API Key Error: %s', 'wcsdm' ), $response->get_error_message() ) );
			}
		}

		return $value;
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @param array   $args Custom arguments for $settings and $package data.
	 * @param boolean $cache Is use the cached data.
	 * @throws Exception If error happen.
	 * @return array
	 */
	private function api_request( $args = array(), $cache = true ) {
		/**
		 * Developers can modify the api request via filter hooks.
		 *
		 * @since 2.0
		 *
		 * This example shows how you can modify the $pre var via custom function:
		 *
		 *      add_filter( 'wcsdm_api_request_pre', 'my_api_request_pre', 10, 4 );
		 *
		 *      function my_api_request_pre( $false, $args, $cache, $obj ) {
		 *          // Return the response data array
		 *          return array(
		 *              'distance'      => 40,
		 *              'distance_text' => '40 km',
		 *              'duration'      => 3593,
		 *              'duration_text' => '1 hour 5 mins',
		 *              'response'      => array() // Raw response from API server
		 *          );
		 *      }
		 */
		$pre = apply_filters( 'wcsdm_api_request_pre', false, $args, $cache, $this );

		if ( false !== $pre ) {
			return $pre;
		}

		try {
			$args = wp_parse_args(
				$args, array(
					'origin'      => array(),
					'destination' => array(),
					'settings'    => array(),
					'package'     => array(),
				)
			);

			// Imports variables from $args: $origin, $destination, $settings, $package.
			$origin      = $args['origin'];
			$destination = $args['destination'];
			$settings    = wp_parse_args( $args['settings'], $this->_options );
			$package     = $args['package'];

			// Check origin parameter.
			if ( empty( $origin ) ) {
				throw new Exception( __( 'Origin parameter is empty', 'wcsdm' ) );
			}

			// Check destination parameter.
			if ( empty( $destination ) ) {
				throw new Exception( __( 'Destination parameter is empty', 'wcsdm' ) );
			}

			if ( $cache && ! $this->is_debug_mode() ) {
				$cache_key = $this->id . '_' . $this->get_instance_id() . '_api_request_' . md5(
					wp_json_encode(
						array(
							'origin'      => $origin,
							'destination' => $destination,
							'package'     => $package,
							'settings'    => $settings,
						)
					)
				);

				// Check if the data already chached and return it.
				$cached_data = get_transient( $cache_key );

				if ( false !== $cached_data ) {
					return $cached_data;
				}
			}

			$api_request_data = array(
				'origins'      => is_array( $origin ) ? implode( ',', $origin ) : $origin,
				'destinations' => is_array( $destination ) ? implode( ',', $destination ) : $destination,
				'language'     => get_locale(),
			);

			foreach ( $this->instance_form_fields as $key => $field ) {
				if ( ! isset( $field['api_request'] ) ) {
					continue;
				}

				$api_request_data[ $field['api_request'] ] = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
			}

			foreach ( $api_request_data as $key => $value ) {
				$api_request_data[ $key ] = is_array( $value ) ? array_map( $value, 'rawurlencode' ) : rawurlencode( $value );
			}

			$request_url = add_query_arg( $api_request_data, $this->_google_api_url );

			$this->show_debug( __( 'API Request URL', 'wcsdm' ) . ': ' . $request_url );

			$raw_response = wp_remote_get( esc_url_raw( $request_url ) );

			// Check if HTTP request is error.
			if ( is_wp_error( $raw_response ) ) {
				throw new Exception( $raw_response->get_error_message() );
			}

			$response_body = wp_remote_retrieve_body( $raw_response );

			// Check if API response is empty.
			if ( empty( $response_body ) ) {
				throw new Exception( __( 'API response is empty', 'wcsdm' ) );
			}

			// Decode API response body.
			$response_data = json_decode( $response_body, true );

			// Check if JSON data is valid.
			$json_last_error_msg = json_last_error_msg();
			if ( $json_last_error_msg && 'No error' !== $json_last_error_msg ) {
				// translators: %s = Json error message.
				$error_message = sprintf( __( 'Error occured while decoding API response: %s', 'wcsdm' ), $json_last_error_msg );

				throw new Exception( $error_message );
			}

			// Check API response is OK.
			$status = isset( $response_data['status'] ) ? $response_data['status'] : '';
			if ( 'OK' !== $status ) {
				$error_message = __( 'API Response Error', 'wcsdm' ) . ': ' . $status;
				if ( isset( $response_data['error_message'] ) ) {
					$error_message .= ' - ' . $response_data['error_message'];
				}

				throw new Exception( $error_message );
			}

			$errors  = array();
			$results = array();

			// Get the shipping distance.
			foreach ( $response_data['rows'] as $row ) {
				foreach ( $row['elements'] as $element ) {
					// Check element status code.
					if ( 'OK' !== $element['status'] ) {
						$errors[] = $element['status'];
						continue;
					}

					$results[] = array(
						'distance'      => $element['distance']['value'],
						'distance_text' => $element['distance']['text'],
						'duration'      => $element['duration']['value'],
						'duration_text' => $element['duration']['text'],
					);
				}
			}

			if ( empty( $results ) ) {
				$error_template = array(
					'NOT_FOUND'                 => __( 'Origin and/or destination of this pairing could not be geocoded', 'wcsdm' ),
					'ZERO_RESULTS'              => __( 'No route could be found between the origin and destination', 'wcsdm' ),
					'MAX_ROUTE_LENGTH_EXCEEDED' => __( 'Requested route is too long and cannot be processed', 'wcsdm' ),
				);

				if ( ! empty( $errors ) ) {
					foreach ( $errors as $error_key ) {
						if ( isset( $error_template[ $error_key ] ) ) {
							throw new Exception( __( 'API Response Error', 'wcsdm' ) . ': ' . $error_template[ $error_key ] );
						}
					}
				}

				throw new Exception( __( 'API Response Error', 'wcsdm' ) . ': ' . __( 'No results found', 'wcsdm' ) );
			}

			if ( count( $results ) > 1 ) {
				switch ( $settings['preferred_route'] ) {
					case 'longest_duration':
						usort( $results, array( $this, 'longest_duration_results' ) );
						break;

					case 'longest_distance':
						usort( $results, array( $this, 'longest_distance_results' ) );
						break;

					case 'shortest_duration':
						usort( $results, array( $this, 'shortest_duration_results' ) );
						break;

					default:
						usort( $results, array( $this, 'shortest_distance_results' ) );
						break;
				}
			}

			$distance = floatVal( $this->convert_distance( $results[0]['distance'] ) );

			if ( empty( $distance ) ) {
				$distance = 0.1;
			}

			if ( 'yes' === $settings['round_up_distance'] ) {
				$distance = ceil( $distance );
			}

			$result = array(
				'distance'          => $distance,
				'distance_text'     => sprintf( '%s %s', $distance, ( 'metric' === $this->distance_unit ? 'km' : 'mi' ) ),
				'duration'          => $results[0]['duration'],
				'duration_text'     => $results[0]['duration_text'],
				'api_response_data' => $response_data,
				'api_request_data'  => $api_request_data,
			);

			if ( $cache && ! $this->is_debug_mode() ) {
				delete_transient( $cache_key ); // To make sure the transient data re-created, delete it first.
				set_transient( $cache_key, $result, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.
			}

			$this->show_debug( __( 'API Response', 'wcsdm' ) . ': ' . print_r( $result, true ) );

			/**
			 * Developers can modify the api request $result via filter hooks.
			 *
			 * @since 2.0
			 *
			 * This example shows how you can modify the $pre var via custom function:
			 *
			 *      add_filter( 'wcsdm_api_request', 'my_api_request', 10, 2 );
			 *
			 *      function my_api_request( $false, $args, $cache, $obj ) {
			 *          // Return the response data array
			 *          return array(
			 *              'distance'          => 40,
			 *              'distance_text'     => '40 km',
			 *              'duration'          => 3593,
			 *              'duration_text'     => '1 hour 5 mins',
			 *              'api_response_data' => array() // Raw response from API server
			 *              'api_request_data'  => array() // API request parameters
			 *          );
			 *      }
			 */
			return apply_filters( 'wcsdm_api_request', $result, $this );
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), 'error' );

			return new WP_Error( 'api_request', $e->getMessage() );
		}
	}

	/**
	 * Populate field data
	 *
	 * @since    2.0
	 * @param array $field Current field data.
	 * @return array
	 */
	private function populate_field( $field ) {
		return wp_parse_args( $field, $this->_field_default );
	}

	/**
	 * Processes and saves global shipping method options in the admin area.
	 *
	 * @since 2.0
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		if ( ! $this->instance_id ) {
			return parent::process_admin_options();
		}

		// Check we are processing the correct form for this instance.
		if ( ! isset( $_REQUEST['instance_id'] ) || absint( $_REQUEST['instance_id'] ) !== $this->instance_id ) { // WPCS: input var ok, CSRF ok.
			return false;
		}

		// Check duplicate method.
		if ( ! $this->is_pro() ) {
			$zone = WC_Shipping_Zones::get_zone_by( 'instance_id', $this->instance_id );
			if ( $zone ) {
				$duplicate = array();
				foreach ( $zone->get_shipping_methods() as $shipping_method ) {
					if ( $shipping_method->id === $this->id ) {
						$duplicate[] = $shipping_method->get_instance_id();
					}
				}

				if ( count( $duplicate ) > 1 ) {
					return $this->add_error( __( 'Multiple instances shipping method WooCommerce Shipping Distance Matrix within the same zone only available in Pro Version. Please upgrade.', 'wcsdm' ) );
				}
			}
		}

		$this->init_instance_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_instance_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					$this->instance_settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
				} catch ( Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
			}
		}

		return update_option( $this->get_instance_option_key(), apply_filters( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', $this->instance_settings, $this ), 'yes' );
	}

	/**
	 * Sanitize settings value before store to DB.
	 *
	 * @since    1.0.0
	 * @param array $settings Current settings data.
	 * @return array
	 */
	public function instance_settings_values( $settings ) {
		if ( $this->get_errors() ) {
			return $this->_options;
		}

		return $settings;
	}

	/**
	 * Inject cart cart packages to calculate shipping for addres 2 field.
	 *
	 * @since 1.0.0
	 * @param array $packages Current cart contents packages.
	 * @return array
	 */
	public function inject_cart_shipping_packages( $packages ) {
		if ( ! $this->is_calc_shipping() ) {
			return $packages;
		}

		$nonce_field  = 'woocommerce-shipping-calculator-nonce';
		$nonce_action = 'woocommerce-shipping-calculator';

		$address_1 = false;
		$address_2 = false;

		if ( isset( $_POST['calc_shipping_address_1'], $_POST['calc_shipping_address_2'], $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			$address_1 = sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_1'] ) );
			$address_2 = sanitize_text_field( wp_unslash( $_POST['calc_shipping_address_2'] ) );
		}

		foreach ( $packages as $key => $package ) {
			if ( false !== $address_1 ) {
				WC()->customer->set_billing_address_1( $address_1 );
				WC()->customer->set_shipping_address_1( $address_1 );
				$packages[ $key ]['destination']['address_1'] = $address_1;
			}

			if ( false !== $address_2 ) {
				WC()->customer->set_billing_address_2( $address_2 );
				WC()->customer->set_shipping_address_2( $address_2 );
				$packages[ $key ]['destination']['address_2'] = $address_2;
			}
		}

		return $packages;
	}

	/**
	 * Check if this method available
	 *
	 * @since    1.0.0
	 * @param boolean $available Current status is available.
	 * @param array   $package Current order package data.
	 * @return bool
	 */
	public function check_is_available( $available, $package ) {
		if ( empty( $package['contents'] ) || empty( $package['destination'] ) ) {
			return false;
		}

		return $available;
	}

	/**
	 * Calculate shipping function.
	 *
	 * @since    1.0.0
	 * @param array $package Package data array.
	 * @throws Exception Throw error if validation not passed.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		global $woocommerce;

		try {
			$api_response = $this->api_request(
				array(
					'origin'      => $this->get_origin_info( $package ),
					'destination' => $this->get_destination_info( $package ),
					'package'     => $package,
				)
			);

			// Bail early if the API request error.
			if ( is_wp_error( $api_response ) ) {
				throw new Exception( $api_response->get_error_message() );
			}

			if ( ! $api_response ) {
				return;
			}

			$calculated = $this->calculate_shipping_cost( $api_response, $package );

			// Bail early if there is no rate found.
			if ( is_wp_error( $calculated ) ) {
				throw new Exception( $calculated->get_error_message() );
			}

			// Set shipping cost.
			$cost = isset( $calculated['cost'] ) ? $calculated['cost'] : 0;

			// Set shipping courier label.
			$label = empty( $calculated['label'] ) ? $this->title : $calculated['label'];

			// Show the distance info.
			if ( 'yes' === $this->show_distance && ! empty( $api_response['distance_text'] ) ) {
				$label = sprintf( '%s (%s)', $label, $api_response['distance_text'] );
			}

			// Set meta_data info.
			$meta_data = isset( $calculated['meta_data'] ) ? $calculated['meta_data'] : $api_response;

			$rate = array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => $cost,
				'package'   => $package,
				'meta_data' => $meta_data,
			);

			// Register shipping rate to cart.
			$this->add_rate( $rate );
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Get shipping cost by distance
	 *
	 * @since    1.0.0
	 * @param array $api_response API response data.
	 * @param array $package Current cart data.
	 * @return mixed cost data array or WP_Error on failure.
	 */
	private function calculate_shipping_cost( $api_response, $package ) {
		/**
		 * Developers can modify the $rate via filter hooks.
		 *
		 * @since 2.0
		 *
		 * This example shows how you can modify the $pre var via custom function:
		 *
		 *      add_filter( 'wcsdm_calculate_shipping_cost_pre', 'my_get_rate_pre', 10, 4 );
		 *
		 *      function my_get_rate_pre( $false, $api_response, $package, $obj ) {
		 *          // Return the cost data array
		 *          return array(
		 *              'cost'      => 0,
		 *              'label'     => 'Free Shipping',
		 *              'meta_data' => array(),
		 *          );
		 *      }
		 */
		$pre = apply_filters( 'wcsdm_calculate_shipping_cost_pre', false, $api_response, $package, $this );

		if ( false !== $pre ) {
			return $pre;
		}

		if ( $this->table_rates ) {
			$offset = 0;
			foreach ( $this->table_rates as $rate ) {
				if ( $api_response['distance'] > $offset && $api_response['distance'] <= $rate['max_distance'] ) {
					$this->show_debug( __( 'Rate Match', 'wcsdm' ) . ': ' . print_r( $rate, true ) );

					// Hold costs data for flat total_cost_type.
					$flat = array();

					// Hold costs data for progressive total_cost_type.
					$progressive = array();

					foreach ( $package['contents'] as $hash => $item ) {
						$class_id   = $item['data']->get_shipping_class_id();
						$product_id = $item['data']->get_id();

						$item_cost = isset( $rate['rate_class_0'] ) ? $rate['rate_class_0'] : 0;

						if ( $class_id ) {
							$class_cost = isset( $rate[ 'rate_class_' . $class_id ] ) ? $rate[ 'rate_class_' . $class_id ] : '';
							if ( strlen( $class_cost ) ) {
								$item_cost = $class_cost;
							}
						}

						// Multiply shipping cost with distance unit.
						if ( 'flexible' === $rate['cost_type'] ) {
							$item_cost *= $api_response['distance'];
						}

						// Add cost data for flat total_cost_type.
						$flat[] = $item_cost;

						// Add cost data for progressive total_cost_type.
						$progressive[] = array(
							'item_cost'  => $item_cost,
							'class_id'   => $class_id,
							'product_id' => $product_id,
							'quantity'   => $item['quantity'],
						);
					}

					$cost = 0;

					if ( strpos( $rate['total_cost_type'], 'flat__' ) === 0 ) {
						$total_cost_type = str_replace( 'flat__', '', $rate['total_cost_type'] );
						switch ( $total_cost_type ) {
							case 'lowest':
								$cost = min( $flat );
								break;

							case 'average':
								$cost = array_sum( $flat ) / count( $flat );
								break;

							default:
								$cost = max( $flat );
								break;
						}
					} elseif ( strpos( $rate['total_cost_type'], 'progressive__' ) === 0 ) {
						$total_cost_type = str_replace( 'progressive__', '', $rate['total_cost_type'] );
						switch ( $total_cost_type ) {
							case 'per_shipping_class':
								$costs = array();
								foreach ( $progressive as $value ) {
									$costs[ $value['class_id'] ] = $value['item_cost'];
								}
								$cost = array_sum( $costs );
								break;

							case 'per_product':
								$costs = array();
								foreach ( $progressive as $value ) {
									$costs[ $value['product_id'] ] = $value['item_cost'];
								}
								$cost = array_sum( $costs );
								break;

							default:
								$costs = array();
								foreach ( $progressive as $value ) {
									$costs[ $value['product_id'] ] = $value['item_cost'] * $value['quantity'];
								}
								$cost = array_sum( $costs );
								break;
						}
					}

					if ( $rate['surcharge'] ) {
						$cost += $rate['surcharge'];
					}

					$result = array(
						'cost'      => $cost,
						'label'     => $rate['title_rate'],
						'meta_data' => array(
							'api_response' => $api_response,
						),
					);

					/**
					 * Developers can modify the $rate via filter hooks.
					 *
					 * @since 2.0
					 *
					 * This example shows how you can modify the $result var via custom function:
					 *
					 *      add_filter( 'wcsdm_calculate_shipping_cost', 'my_get_rate', 10, 4 );
					 *
					 *      function my_calculate_shipping_cost( $rate, $api_response, $package, $obj ) {
					 *          // Return the cost data array
					 *          return array(
					 *              'cost'      => 0,
					 *              'label'     => 'Free Shipping',
					 *              'meta_data' => array(),
					 *          );
					 *      }
					 */
					return apply_filters( 'wcsdm_calculate_shipping_cost', $result, $api_response, $package, $this );
				}

				$offset = $rate['max_distance'];
			}
		}

		// translators: %1$s distance value, %2$s distance unit.
		return new WP_Error( 'no_rates', sprintf( __( 'No shipping rates defined within distance range: %1$s %2$s', 'wcsdm' ), $api_response['distance'], 'imperial' === $this->distance_unit ? 'mi' : 'km' ) );
	}

	/**
	 * Get shipping origin info
	 *
	 * @since    1.0.0
	 * @param array $package The cart content data.
	 * @return array
	 */
	private function get_origin_info( $package ) {
		$origin_info = array();

		switch ( $this->origin_type ) {
			case 'coordinate':
				if ( ! empty( $this->origin_lat ) && ! empty( $this->origin_lng ) ) {
					$origin_info['origin_lat'] = $this->origin_lat;
					$origin_info['origin_lng'] = $this->origin_lng;
				}
				break;

			default:
				if ( ! empty( $this->origin_address ) ) {
					$origin_info['origin_address'] = $this->origin_address;
				}
				break;
		}

		/**
		 * Developers can modify the origin info via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the $origin_info var via custom function:
		 *
		 *      add_filter( 'wcsdm_origin_info', 'my_origin_info', 10, 3 );
		 *
		 *      function my_origin_info( $origin_info, $package, $instance_id ) {
		 *          return '1600 Amphitheatre Parkway,Mountain View,CA,94043';
		 *      }
		 */
		return apply_filters( $this->id . '_origin_info', $origin_info, $package, $this->get_instance_id() );
	}

	/**
	 * Get shipping destination info
	 *
	 * @since    1.0.0
	 * @throws Exception Throw error if validation not passed.
	 * @param array $package The cart content data.
	 * @return array
	 */
	private function get_destination_info( $package ) {
		/**
		 * Developers can modify the $destination_info var via filter hooks.
		 *
		 * @since 2.0
		 *
		 * This example shows how you can modify the shipping destination info via custom function:
		 *
		 *      add_filter( 'wcsdm_destination_info_pre', 'my_destination_info_pre', 10, 3 );
		 *
		 *      function my_destination_info_pre( $false, $package, $instance_id ) {
		 *          // Return the cost data array
		 *          return '1600 Amphitheatre Parkway, Mountain View, CA, 94043';
		 *      }
		 */
		$pre = apply_filters( $this->id . '_destination_info_pre', false, $package, $this->get_instance_id() );
		if ( false !== $pre ) {
			return $pre;
		}

		$destination_info = array();

		// Set initial destination info.
		if ( isset( $package['destination'] ) ) {
			foreach ( $package['destination'] as $key => $value ) {
				if ( 'address' === $key ) {
					continue;
				}

				$destination_info[ $key ] = $value;
			}
		}

		$errors = array();

		$country_code = ! empty( $destination_info['country'] ) ? $destination_info['country'] : false;

		$country_locale = WC()->countries->get_country_locale();

		$rules = $country_locale['default'];

		if ( $country_code && isset( $country_locale[ $country_code ] ) ) {
			$rules = array_merge( $rules, $country_locale[ $country_code ] );
		}

		// Validate shiipping fields.
		foreach ( $rules as $rule_key => $rule ) {
			if ( in_array( $rule_key, array( 'first_name', 'last_name', 'company' ), true ) ) {
				continue;
			}

			if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_' . $rule_key, true ) ) {
				continue;
			}

			$field_value = isset( $destination_info[ $rule_key ] ) ? $destination_info[ $rule_key ] : '';
			$is_required = isset( $rule['required'] ) ? $rule['required'] : false;

			if ( $is_required && ! strlen( strval( $field_value ) ) ) {
				// translators: %s = Field label.
				$errors[ $rule_key ] = sprintf( __( 'Shipping destination field is empty: %s', 'wcsdm' ), $rule['label'] );
			}

			if ( $country_code && $field_value && 'postcode' === $rule_key && ! WC_Validation::is_postcode( $field_value, $country_code ) ) {
				// translators: %s = Field label.
				$errors[ $rule_key ] = sprintf( __( 'Shipping destination field is invalid: %s', 'wcsdm' ), $rule['label'] );
			}
		}

		if ( $errors ) {
			// Set debug if error.
			foreach ( $errors as $error ) {
				$this->show_debug( $error, 'error' );
			}

			// Reset destionation info if error.
			$destination_info = array();
		} else {
			$destination = array();
			$states      = WC()->countries->states;
			$countries   = WC()->countries->countries;

			foreach ( $destination_info as $key => $value ) {
				// Skip for empty field.
				if ( ! strlen( strval( $field_value ) ) ) {
					continue;
				}

				if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_' . $key, true ) ) {
					continue;
				}

				switch ( $key ) {
					case 'country':
						if ( ! $country_code ) {
							$country_code = $value;
						}

						$destination[ $key ] = isset( $countries[ $value ] ) ? $countries[ $value ] : $value; // Set country full name.
						break;

					case 'state':
						if ( ! $country_code ) {
							$country_code = isset( $destination_info['country'] ) ? $destination_info['country'] : 'undefined';
						}

						$destination[ $key ] = isset( $states[ $country_code ][ $value ] ) ? $states[ $country_code ][ $value ] : $value; // Set state full name.
						break;

					default:
						$destination[ $key ] = $value;
						break;
				}
			}

			if ( ! $country_code ) {
				$country_code = isset( $destination['country'] ) ? $destination['country'] : false;
			}

			// Try to format the address.
			if ( $country_code ) {
				$formats = WC()->countries->get_address_formats();
				$format  = isset( $formats[ $country_code ] ) ? $formats[ $country_code ] : $formats['default'];

				if ( $format ) {
					$destination_format = array();
					$parts              = explode( "\n", str_replace( array( '{', '}' ), '', $format ) );
					foreach ( $parts as $part ) {
						if ( isset( $destination[ $part ] ) ) {
							$destination_format[ $part ] = $destination[ $part ];
						}
					}

					$destination = $destination_format;
				}
			}

			$destination_info = $destination;
		}

		/**
		 * Developers can modify the $destination_info var via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the shipping destination info via custom function:
		 *
		 *      add_filter( 'wcsdm_destination_info', 'my_destination_info', 10, 3 );
		 *
		 *      function my_destination_info( $destination_info, $package, $instance_id ) {
		 *          return '1600 Amphitheatre Parkway, Mountain View, CA, 94043';
		 *      }
		 */
		return apply_filters( $this->id . '_destination_info', $destination_info, $package, $this->get_instance_id() );
	}

	/**
	 * Check if current request is shipping calculator form.
	 *
	 * @return bool
	 */
	public function is_calc_shipping() {
		$nonce_field  = 'woocommerce-shipping-calculator-nonce';
		$nonce_action = 'woocommerce-shipping-calculator';

		if ( isset( $_POST['calc_shipping'], $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert Meters to Distance Unit
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	public function convert_distance( $meters ) {
		return ( 'metric' === $this->distance_unit ) ? $this->convert_distance_to_km( $meters ) : $this->convert_distance_to_mi( $meters );
	}

	/**
	 * Convert Meters to Miles
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	public function convert_distance_to_mi( $meters ) {
		return wc_format_decimal( ( $meters * 0.000621371 ), 1 );
	}

	/**
	 * Convert Meters to Kilometres
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	public function convert_distance_to_km( $meters ) {
		return wc_format_decimal( ( $meters * 0.001 ), 1 );
	}

	/**
	 * Sort ascending API response array by duration.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	public function shortest_duration_results( $a, $b ) {
		if ( $a['duration'] === $b['duration'] ) {
			return 0;
		}
		return ( $a['duration'] < $b['duration'] ) ? -1 : 1;
	}

	/**
	 * Sort descending API response array by duration.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	public function longest_duration_results( $a, $b ) {
		if ( $a['duration'] === $b['duration'] ) {
			return 0;
		}
		return ( $a['duration'] > $b['duration'] ) ? -1 : 1;
	}

	/**
	 * Sort ascending API response array by distance.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	public function shortest_distance_results( $a, $b ) {
		if ( $a['max_distance'] === $b['max_distance'] ) {
			return 0;
		}
		return ( $a['max_distance'] < $b['max_distance'] ) ? -1 : 1;
	}

	/**
	 * Sort descending API response array by distance.
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	public function longest_distance_results( $a, $b ) {
		if ( $a['max_distance'] === $b['max_distance'] ) {
			return 0;
		}
		return ( $a['max_distance'] > $b['max_distance'] ) ? -1 : 1;
	}

	/**
	 * Check if pro version plugin is installed and activated
	 *
	 * @since    1.5.0
	 * @return bool
	 */
	public function is_pro() {
		return wcsdm_is_pro();
	}

	/**
	 * Check if run in debug mode
	 *
	 * @since    1.5.0
	 * @return bool
	 */
	public function is_debug_mode() {
		return get_option( 'woocommerce_shipping_debug_mode', 'no' ) === 'yes';
	}

	/**
	 * Show debug info
	 *
	 * @since    1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $type The type of notice.
	 * @return void
	 */
	public function show_debug( $message, $type = '' ) {
		if ( empty( $message ) ) {
			return;
		}

		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			return;
		}

		if ( defined( 'WC_DOING_AJAX' ) ) {
			return;
		}

		if ( ! $this->is_debug_mode() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$debug_key = md5( $message );

		if ( isset( $this->_debugs[ $debug_key ] ) ) {
			return;
		}

		$this->_debugs[ $debug_key ] = $message;

		$debug_prefix = strtoupper( $this->id ) . '_' . $this->get_instance_id();

		if ( ! empty( $type ) ) {
			$debug_prefix .= '_' . strtoupper( $type );
		}

		wc_add_notice( $debug_prefix . ' => ' . $message, 'notice' );
	}
}
