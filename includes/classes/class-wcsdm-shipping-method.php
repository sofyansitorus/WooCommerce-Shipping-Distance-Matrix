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
class Wcsdm_Shipping_Method extends WC_Shipping_Method {

	/**
	 * All options data
	 *
	 * @since    1.4.2
	 * @var array
	 */
	private $options = array();

	/**
	 * All debugs data
	 *
	 * @since    1.4.2
	 * @var array
	 */
	private $debugs = array();

	/**
	 * Rate fields data
	 *
	 * @since    2.0
	 * @var array
	 */
	private $instance_rate_fields = array();

	/**
	 * Default data
	 *
	 * @since    2.0
	 * @var array
	 */
	private $field_default = array(
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
		$this->method_title = WCSDM_METHOD_TITLE;

		// Title shown in admin.
		$this->title = $this->method_title;

		// Description shown in admin.
		$this->method_description = __( 'WooCommerce shipping rates calculator allows you to easily offer shipping rates based on the distance calculated using Google Maps Distance Matrix Service API.', 'wcsdm' );

		$this->enabled = $this->get_option( 'enabled' );

		$this->instance_id = absint( $instance_id );

		$this->supports = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Init settings
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public function init() {
		// Register hooks.
		$this->init_hooks();

		// Load the settings API.
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		$this->init_rate_fields(); // Init rate fields.

		// Define user set variables.
		foreach ( $this->instance_form_fields as $key => $field ) {
			$default = isset( $field['default'] ) ? $field['default'] : null;

			$this->options[ $key ] = $this->get_option( $key, $default );

			$this->{$key} = $this->options[ $key ];
		}
	}

	/**
	 * Register actions/filters hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		// Sanitize settings fields.
		add_filter( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', array( $this, 'instance_settings_values' ), 10 );
	}

	/**
	 * Init form fields.
	 *
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$form_fields = array(
			'field_group_general'         => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group',
				'title'     => __( 'General Settings', 'wcsdm' ),
			),
			'tax_status'                  => array(
				'title'       => __( 'Tax Status', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Tax status of fee.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'taxable',
				'options'     => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'field_group_store_location'  => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group',
				'title'     => __( 'Store Location Settings', 'wcsdm' ),
			),
			'api_key'                     => array(
				'title'       => __( 'Distance Calculator API Key', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'api_key',
				'description' => __( 'API Key used to calculate the shipping address distance. Required Google API Service: Distance Matrix API.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the pencil icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
			),
			'api_key_picker'              => array(
				'title'       => __( 'Location Picker API Key', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'api_key',
				'description' => __( 'API Key used to render the location picker map. Required Google API Services: Maps JavaScript API, Geocoding API, Places API.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Click the pencil icon on the right to edit', 'wcsdm' ),
				'is_required' => true,
			),
			'origin_type'                 => array(
				'title'             => __( 'Store Origin Data Type', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'origin_type',
				'description'       => __( 'Preferred data that will be used as the origin info when calculating the distance.', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => 'coordinate',
				'options'           => array(
					'coordinate' => __( 'Coordinate (Recommended)', 'wcsdm' ),
					'address'    => __( 'Address (Less Accurate)', 'wcsdm' ),
				),
				'custom_attributes' => array(
					'data-fields' => wp_json_encode(
						array(
							'coordinate' => array( 'woocommerce_wcsdm_origin_lat', 'woocommerce_wcsdm_origin_lng' ),
							'address'    => array( 'woocommerce_wcsdm_origin_address' ),
						)
					),
				),
			),
			'origin_lat'                  => array(
				'title'             => __( 'Store Location Latitude', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'text',
				'description'       => __( 'Store location latitude coordinates', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => __( 'Click the map icon on the right Store Origin Data Type to edit', 'wcsdm' ),
				'is_required'       => true,
				'custom_attributes' => array(
					'readonly' => true,
				),
			),
			'origin_lng'                  => array(
				'title'             => __( 'Store Location Longitude', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'text',
				'description'       => __( 'Store location longitude coordinates', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => __( 'Click the map icon on the right Store Origin Data Type to edit', 'wcsdm' ),
				'is_required'       => true,
				'custom_attributes' => array(
					'readonly' => true,
				),
			),
			'origin_address'              => array(
				'title'             => __( 'Store Location Address', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'text',
				'description'       => __( 'Store location full address', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => __( 'Click the map icon on the right Store Origin Data Type to edit', 'wcsdm' ),
				'is_required'       => true,
				'custom_attributes' => array(
					'readonly' => true,
				),
			),
			'field_group_route'           => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group',
				'title'     => __( 'Route Settings', 'wcsdm' ),
			),
			'travel_mode'                 => array(
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
			'route_restrictions'          => array(
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
			'preferred_route'             => array(
				'title'       => __( 'Preferred Route', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'description' => __( 'Preferred route that will be used for calculation if API provide several routes', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'shortest_distance',
				'options'     => array(
					'shortest_distance' => __( 'Shortest Distance', 'wcsdm' ),
					'longest_distance'  => __( 'Longest Distance', 'wcsdm' ),
					'shortest_duration' => __( 'Shortest Duration', 'wcsdm' ),
					'longest_duration'  => __( 'Longest Duration', 'wcsdm' ),
				),
			),
			'distance_unit'               => array(
				'title'             => __( 'Distance Units', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'select',
				'description'       => __( 'Google Maps Distance Matrix API distance units parameter.', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => 'metric',
				'options'           => array(
					'metric'   => __( 'Kilometer', 'wcsdm' ),
					'imperial' => __( 'Mile', 'wcsdm' ),
				),
				'api_request'       => 'units',
				'custom_attributes' => array(
					'data-fields' => wp_json_encode(
						array(
							'targets' => array(
								'#wcsdm-table--table_rates--dummy .wcsdm-col--rate_class_0 .label-text',
								'#wcsdm-table--advanced_rate .wcsdm-field--context--advanced--section_shipping_rates',
							),
							'label'   => array(
								'metric'   => __( 'Rate per Kilometer', 'wcsdm' ),
								'imperial' => __( 'Rate per Mile', 'wcsdm' ),
							),
						)
					),
				),
			),
			'round_up_distance'           => array(
				'title'       => __( 'Round Up Distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Round up the calculated shipping distance with decimal to the nearest absolute number.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'show_distance'               => array(
				'title'       => __( 'Show Distance Info', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Show the distance info to customer during checkout.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'field_group_total_cost'      => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'title',
				'class'       => 'wcsdm-field-group',
				'title'       => __( 'Global Rates Settings', 'wcsdm' ),
				'description' => __( 'Default settings that will be inherited by certain settings in table rates when it is empty.', 'wcsdm' ),
			),
			'title'                       => array(
				'title'       => __( 'Label', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'text',
				'description' => __( 'This controls the label which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
				'is_required' => true,
				'table_rate'  => array(
					'insert_after' => 'section_general',
					'attrs'        => array(
						'default'     => '',
						'desc_tip'    => true,
						'is_advanced' => true,
						'is_dummy'    => true,
						'is_hidden'   => true,
						'is_required' => false,
					),
				),
			),
			'min_cost'                    => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'text',
				'title'             => __( 'Minimum Cost', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Minimum cost that will be applied.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'section_total_cost',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => true,
						'is_hidden'   => true,
						'validate'    => 'number',
					),
				),
			),
			'surcharge_type'              => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'title'       => __( 'Surcharge Type', 'wcsdm' ),
				'default'     => 'fixed',
				'description' => __( 'Surcharge type that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_required' => true,
				'options'     => array(
					'none'       => __( 'None', 'wcsdm' ),
					'fixed'      => __( 'Fixed', 'wcsdm' ),
					'percentage' => __( 'Percentage', 'wcsdm' ),
				),
				'table_rate'  => array(
					'insert_after' => 'min_cost',
					'attrs'        => array(
						'is_advanced' => true,
						'is_hidden'   => true,
					),
				),
			),
			'surcharge'                   => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'text',
				'title'             => __( 'Surcharge Amount', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Surcharge amount that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'surcharge_type',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => true,
						'is_hidden'   => true,
						'validate'    => 'number',
						'title'       => __( 'Surcharge', 'wcsdm' ),
					),
				),
			),
			'discount_type'               => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'title'       => __( 'Discount Type', 'wcsdm' ),
				'default'     => 'fixed',
				'description' => __( 'Discount type that will be deducted to the total shipping cost.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_required' => true,
				'options'     => array(
					'none'       => __( 'None', 'wcsdm' ),
					'fixed'      => __( 'Fixed', 'wcsdm' ),
					'percentage' => __( 'Percentage', 'wcsdm' ),
				),
				'table_rate'  => array(
					'insert_after' => 'surcharge',
					'attrs'        => array(
						'is_advanced' => true,
						'is_hidden'   => true,
					),
				),
			),
			'discount'                    => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'text',
				'title'             => __( 'Discount Amount', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Discount amount that will be deducted to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'discount_type',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => true,
						'is_hidden'   => true,
						'validate'    => 'number',
						'title'       => __( 'Discount', 'wcsdm' ),
					),
				),
			),
			'total_cost_type'             => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'select',
				'title'       => __( 'Total Cost Type', 'wcsdm' ),
				'default'     => 'flat__highest',
				'description' => __( 'Determine how is the total shipping cost will be calculated.', 'wcsdm' ),
				'desc_tip'    => true,
				'is_required' => true,
				'options'     => array(
					'flat__highest'                   => __( 'Max - Set highest item cost as total (Flat)', 'wcsdm' ),
					'flat__average'                   => __( 'Average - Set average item cost as total (Flat)', 'wcsdm' ),
					'flat__lowest'                    => __( 'Min - Set lowest item cost as total (Flat)', 'wcsdm' ),
					'progressive__per_shipping_class' => __( 'Per Class - Accumulate total by grouping the product shipping class (Progressive)', 'wcsdm' ),
					'progressive__per_product'        => __( 'Per Product - Accumulate total by grouping the product ID (Progressive)', 'wcsdm' ),
					'progressive__per_item'           => __( 'Per Piece - Accumulate total by multiplying the quantity (Progressive)', 'wcsdm' ),
				),
				'table_rate'  => array(
					'insert_after' => 'discount',
					'attrs'        => array(
						'is_advanced' => true,
						'is_dummy'    => false,
						'is_hidden'   => true,
					),
				),
			),
			'field_group_table_rates'     => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'title',
				'class'       => 'wcsdm-field-group',
				'title'       => __( 'Table Rates Settings', 'wcsdm' ),
				'description' => __( 'Determine the shipping cost based on the shipping address distance and extra advanced rules. The rate row that will be chosen during checkout is the rate row with the maximum distance value closest with the calculated shipping address distance and the order info must matched with the extra advanced rules if defined. You can sort the rate row priority manually when it has the same maximum distance values by dragging vertically the "move" icon on the right. First come first served.', 'wcsdm' ),
			),
			'table_rates'                 => array(
				'type'  => 'table_rates',
				'title' => __( 'Table Rates Settings', 'wcsdm' ),
			),
			'field_group_advanced_rate'   => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group wcsdm-field-group-hidden',
				'title'     => __( 'Advanced Rate Settings', 'wcsdm' ),
			),
			'advanced_rate'               => array(
				'type'  => 'advanced_rate',
				'title' => __( 'Advanced Table Rate Settings', 'wcsdm' ),
			),
			'field_group_location_picker' => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group wcsdm-field-group-hidden',
				'title'     => __( 'Store Location Picker', 'wcsdm' ),
			),
			'store_location_picker'       => array(
				'title'       => __( 'Store Location Picker', 'wcsdm' ),
				'type'        => 'store_location_picker',
				'description' => __( 'Drag the store icon marker or search your address in the input box below.', 'wcsdm' ),
			),
			'js_template'                 => array(
				'type' => 'js_template',
			),
			'field_group_third_party'     => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'title',
				'class'       => 'wcsdm-field-group',
				'title'       => __( 'Third-Party Settings', 'wcsdm' ),
				'description' => __( 'Settings added by third-party plugins.', 'wcsdm' ),
			),
		);

		/**
		 * Developers can modify the $form_fields var via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the form fields data via custom function:
		 *
		 *      add_filter( 'wcsdm_form_fields', 'my_wcsdm_form_fields', 10, 2 );
		 *
		 *      function my_wcsdm_form_fields( $form_fields, $instance_id ) {
		 *          return array();
		 *      }
		 */
		$this->instance_form_fields = apply_filters( 'wcsdm_form_fields', $form_fields, $this->get_instance_id() );
	}

	/**
	 * Init rate fields.
	 *
	 * @since    2.0
	 */
	public function init_rate_fields() {
		$rate_fields = array(
			'section_shipping_rules' => array(
				'type'        => 'title',
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
				'default'           => '0',
				'validate'          => 'number',
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
				'default'           => '0',
				'validate'          => 'number',
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
				'default'           => '0',
				'validate'          => 'number',
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
				'default'           => '0',
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'section_shipping_rates' => array(
				'type'        => 'title',
				'title'       => __( 'Shipping Rates', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'rate_class_0'           => array(
				'type'              => 'text',
				'title'             => __( 'Distance Unit Rate', 'wcsdm' ),
				'description'       => __( 'The shipping rate within the distances range. Zero value will be assumed as free shipping.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rate'           => true,
				'is_rule'           => true,
				'default'           => '0',
				'validate'          => 'number',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'section_total_cost'     => array(
				'type'        => 'title',
				'title'       => __( 'Total Cost', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'section_general'        => array(
				'type'        => 'title',
				'title'       => __( 'General', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'link_advanced'          => array(
				'type'        => 'link_advanced',
				'title'       => __( 'Advanced', 'wcsdm' ),
				'class'       => 'wcsdm-link wcsdm-link--advanced-rate',
				'is_advanced' => false,
				'is_dummy'    => true,
				'is_hidden'   => false,
			),
			'link_sort'              => array(
				'type'        => 'link_sort',
				'title'       => __( 'Sort', 'wcsdm' ),
				'class'       => 'wcsdm-link wcsdm-link--sort',
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
			$rate_class_0 = $rate_fields['rate_class_0'];
			foreach ( $shipping_classes as $class_id => $class_obj ) {
				$rate_class_data = array_merge(
					$rate_class_0,
					array(
						// translators: %s is Product shipping class name.
						'title'       => sprintf( __( '"%s" Shipping Class Rate', 'wcsdm' ), $class_obj->name ),
						// translators: %s is Product shipping class name.
						'description' => sprintf( __( 'Rate for "%s" shipping class products. Leave blank to use defined default rate above.', 'wcsdm' ), $class_obj->name ),
						'default'     => '',
						'is_advanced' => true,
						'is_dummy'    => false,
						'is_hidden'   => true,
						'is_required' => false,
					)
				);

				$rate_fields = wcsdm_array_insert_after( 'rate_class_0', $rate_fields, 'rate_class_' . $class_id, $rate_class_data );
			}
		}

		foreach ( $this->instance_form_fields as $key => $field ) {
			if ( ! isset( $field['table_rate'] ) || ! $field['table_rate'] ) {
				continue;
			}

			if ( is_bool( $field['table_rate'] ) ) {
				$rate_fields[ $key ] = $field;
			} elseif ( is_array( $field['table_rate'] ) ) {
				if ( isset( $field['table_rate']['attrs'] ) && is_array( $field['table_rate']['attrs'] ) ) {
					$field = array_merge( $field, $field['table_rate']['attrs'] );
				}

				$field_type = isset( $field['orig_type'] ) ? $field['orig_type'] : $field['type'];

				if ( 'select' === $field_type ) {
					if ( isset( $field['options'] ) && ! isset( $field['table_rate']['attrs']['options'] ) ) {
						$field['options'] = array_merge(
							array(
								'inherit' => __( 'Inherit - Use global setting', 'wcsdm' ),
							),
							$field['options']
						);
					}

					if ( isset( $field['default'] ) && ! isset( $field['table_rate']['attrs']['default'] ) ) {
						$field['default'] = 'inherit';
					}
				}

				if ( 'select' !== $field_type && isset( $field['description'] ) && ! isset( $field['table_rate']['attrs']['description'] ) ) {
					$field['description'] = sprintf( '%1$s %2$s', $field['description'], __( 'Leave blank to inherit from the global setting.', 'wcsdm' ) );
				}

				if ( isset( $field['table_rate']['insert_after'] ) ) {
					$rate_fields = wcsdm_array_insert_after( $field['table_rate']['insert_after'], $rate_fields, $key, $field );
				} elseif ( isset( $field['table_rate']['insert_before'] ) ) {
					$rate_fields = wcsdm_array_insert_before( $field['table_rate']['insert_before'], $rate_fields, $key, $field );
				} else {
					$rate_fields[ $key ] = $field;
				}
			}
		}

		/**
		 * Developers can modify the $rate_fields var via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the rate fields data via custom function:
		 *
		 *      add_filter( 'wcsdm_rate_fields', 'my_wcsdm_rate_fields', 10, 2 );
		 *
		 *      function my_wcsdm_rate_fields( $rate_fields, $instance_id ) {
		 *          return array();
		 *      }
		 */
		$this->instance_rate_fields = apply_filters( 'wcsdm_rate_fields', $rate_fields, $this->get_instance_id() );
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

		foreach ( $this->instance_rate_fields as $key => $field ) {
			if ( ! empty( $context ) && ( ! isset( $field[ 'is_' . $context ] ) || ! $field[ 'is_' . $context ] ) ) {
				continue;
			}

			if ( ! empty( $context ) ) {
				$field['context'] = $context;
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
				'is_rate'           => true,
			);

			$rate_field = wp_parse_args( $field, $rate_field_default );

			$rates_fields[ $key ] = $rate_field;
		}

		return $rates_fields;
	}

	/**
	 * Get Rate Field Value
	 *
	 * @since 2.0.7
	 * @param string $key Rate field key.
	 * @param array  $rate Rate row data.
	 * @param string $default Default rate field value.
	 */
	private function get_rate_field_value( $key, $rate, $default = '' ) {
		$value = isset( $rate[ $key ] ) ? $rate[ $key ] : $default;

		if ( 0 === strpos( $key, 'rate_class_' ) && isset( $rate['cost_type'] ) && 'fixed' === $rate['cost_type'] ) {
			$value = 0;
		}

		if ( 'min_cost' === $key && isset( $rate['rate_class_0'], $rate['cost_type'] ) && 'fixed' === $rate['cost_type'] ) {
			$value = $rate['rate_class_0'];
		}

		return $value;
	}

	/**
	 * Generate wcsdm field.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_wcsdm_html( $key, $data ) {
		$data = $this->populate_field( $key, $data );

		return $this->generate_settings_html( array( $key => $data ), false );
	}

	/**
	 * Generate js_template field.
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
			<div id="wcsdm-map-search-panel" class="wcsdm-map-search-panel wcsdm-hidden expanded">
				<button type="button" id="wcsdm-map-search-panel-toggle" class="wcsdm-map-search-panel-toggle wcsdm-map-search-element"><span class="dashicons"></button>
				<input id="wcsdm-map-search-input" class="wcsdm-fullwidth wcsdm-map-search-input wcsdm-map-search-element" type="search" placeholder="<?php echo esc_html__( 'Type your store location address...', 'wcsdm' ); ?>" autocomplete="off">
			</div>
		</script>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate api_key field.
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
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input type="text" class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" readonly="readonly" />
					<a href="#" class="button button-secondary wcsdm-buttons--has-icon wcsdm-edit-api-key wcsdm-link" id="<?php echo esc_attr( $key ); ?>" title="<?php esc_attr_e( 'Edit API Key', 'wcsdm' ); ?>"><span class="dashicons"></span></a>
					<a href="https://cloud.google.com/maps-platform/#get-started" target="_blank" class="button button-secondary wcsdm-buttons--has-icon wcsdm-link" id="<?php echo esc_attr( $key ); ?>" title="<?php esc_attr_e( 'Get API Key', 'wcsdm' ); ?>"><span class="dashicons dashicons-admin-network"></span></a>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate origin_type field.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_origin_type_html( $key, $data ) {
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
			'options'           => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $this->get_option( $key ) ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
						<?php endforeach; ?>
					</select>
					<a href="#" class="button button-secondary wcsdm-buttons--has-icon wcsdm-link wcsdm-edit-location-picker" title="<?php esc_attr_e( 'Pick Location', 'wcsdm' ); ?>"><span class="dashicons dashicons-location"></span></a>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate store_location_picker field.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_store_location_picker_html( $key, $data ) {
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
		<tr valign="top" class="wcsdm-row">
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
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table_rates field.
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
		<tr valign="top">
			<td colspan="2" class="wcsdm-no-padding">
				<table id="wcsdm-table--table_rates--dummy" class="form-table wcsdm-table wcsdm-table--table_rates--dummy">
					<thead>
						<tr>
							<td class="wcsdm-col wcsdm-col--select-item">
								<input class="select-item" type="checkbox">
							</td>
							<?php foreach ( $this->get_rates_fields( 'dummy' ) as $key => $field ) : ?>
								<td class="wcsdm-col wcsdm-col--<?php echo esc_html( $key ); ?>">
									<label><span class="label-text"><?php echo esc_html( $field['title'] ); ?></span><?php echo $this->get_tooltip_html( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
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
	 * Generate table rate_row_body
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
			<?php
			foreach ( $this->get_rates_fields( 'dummy' ) as $key => $data ) :
				$data = $this->populate_field( $key, $data );
				?>
			<td class="wcsdm-col wcsdm-col--<?php echo esc_html( $key ); ?>">
				<?php
				$field_value = $this->get_rate_field_value( $key, $rate, $data['default'] );

				switch ( $data['type'] ) {
					case 'link_sort':
						?>
						<a href="#" class="<?php echo esc_attr( $data['class'] ); ?>" title="<?php echo esc_attr( $data['title'] ); ?>"><span class="dashicons dashicons-move"></span></a>
						<?php
						break;
					case 'link_advanced':
						?>
						<a href="#" class="<?php echo esc_attr( $data['class'] ); ?>" title="<?php echo esc_attr( $data['title'] ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
						<?php
						foreach ( $this->get_rates_fields( 'hidden' ) as $hidden_key => $hidden_field ) :
							$hidden_field = $this->populate_field( $hidden_key, $hidden_field );
							$hidden_value = $this->get_rate_field_value( $hidden_key, $rate, $hidden_field['default'] );
							?>
						<input class="<?php echo esc_attr( $hidden_field['class'] ); ?>" type="hidden" name="<?php echo esc_attr( $field_key ); ?>__<?php echo esc_attr( $hidden_key ); ?>[]" value="<?php echo esc_attr( $hidden_value ); ?>" <?php echo $this->get_custom_attribute_html( $hidden_field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							<?php
						endforeach;
						break;

					default:
						$html = $this->generate_settings_html( array( 'fake--field--' . $key => $data ), false );

						preg_match( '/<fieldset>(.*?)<\/fieldset>/s', $html, $matches );

						if ( ! empty( $matches[0] ) ) {
							$output = preg_replace( '#\s(name|id)="[^"]+"#', '', $matches[0] );

							$find    = 'select' === $data['type'] ? 'value="' . $field_value . '"' : 'value=""';
							$replace = 'select' === $data['type'] ? 'value="' . $field_value . '" ' . selected( true, true, false ) : 'value="' . $field_value . '"';

							$output = str_replace( $find, $replace, $output );

							echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 * Generate advanced_rate field
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_advanced_rate_html( $key, $data ) {
		$defaults = array(
			'title' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<td colspan="2" class="wcsdm-no-padding">
				<table id="wcsdm-table--advanced-rate" class="form-table wcsdm-table wcsdm-table--advanced-rate">
					<?php
					foreach ( $this->get_rates_fields( 'advanced' ) as $key => $data ) {
						$this->generate_settings_html( array( 'fake--field--' . $key => $this->populate_field( $key, $data ) ) );
					}
					?>
				</table>
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
			$field = isset( $this->instance_rate_fields[ $key ] ) ? $this->instance_rate_fields[ $key ] : false;
		} else {
			$field = isset( $this->instance_form_fields[ $key ] ) ? $this->instance_form_fields[ $key ] : false;
		}

		if ( $field ) {
			$field = $this->populate_field( $key, $field );

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

			try {
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
			} catch ( Exception $e ) {
				// translators: %s is the error message.
				throw new Exception( sprintf( __( 'Error: %s', 'wcsdm' ), $e->getMessage() ) );
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
	 * @param string $value Input field current value.
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
					$rates[ $index ][ $rate_field_key ] = $this->validate_wcsdm_field( $rate_field_key, $value, true );
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
			$rules = array();

			foreach ( $rule_fields as $rule_field ) {
				$rules[ $rule_field ] = isset( $rate[ $rule_field ] ) && strlen( $rate[ $rule_field ] ) ? $rate[ $rule_field ] : false;
			}

			$rate_key = implode( '___', array_values( $rules ) );

			try {
				if ( isset( $filtered[ $rate_key ] ) ) {
					$error_msg = array();

					foreach ( $rules as $rule_key => $rule_value ) {
						if ( false === $rule_value || 'max_distance' === $rule_key ) {
							continue;
						}

						$error_msg[] = wp_sprintf( '%s: %s', $rate_fields[ $rule_key ]['title'], $rule_value );
					}

					throw new Exception( implode( ', ', $error_msg ) );
				}

				$filtered[ $rate_key ] = array(
					'index' => $index,
					'rate'  => $rate,
				);
			} catch ( Exception $e ) {
				$errors[] = wp_sprintf(
					wcsdm_i18n( 'errors.table_rate_row' ),
					( $index + 1 ),
					wp_sprintf( wcsdm_i18n( 'errors.duplicate_rate_row' ), $filtered[ $rate_key ]['index'], $e->getMessage() )
				);
			}
		}

		if ( $errors ) {
			throw new Exception( implode( '</p><p>', $errors ) );
		}

		if ( empty( $filtered ) ) {
			throw new Exception( __( 'Shipping rates table is empty', 'wcsdm' ) );
		}

		$filtered_values = array();

		foreach ( $filtered as $row ) {
			$filtered_values[] = $row['rate'];
		}

		/**
		 * Developers can modify the $filtered_values var via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the filtered table rates data via custom function:
		 *
		 *      add_filter( 'wcsdm_validate_table_rates', 'my_wcsdm_validate_table_rates', 10, 2 );
		 *
		 *      function my_wcsdm_validate_table_rates( $filtered_values, $instance_id ) {
		 *          return array();
		 *      }
		 */
		return apply_filters( 'wcsdm_validate_table_rates', $filtered_values, $this->get_instance_id() );
	}

	/**
	 * Get API Key for the API request
	 *
	 * @since 2.0.8
	 *
	 * @return string
	 */
	private function api_request_key() {
		return $this->api_key;
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
		 *      add_filter( 'wcsdm_api_request_pre', 'my_wcsdm_api_request_pre', 10, 4 );
		 *
		 *      function my_wcsdm_api_request_pre( $false, $args, $cache, $obj ) {
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
				$args,
				array(
					'origin'      => array(),
					'destination' => array(),
					'settings'    => array(),
					'package'     => array(),
				)
			);

			// Imports variables from args: origin, destination, settings, package.
			$settings    = wp_parse_args( $args['settings'], $this->options );
			$package     = $args['package'];
			$origin      = $args['origin'];
			$destination = $args['destination'];

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

				// Check if the data already cached and return it.
				$cached_data = get_transient( $cache_key );

				if ( false !== $cached_data ) {
					return $cached_data;
				}
			}

			$api_request_data = array(
				'origins'      => $origin,
				'destinations' => $destination,
				'language'     => get_locale(),
				'key'          => $this->api_request_key(),
			);

			foreach ( $this->instance_form_fields as $key => $field ) {
				if ( ! isset( $field['api_request'] ) ) {
					continue;
				}

				$api_request_data[ $field['api_request'] ] = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
			}

			$api = new Wcsdm_API();

			$results = $api->calculate_distance( $api_request_data );

			if ( is_wp_error( $results ) ) {
				throw new Exception( $results->get_error_message() );
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
				'distance'         => $distance,
				'distance_text'    => sprintf( '%s %s', $distance, ( 'metric' === $this->distance_unit ? 'km' : 'mi' ) ),
				'duration'         => $results[0]['duration'],
				'duration_text'    => $results[0]['duration_text'],
				'api_request_data' => $api_request_data,
			);

			if ( $cache && ! $this->is_debug_mode() ) {
				delete_transient( $cache_key ); // To make sure the transient data re-created, delete it first.
				set_transient( $cache_key, $result, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.
			}

			$this->show_debug( __( 'API Response', 'wcsdm' ) . ': ' . wp_json_encode( $result ) );

			/**
			 * Developers can modify the api request $result via filter hooks.
			 *
			 * @since 2.0
			 *
			 * This example shows how you can modify the $pre var via custom function:
			 *
			 *      add_filter( 'wcsdm_api_request', 'my_wcsdm_api_request', 10, 2 );
			 *
			 *      function my_wcsdm_api_request( $result, $obj ) {
			 *          // Return the response data array
			 *          return array(
			 *              'distance'          => 40,
			 *              'distance_text'     => '40 km',
			 *              'duration'          => 3593,
			 *              'duration_text'     => '1 hour 5 mins',
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
	 *
	 * @param array $key Current field key.
	 * @param array $data Current field data.
	 *
	 * @return array
	 */
	private function populate_field( $key, $data ) {
		$data = wp_parse_args( $data, $this->field_default );

		if ( isset( $data['orig_type'] ) ) {
			$data['type'] = $data['orig_type'];
		}

		if ( 'wcsdm' === $data['type'] ) {
			$data['type'] = 'text';
		}

		$data_classes = isset( $data['class'] ) ? explode( ' ', $data['class'] ) : array();

		array_push( $data_classes, 'wcsdm-field', 'wcsdm-field-key--' . $key, 'wcsdm-field-type--' . $data['type'] );

		if ( isset( $data['is_rate'] ) && $data['is_rate'] ) {
			array_push( $data_classes, 'wcsdm-field--rate' );
			array_push( $data_classes, 'wcsdm-field--rate--' . $data['type'] );
			array_push( $data_classes, 'wcsdm-field--rate--' . $key );
		}

		if ( isset( $data['context'] ) && $data['context'] ) {
			array_push( $data_classes, 'wcsdm-field--context--' . $data['context'] );
			array_push( $data_classes, 'wcsdm-field--context--' . $data['context'] . '--' . $data['type'] );
			array_push( $data_classes, 'wcsdm-field--context--' . $data['context'] . '--' . $key );

			if ( 'dummy' === $data['context'] ) {
				array_push( $data_classes, 'wcsdm-fullwidth' );
			}
		}

		$data_is_required = isset( $data['is_required'] ) && $data['is_required'];

		if ( $data_is_required ) {
			array_push( $data_classes, 'wcsdm-field--is-required' );
		}

		$data['class'] = implode( ' ', array_map( 'trim', array_unique( array_filter( $data_classes ) ) ) );

		$custom_attributes = array(
			'data-type'        => $data['type'],
			'data-key'         => $key,
			'data-title'       => isset( $data['title'] ) ? $data['title'] : $key,
			'data-id'          => $this->get_field_key( $key ),
			'data-context'     => isset( $data['context'] ) ? $data['context'] : '',
			'data-title'       => isset( $data['title'] ) ? $data['title'] : $key,
			'data-options'     => isset( $data['options'] ) ? wp_json_encode( $data['options'] ) : wp_json_encode( array() ),
			'data-validate'    => isset( $data['validate'] ) ? $data['validate'] : 'text',
			'data-is_rate'     => empty( $data['is_rate'] ) ? '0' : '1',
			'data-is_required' => empty( $data['is_required'] ) ? '0' : '1',
			'data-is_rule'     => empty( $data['is_rule'] ) ? '0' : '1',
		);

		$data['custom_attributes'] = array_merge( $data['custom_attributes'], $custom_attributes );

		return $data;
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
		if ( ! isset( $_REQUEST['instance_id'] ) || absint( $_REQUEST['instance_id'] ) !== $this->instance_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$this->init_instance_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_instance_form_fields() as $key => $field ) {
			if ( 'title' === $this->get_field_type( $field ) ) {
				continue;
			}

			try {
				$this->instance_settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
			} catch ( Exception $e ) {
				$this->add_error( $e->getMessage() );
			}
		}

		return update_option( $this->get_instance_option_key(), apply_filters( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', $this->instance_settings, $this ), 'yes' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
			return $this->options;
		}

		return $settings;
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

			// Bail early if the API response is empty.
			if ( ! $api_response ) {
				throw new Exception( __( 'API Response data is empty', 'wcsdm' ) );
			}

			$calculated = $this->calculate_shipping_cost( $api_response, $package );

			// Bail early if there is no rate found.
			if ( is_wp_error( $calculated ) ) {
				throw new Exception( $calculated->get_error_message() );
			}

			// Bail early if the calculated data format is invalid.
			if ( ! is_array( $calculated ) || ! isset( $calculated['cost'] ) ) {
				throw new Exception( __( 'Calculated shipping data format is invalid', 'wcsdm' ) );
			}

			$calculated = wp_parse_args(
				$calculated,
				array(
					'id'        => $this->get_rate_id(),
					'label'     => $this->title,
					'package'   => $package,
					'meta_data' => array( 'api_response' => $api_response ),
				)
			);

			// Show the distance info.
			if ( 'yes' === $this->show_distance && ! empty( $api_response['distance_text'] ) ) {
				$calculated['label'] = sprintf( '%s (%s)', $calculated['label'], $api_response['distance_text'] );
			}

			// Register shipping rate to cart.
			$this->add_rate( $calculated );
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
		 *      add_filter( 'wcsdm_calculate_shipping_cost_pre', 'my_wcsdm_calculate_shipping_cost_pre', 10, 4 );
		 *
		 *      function my_wcsdm_calculate_shipping_cost_pre( $false, $api_response, $package, $obj ) {
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

		/**
		 * Developers can modify the $table_rates data via filter hooks.
		 *
		 * @since 2.1.0
		 *
		 * This example shows how you can modify the $table_rates var via custom function:
		 *
		 *      add_filter( 'wcsdm_table_rates', 'my_wcsdm_table_rates', 10, 4 );
		 *
		 *      function my_wcsdm_table_rates( $table_rates, $api_response, $package, $object ) {
		 *          // Return the table rates data array
		 *          return array(
		 *              array(
		 *                  'max_distance' => '1',
		 *                  'min_order_quantity' => '0',
		 *                  'max_order_quantity' => '0',
		 *                  'min_order_amount' => '0',
		 *                  'max_order_amount' => '0',
		 *                  'rate_class_0' => '10',
		 *                  'min_cost' => '',
		 *                  'surcharge' => '',
		 *                  'total_cost_type' => 'inherit',
		 *                  'title' => '',
		 *              ),
		 *              array(
		 *                  'max_distance' => '1000',
		 *                  'min_order_quantity' => '0',
		 *                  'max_order_quantity' => '0',
		 *                  'min_order_amount' => '0',
		 *                  'max_order_amount' => '0',
		 *                  'rate_class_0' => '5',
		 *                  'min_cost' => '',
		 *                  'surcharge' => '',
		 *                  'total_cost_type' => 'inherit',
		 *                  'title' => '',
		 *              )
		 *          );
		 *      }
		 */
		$table_rates = apply_filters( 'wcsdm_table_rates', $this->table_rates, $api_response, $package, $this );

		if ( $table_rates ) {
			$table_rates_match = array();

			foreach ( $table_rates as $index => $rate ) {
				/**
				 * Developers can modify the $rate data via filter hooks.
				 *
				 * @since 2.1.0
				 *
				 * This example shows how you can modify the $rate var via custom function:
				 *
				 *      add_filter( 'wcsdm_table_rates_row', 'my_wcsdm_table_rates_row', 10, 5 );
				 *
				 *      function my_wcsdm_table_rates_row( $rate, $index, $api_response, $package, $object ) {
				 *          // Return the rate row data array
				 *          return array(
				 *              'max_distance' => '8',
				 *              'min_order_quantity' => '0',
				 *              'max_order_quantity' => '0',
				 *              'min_order_amount' => '0',
				 *              'max_order_amount' => '0',
				 *              'rate_class_0' => '1000',
				 *              'min_cost' => '',
				 *              'surcharge' => '',
				 *              'total_cost_type' => 'inherit',
				 *              'title' => '',
				 *          );
				 *      }
				 */
				$rate = apply_filters( 'wcsdm_table_rates_row', $rate, $index, $api_response, $package, $this );

				$rate = $this->norlmalize_table_rate_row( $rate );

				if ( ! $rate ) {
					continue;
				}

				if ( $this->table_rate_row_rules_match( $rate, 0, $api_response, $package ) ) {
					if ( ! isset( $table_rates_match[ $rate['max_distance'] ] ) ) {
						$table_rates_match[ $rate['max_distance'] ] = array();
					}

					$table_rates_match[ $rate['max_distance'] ][] = $rate;
				}
			}

			if ( $table_rates_match ) {
				ksort( $table_rates_match, SORT_NUMERIC );

				// Pick the lowest max distance rate row rules.
				$table_rates_match = reset( $table_rates_match );

				// Pick first rate row data.
				$rate = reset( $table_rates_match );

				$this->show_debug( __( 'Table Rate Row match', 'wcsdm' ) . ': ' . wp_json_encode( $rate ) );

				// Hold costs data for flat total_cost_type.
				$flat = array();

				// Hold costs data for progressive total_cost_type.
				$progressive = array();

				foreach ( $package['contents'] as $hash => $item ) {
					if ( ! $item['data']->needs_shipping() ) {
						continue;
					}

					$class_id   = $item['data']->get_shipping_class_id();
					$product_id = $item['data']->get_id();

					$item_cost = $this->get_rate_field_value( 'rate_class_0', $rate, 0 );

					if ( $class_id ) {
						$class_cost = $this->get_rate_field_value( 'rate_class_' . $class_id, $rate );
						if ( strlen( $class_cost ) ) {
							$item_cost = $class_cost;
						}
					}

					// Multiply shipping cost with distance unit.
					$item_cost *= $api_response['distance'];

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

				$total_cost_type = $this->get_rate_field_value( 'total_cost_type', $rate, 'inherit' );
				if ( 'inherit' === $total_cost_type ) {
					$total_cost_type = $this->total_cost_type;
				}

				if ( strpos( $total_cost_type, 'flat__' ) === 0 ) {
					switch ( str_replace( 'flat__', '', $total_cost_type ) ) {
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
				} elseif ( strpos( $total_cost_type, 'progressive__' ) === 0 ) {
					switch ( str_replace( 'progressive__', '', $total_cost_type ) ) {
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

				$min_cost = $this->get_rate_field_value( 'min_cost', $rate, '' );

				if ( ! strlen( $min_cost ) ) {
					$min_cost = $this->min_cost;
				}

				if ( $min_cost && $min_cost > $cost ) {
					$cost = $min_cost;
				}

				$surcharge = $this->get_rate_field_value( 'surcharge', $rate, '' );

				if ( ! strlen( $surcharge ) ) {
					$surcharge = $this->surcharge;
				}

				if ( $surcharge ) {
					$surcharge_type = $this->get_rate_field_value( 'surcharge_type', $rate );

					if ( ! $surcharge_type || 'inherit' === $surcharge_type ) {
						$surcharge_type = $this->surcharge_type;
					}

					if ( ! $surcharge_type ) {
						$surcharge_type = 'fixed';
					}

					if ( 'fixed' === $surcharge_type ) {
						$cost += $surcharge;
					} else {
						$cost += ( ( $cost * $surcharge ) / 100 );
					}
				}

				$discount = $this->get_rate_field_value( 'discount', $rate, '' );

				if ( ! strlen( $discount ) ) {
					$discount = $this->discount;
				}

				if ( $discount ) {
					$discount_type = $this->get_rate_field_value( 'discount_type', $rate );

					if ( ! $discount_type || 'inherit' === $discount_type ) {
						$discount_type = $this->discount_type;
					}

					if ( ! $discount_type ) {
						$discount_type = 'fixed';
					}

					if ( 'fixed' === $discount_type ) {
						$cost -= $discount;
					} else {
						$cost -= ( ( $cost * $discount ) / 100 );
					}
				}

				$result = array(
					'cost'      => $cost,
					'label'     => empty( $rate['title'] ) ? $this->title : $rate['title'],
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
				 *      add_filter( 'wcsdm_calculate_shipping_cost', 'my_wcsdm_calculate_shipping_cost', 10, 4 );
				 *
				 *      function my_wcsdm_calculate_shipping_cost( $result, $api_response, $package, $obj ) {
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
		}

		return new WP_Error( 'no_table_rates_rules_match', __( 'No shipping table rates rules match.', 'wcsdm' ) );
	}

	/**
	 * Normalize table rate row data.
	 *
	 * @since 2.1.0
	 *
	 * @param array $rate Rate row data.
	 *
	 * @return array
	 */
	private function norlmalize_table_rate_row( $rate ) {
		if ( ! is_array( $rate ) ) {
			return false;
		}

		return wp_parse_args(
			$rate,
			array(
				'max_distance'       => '0',
				'min_order_quantity' => '0',
				'max_order_quantity' => '0',
				'min_order_amount'   => '0',
				'max_order_amount'   => '0',
				'rate_class_0'       => '',
				'min_cost'           => '',
				'surcharge'          => '',
				'total_cost_type'    => 'inherit',
				'title'              => '',
			)
		);
	}

	/**
	 * Check if table rate row rules is match
	 *
	 * @since 2.1.0
	 *
	 * @param array $rate Rate row data.
	 * @param int   $distance_offset Distance offset data.
	 * @param array $api_response API response data.
	 * @param array $package Cart data.
	 *
	 * @deprecated $distance_offset since 2.1.6
	 *
	 * @return bool
	 */
	private function table_rate_row_rules_match( $rate, $distance_offset, $api_response, $package ) {
		$is_match = $api_response['distance'] <= $rate['max_distance'];

		$min_order_amount = $this->get_rate_field_value( 'min_order_amount', $rate );

		if ( $is_match && $min_order_amount ) {
			$is_match = $min_order_amount <= $package['cart_subtotal'];
		}

		$max_order_amount = $this->get_rate_field_value( 'max_order_amount', $rate );

		if ( $is_match && $max_order_amount ) {
			$is_match = $max_order_amount >= $package['cart_subtotal'];
		}

		if ( $is_match && ( isset( $rate['min_order_quantity'] ) || isset( $rate['max_order_quantity'] ) ) ) {
			$cart_quantity = 0;

			foreach ( $package['contents'] as $item ) {
				if ( ! $item['data']->needs_shipping() ) {
					continue;
				}

				$cart_quantity += $item['quantity'];
			}

			$min_order_quantity = $this->get_rate_field_value( 'min_order_quantity', $rate );

			if ( $min_order_quantity ) {
				$is_match = $min_order_quantity <= $cart_quantity;
			}

			$max_order_quantity = $this->get_rate_field_value( 'max_order_quantity', $rate );

			if ( $max_order_quantity ) {
				$is_match = $max_order_quantity >= $cart_quantity;
			}
		}

		/**
		 * Developers can modify the rate row rules via filter hooks.
		 *
		 * @since 2.1.0
		 *
		 * This example shows how you can modify the rate row rules var via custom function:
		 *
		 *      add_filter( 'wcsdm_table_rate_row_rules_match', 'my_wcsdm_table_rate_row_rules_match', 10, 6 );
		 *
		 *      function my_wcsdm_table_rate_row_rules_match( $is_match, $rate, $distance_offset, $api_response, $package, $object ) {
		 *          return true;
		 *      }
		 */
		return apply_filters( 'wcsdm_table_rate_row_rules_match', $is_match, $rate, $distance_offset, $api_response, $package, $this );
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
		 *      add_filter( 'wcsdm_origin_info', 'my_wcsdm_origin_info', 10, 3 );
		 *
		 *      function my_wcsdm_origin_info( $origin_info, $package, $instance_id ) {
		 *          return array(
		 *               'origin_address' => '1600 Amphitheater Parkway,Mountain View,CA,94043',
		 *          );
		 *      }
		 */
		return apply_filters( 'wcsdm_origin_info', $origin_info, $package, $this->get_instance_id() );
	}

	/**
	 * Get shipping destination info
	 *
	 * @since    1.0.0
	 * @throws Exception Throw error if validation not passed.
	 * @param array $package The cart content data.
	 * @return string
	 */
	private function get_destination_info( $package ) {
		/**
		 * Developers can modify the $destination_info var via filter hooks.
		 *
		 * @since 2.0
		 *
		 * This example shows how you can modify the shipping destination info via custom function:
		 *
		 *      add_filter( 'wcsdm_destination_info_pre', 'my_wcsdm_destination_info_pre', 10, 3 );
		 *
		 *      function my_wcsdm_destination_info_pre( $false, $package, $instance_id ) {
		 *          return '1600 Amphitheater Parkway, Mountain View, CA, 94043, United State';
		 *      }
		 */
		$pre = apply_filters( 'wcsdm_destination_info_pre', false, $package, $this->get_instance_id() );

		if ( false !== $pre ) {
			return $pre;
		}

		$errors = array();

		$destination_info = array(
			'address_1' => false,
			'address_2' => false,
			'city'      => false,
			'state'     => false,
			'postcode'  => false,
			'country'   => false,
		);

		$shipping_fields = wcsdm_shipping_fields();

		if ( ! $shipping_fields ) {
			return '';
		}

		foreach ( $shipping_fields['data'] as $key => $field ) {
			$field_key = str_replace( $shipping_fields['type'] . '_', '', $key );

			if ( ! isset( $destination_info[ $field_key ] ) ) {
				continue;
			}

			if ( wcsdm_is_calc_shipping() && ! apply_filters( 'woocommerce_shipping_calculator_enable_' . $field_key, true ) ) { // phpcs:ignore WordPress.NamingConventions
				continue;
			}

			try {
				$required = isset( $field['required'] ) ? $field['required'] : false;
				$value    = isset( $package['destination'][ $field_key ] ) ? $package['destination'][ $field_key ] : '';

				if ( $required && ! $value ) {
					// translators: %s is field key.
					throw new Exception( sprintf( __( 'Shipping destination field is empty: %s', 'wcsdm' ), $field_key ) );
				}

				if ( $value && 'postcode' === $field_key && ! empty( $package['destination']['country'] ) ) {
					$country_code = $package['destination']['country'];

					if ( ! WC_Validation::is_postcode( $value, $country_code ) ) {
						// translators: %s is field key.
						throw new Exception( sprintf( __( 'Shipping destination field is invalid: %s', 'wcsdm' ), $field_key ) );
					}
				}

				if ( $value ) {
					$destination_info[ $field_key ] = $value;
				}
			} catch ( Exception $e ) {
				$errors[ $field_key ] = $e->getMessage();
			}
		}

		// Print debug.
		if ( $errors ) {
			foreach ( $errors as $key => $error ) {
				$this->show_debug( $error, 'error' );
			}

			$destination_info = array();
		}

		// Try to get full info for country and state.
		foreach ( $destination_info as $field_key => $value ) {
			if ( ! $value ) {
				continue;
			}

			if ( ! in_array( $field_key, array( 'country', 'state' ), true ) ) {
				continue;
			}

			if ( 'country' === $field_key ) {
				$countries = WC()->countries->countries;

				if ( $countries && is_array( $countries ) && isset( $countries[ $value ] ) ) {
					$value = $countries[ $value ];
				}
			}

			if ( 'state' === $field_key && ! empty( $package['destination']['country'] ) ) {
				$states = WC()->countries->states;

				if ( $states && is_array( $states ) && isset( $states[ $package['destination']['country'] ][ $value ] ) ) {
					$value = $states[ $package['destination']['country'] ][ $value ];
				}
			}

			$destination_info[ $field_key ] = $value;
		}

		// Format address.
		$destination_info = WC()->countries->get_formatted_address( $destination_info, ', ' );

		/**
		 * Developers can modify the $destination_info var via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the shipping destination info via custom function:
		 *
		 *      add_filter( 'wcsdm_destination_info', 'my_wcsdm_destination_info', 10, 3 );
		 *
		 *      function my_wcsdm_destination_info( $destination_info, $package, $instance_id ) {
		 *          return '1600 Amphitheater Parkway, Mountain View, CA, 94043, United State';
		 *      }
		 */
		return apply_filters( 'wcsdm_destination_info', $destination_info, $package, $this->get_instance_id() );
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
	 * Convert Meters to Kilometers
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

		$message = is_array( $message ) ? wp_json_encode( $message ) : $message;

		$debug_key = md5( $message );

		if ( isset( $this->debugs[ $debug_key ] ) ) {
			return;
		}

		$this->debugs[ $debug_key ] = $message;

		$debug_prefix = strtoupper( $this->id ) . '_' . $this->get_instance_id();

		if ( ! empty( $type ) ) {
			$debug_prefix .= '_' . strtoupper( $type );
		}

		wc_add_notice( $debug_prefix . ' => ' . $message, 'notice' );
	}
}
