<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @package    Wcsdm
 * @subpackage Legacy
 * @since      1.0.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
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
 * @since 1.0.0
 */
class Wcsdm_Legacy_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Rate fields data
	 *
	 * @since    2.0
	 * @var array
	 */
	private $instance_rate_fields = array();

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
		);

		$this->init();
		$this->migrate_data();
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
		$this->init_rate_fields(); // Init rate fields.
	}

	/**
	 * Data migration handler
	 *
	 * @since 2.1.10
	 *
	 * @return void
	 */
	private function migrate_data() {
		if ( ! $this->get_instance_id() ) {
			return;
		}

		$data_version_option_key = 'wcsdm_data_version_' . $this->get_instance_id();

		$data_version = get_option( $data_version_option_key );

		if ( $data_version && version_compare( WCSDM_DATA_VERSION, $data_version, '<=' ) ) {
			return;
		}

		$migrations = array();

		foreach ( glob( WCSDM_PATH . 'legacy/migrations/*.php' ) as $migration_file ) {
			$migration_class_name = wcsdm_convert_file_path_to_class_name( $migration_file );

			if ( isset( $migrations[ $migration_class_name ] ) ) {
				continue;
			}

			$migration = new $migration_class_name();

			if ( $data_version && version_compare( $migration->get_version(), $data_version, '<=' ) ) {
				continue;
			}

			$migrations[ $migration_class_name ] = $migration;
		}

		if ( $migrations ) {
			usort( $migrations, array( $this, 'sort_version' ) );

			if ( ! $this->instance_settings ) {
				$this->init_instance_settings();
			}

			foreach ( $migrations as $migration ) {
				$migration->set_instance( $this );

				$migration_update_options = $migration->get_update_options();
				$migration_delete_options = $migration->get_delete_options();

				// Update the settings data.
				if ( $migration_update_options || $migration_update_options ) {
					$instance_settings = $this->instance_settings;

					if ( $migration_update_options ) {
						foreach ( $migration_update_options as $key => $value ) {
							$instance_settings[ $key ] = $value;
						}
					}

					if ( $migration_delete_options ) {
						foreach ( $migration_delete_options as $key ) {
							unset( $instance_settings[ $key ] );
						}
					}

					if ( update_option( $this->get_instance_option_key(), $instance_settings, 'yes' ) ) {
						$this->init_instance_settings();
					}
				}

				// Update the latest version migrated option.
				update_option( $data_version_option_key, $$migration->get_version(), 'yes' );
			}
		}
	}

	/**
	 * Init form fields.
	 *
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$form_fields = array(
			'field_group_general'        => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group',
				'title'     => __( 'General Settings', 'wcsdm' ),
			),
			'tax_status'                 => array(
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
			'enable_log'                 => array(
				'title'       => __( 'Enable Log', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Write data to WooCommerce System Status Report Log for importance event such API response error and shipping calculation failures. <a href="admin.php?page=wc-status&tab=logs" target="_blank">Click here</a> to view the log data.', 'wcsdm' ),
				'desc_tip'    => false,
			),
			'field_group_store_location' => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'title',
				'class'       => 'wcsdm-field-group',
				'title'       => __( 'Store Location Settings', 'wcsdm' ),
				// translators: %s is URL to the external link.
				'description' => sprintf( __( 'This plugin requires Google API Key and also need to have the following APIs services enabled: Distance Matrix API, Maps JavaScript API, Geocoding API, Places API. Please <a href="%s" target="_blank">click here</a> to go to the Google API Console to create API Key and to enable the API services.', 'wcsdm' ), 'https://cloud.google.com/maps-platform/#get-started' ),
			),
			'api_key'                    => array(
				'title'           => __( 'Distance Calculator API Key', 'wcsdm' ),
				'type'            => 'wcsdm',
				'orig_type'       => 'api_key',
				'description'     => __( 'API Key used to calculate the shipping address distance.', 'wcsdm' ),
				'desc_tip'        => true,
				'default'         => '',
				'is_required'     => true,
				'api_services'    => array(
					'Distance Matrix API' => 'https://developers.google.com/maps/documentation/distance-matrix/overview',
				),
				'api_restriction' => array(
					'label' => 'IP addresses',
					'link'  => 'https://developers.google.com/maps/api-security-best-practices#restricting-api-keys',
				),
			),
			'api_key_picker'             => array(
				'title'           => __( 'Location Picker API Key', 'wcsdm' ),
				'type'            => 'wcsdm',
				'orig_type'       => 'api_key',
				'description'     => __( 'API Key used to render the location picker map.', 'wcsdm' ),
				'desc_tip'        => true,
				'default'         => '',
				'is_required'     => true,
				'api_services'    => array(
					'Maps JavaScript API' => 'https://developers.google.com/maps/documentation/javascript/overview',
					'Geocoding API'       => 'https://developers.google.com/maps/documentation/geocoding/overview',
					'Places API'          => 'https://developers.google.com/maps/documentation/places/web-service/overview',
				),
				'api_restriction' => array(
					'label' => 'Websites',
					'link'  => 'https://developers.google.com/maps/api-security-best-practices#restricting-api-keys',
				),
			),
			'origin_type'                => array(
				'title'             => __( 'Store Origin Data Type', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'select',
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
			'origin_lat'                 => array(
				'title'             => __( 'Store Location Latitude', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'origin_info',
				'description'       => __( 'Store location latitude coordinates', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '',
				'is_required'       => true,
				'class'             => 'wcsdm-input-has-action',
				'custom_attributes' => array(
					'readonly' => true,
				),
				'show_if'           => array(
					'origin_type' => 'coordinate',
				),
			),
			'origin_lng'                 => array(
				'title'             => __( 'Store Location Longitude', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'origin_info',
				'description'       => __( 'Store location longitude coordinates', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '',
				'is_required'       => true,
				'class'             => 'wcsdm-input-has-action',
				'custom_attributes' => array(
					'readonly' => true,
				),
				'show_if'           => array(
					'origin_type' => 'coordinate',
				),
			),
			'origin_address'             => array(
				'title'             => __( 'Store Location Address', 'wcsdm' ),
				'type'              => 'wcsdm',
				'orig_type'         => 'origin_info',
				'description'       => __( 'Store location full address', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '',
				'is_required'       => true,
				'class'             => 'wcsdm-input-has-action',
				'custom_attributes' => array(
					'readonly' => true,
				),
				'show_if'           => array(
					'origin_type' => 'address',
				),
			),
			'field_group_route'          => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group',
				'title'     => __( 'Route Settings', 'wcsdm' ),
			),
			'travel_mode'                => array(
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
			'route_restrictions'         => array(
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
			'preferred_route'            => array(
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
			'distance_unit'              => array(
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
							'label'     => array(
								'targets' => array(
									'#wcsdm-table--table_rates--dummy .wcsdm-col--rate_class_0 .label-text',
									'label[for="woocommerce_wcsdm_fake--field--rate_class_0"]',
									'#wcsdm-table--advanced_rate .wcsdm-field--context--advanced--section_shipping_rates',
								),
								'value'   => array(
									'metric'   => __( 'Rate per Kilometer', 'wcsdm' ),
									'imperial' => __( 'Rate per Mile', 'wcsdm' ),
								),
							),
							'attribute' => array(
								'targets' => array(
									'.wcsdm-field-wrapper--max_distance',
								),
								'value'   => array(
									'metric'   => 'km',
									'imperial' => 'mi',
								),
							),
						)
					),
				),
			),
			'round_up_distance'          => array(
				'title'       => __( 'Round Up Distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Round up the calculated shipping distance with decimal to the nearest absolute number.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'show_distance'              => array(
				'title'       => __( 'Show Distance Info', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'wcsdm',
				'orig_type'   => 'checkbox',
				'description' => __( 'Show the distance info to customer during checkout.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'field_group_total_cost'     => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'title',
				'class'       => 'wcsdm-field-group',
				'title'       => __( 'Default Rates Settings', 'wcsdm' ),
				'description' => __( 'Default settings that will be inherited by certain settings in table rates when the value is empty.', 'wcsdm' ),
			),
			'title'                      => array(
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
						'is_dummy'    => false,
						'is_hidden'   => true,
						'is_required' => false,
					),
				),
			),
			'surcharge_type'             => array(
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
					'insert_after' => 'section_total_cost',
					'attrs'        => array(
						'is_advanced' => true,
						'is_hidden'   => true,
					),
				),
			),
			'surcharge'                  => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'price',
				'title'             => __( 'Surcharge Amount', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Surcharge amount that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'surcharge_type',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => false,
						'is_hidden'   => true,
						'title'       => __( 'Surcharge', 'wcsdm' ),
					),
				),
				'hide_if'           => array(
					'surcharge_type' => 'none',
				),
			),
			'discount_type'              => array(
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
			'discount'                   => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'price',
				'title'             => __( 'Discount Amount', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Discount amount that will be deducted to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'discount_type',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => false,
						'is_hidden'   => true,
						'title'       => __( 'Discount', 'wcsdm' ),
					),
				),
				'hide_if'           => array(
					'discount_type' => 'none',
				),
			),
			'total_cost_type'            => array(
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
			'min_cost'                   => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'price',
				'title'             => __( 'Minimum Cost', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Minimum cost that will be applied. The calculated shipping cost will never be lower than whatever amount set into this field. Set as zero value to disable.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'total_cost_type',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => false,
						'is_hidden'   => true,
					),
				),
			),
			'max_cost'                   => array(
				'type'              => 'wcsdm',
				'orig_type'         => 'price',
				'title'             => __( 'Maximum Cost', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Maximum cost that will be applied. The calculated shipping cost will never be greater than whatever amount set into this field. Set as zero value to disable.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_required'       => true,
				'custom_attributes' => array(
					'min' => '0',
				),
				'table_rate'        => array(
					'insert_after' => 'min_cost',
					'attrs'        => array(
						'default'     => '',
						'is_required' => false,
						'is_advanced' => true,
						'is_dummy'    => false,
						'is_hidden'   => true,
					),
				),
			),
			'field_group_table_rates'    => array(
				'type'        => 'wcsdm',
				'orig_type'   => 'title',
				'class'       => 'wcsdm-field-group',
				'title'       => __( 'Table Rates Settings', 'wcsdm' ),
				'description' => __( 'Calculate shipping costs based on the distance to the shipping address and advanced rules. During checkout, the applicable rate is determined by the first row that matches the maximum distance rule and any defined advanced rules. You can manually prioritize rate rows by dragging them vertically when there are rows with the same maximum distance values.', 'wcsdm' ),
			),
			'table_rates'                => array(
				'type'  => 'table_rates',
				'title' => __( 'Table Rates Settings', 'wcsdm' ),
			),
			'field_group_advanced_rate'  => array(
				'type'      => 'wcsdm',
				'orig_type' => 'title',
				'class'     => 'wcsdm-field-group wcsdm-field-group-hidden',
				'title'     => __( 'Advanced Rate Settings', 'wcsdm' ),
			),
			'advanced_rate'              => array(
				'type'  => 'advanced_rate',
				'title' => __( 'Advanced Table Rate Settings', 'wcsdm' ),
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
			'select_item'            => array(
				'type'     => 'select_item',
				'is_dummy' => true,
			),
			'section_general'        => array(
				'type'        => 'title',
				'title'       => __( 'General', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'section_shipping_rules' => array(
				'type'        => 'title',
				'title'       => __( 'Shipping Rules', 'wcsdm' ),
				'is_advanced' => true,
				'is_dummy'    => false,
				'is_hidden'   => false,
			),
			'row_number'             => array(
				'type'     => 'row_number',
				'title'    => '#',
				'is_dummy' => true,
			),
			'max_distance'           => array(
				'type'              => 'decimal',
				'title'             => __( 'Max Distance', 'wcsdm' ),
				'description'       => __( 'The maximum distances rule for the shipping rate. This input is required.', 'wcsdm' ),
				'desc_tip'          => true,
				'default'           => '10',
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'min_order_amount'       => array(
				'type'              => 'price',
				'title'             => __( 'Min Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order amount. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'max_order_amount'       => array(
				'type'              => 'price',
				'title'             => __( 'Max Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order amount. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'min_order_quantity'     => array(
				'type'              => 'decimal',
				'title'             => __( 'Min Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order quantity. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			'max_order_quantity'     => array(
				'type'              => 'decimal',
				'title'             => __( 'Max Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order quantity. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => false,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rule'           => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
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
				'type'              => 'price',
				'title'             => __( 'Distance Unit Rate', 'wcsdm' ),
				'description'       => __( 'The shipping rate within the distances range. Zero value will be assumed as free shipping.', 'wcsdm' ),
				'desc_tip'          => true,
				'is_advanced'       => true,
				'is_dummy'          => true,
				'is_hidden'         => true,
				'is_required'       => true,
				'is_rate'           => true,
				'is_rule'           => false,
				'default'           => '0',
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
			'show_advanced_rate'     => array(
				'type'              => 'action_link',
				'icon'              => 'dashicons dashicons-admin-generic',
				'class'             => 'wcsdm-link wcsdm-link--advanced-rate',
				'custom_attributes' => array(
					'title' => __( 'Advanced', 'wcsdm' ),
				),
				'is_advanced'       => false,
				'is_dummy'          => true,
				'is_hidden'         => false,
			),
			'sort_rate'              => array(
				'type'        => 'action_link',
				'title'       => '',
				'icon'        => 'dashicons dashicons-move',
				'class'       => 'wcsdm-link wcsdm-link--sort',
				'is_advanced' => false,
				'is_dummy'    => true,
				'is_hidden'   => false,
			),
		);

		$shipping_classes = array();

		foreach ( WC()->shipping->get_shipping_classes() as $shipping_classes_value ) {
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
								'inherit' => __( 'Inherit - Use default rate settings', 'wcsdm' ),
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
	 * Get API Key for the API request
	 *
	 * @since 2.0.8
	 *
	 * @return string
	 */
	private function api_request_key() {
		return $this->get_option( 'api_key' );
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @param array   $args Custom arguments for $settings and $package data.
	 * @param boolean $cache Is use the cached data.
	 * @throws Exception If error happen.
	 * @return (array|WP_Error) WP_Error on failure.
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
		 *          );
		 *      }
		 */
		$pre = apply_filters( 'wcsdm_api_request_pre', false, $args, $cache, $this );

		if ( false !== $pre ) {
			return $pre;
		}

		$args_default = array(
			'origin'      => array(),
			'destination' => array(),
			'settings'    => $this->instance_settings,
			'package'     => array(),
		);

		$args = wp_parse_args( $args, $args_default );

		$errors = new WP_Error();

		foreach ( array_keys( $args_default ) as $args_key ) {
			if ( empty( $args[ $args_key ] ) ) {
				$errors->add(
					'wcsdm_api_request_empty_' . $args_key,
					// translators: %s is api request parameter key.
					wp_sprintf( __( 'API request parameter is empty: %s', 'wcsdm' ), $args_key )
				);
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		if ( $cache && ! $this->is_debug_mode() ) {
			$cache_key = $this->autoprefixer( 'api_request_' . md5( wp_json_encode( $args ) ) );

			// Check if the data already cached and return it.
			$cached_data = get_transient( $cache_key );

			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		$api_request_data = array(
			'origins'      => $args['origin'],
			'destinations' => $args['destination'],
			'language'     => get_locale(),
			'key'          => $this->api_request_key(),
		);

		foreach ( $this->instance_form_fields as $key => $field ) {
			if ( ! isset( $field['api_request'] ) ) {
				continue;
			}

			$api_request_data[ $field['api_request'] ] = isset( $args['settings'][ $key ] ) ? $args['settings'][ $key ] : '';
		}

		$results = Wcsdm_Legacy_API::calculate_distance( $api_request_data );

		if ( is_wp_error( $results ) ) {
			return $this->maybe_write_log( 'error', $results->get_error_message() );
		}

		// Sort the results.
		if ( count( $results ) > 1 ) {
			if ( is_callable( array( $this, $args['settings']['preferred_route'] . '_results' ) ) ) {
				usort( $results, array( $this, $args['settings']['preferred_route'] . '_results' ) );
			} else {
				usort( $results, array( $this, 'shortest_distance_results' ) );
			}
		}

		$result = array();

		foreach ( $results[0] as $key => $value ) {
			if ( 'distance' === $key ) {
				$value = $this->convert_distance( $value );

				if ( 'yes' === $args['settings']['round_up_distance'] ) {
					$value = ceil( $value );
				}
			}

			$result[ $key ] = $value;

			if ( is_callable( array( $this, 'get_text_of_' . $key ) ) ) {
				$result[ $key . '_text' ] = call_user_func( array( $this, 'get_text_of_' . $key ), $value );
			}
		}

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
		 *              'distance'      => 40,
		 *              'distance_text' => '40 km',
		 *              'duration'      => 3593,
		 *              'duration_text' => '1 hour 5 mins',
		 *          );
		 *      }
		 */
		$result = apply_filters( 'wcsdm_api_request', $result, $this );

		if ( $result && $cache && ! $this->is_debug_mode() ) {
			set_transient( $cache_key, $result, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.
		}

		return $result;
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
		$api_response = $this->api_request(
			array(
				'origin'      => $this->get_origin_info( $package ),
				'destination' => $this->get_destination_info( $package ),
				'package'     => $package,
			)
		);

		try {
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
			$show_distance = $this->get_option( 'show_distance', 'no' );

			if ( 'yes' === $show_distance && ! empty( $api_response['distance_text'] ) ) {
				$calculated['label'] = sprintf( '%s (%s)', $calculated['label'], $api_response['distance_text'] );
			}

			// Register shipping rate to cart.
			$this->add_rate( $calculated );
		} catch ( Exception $e ) {
			$this->maybe_write_log( 'error', $e->getMessage() );
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
		 *                  'max_cost' => '',
		 *                  'surcharge' => '',
		 *                  'discount' => '',
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
		 *                  'max_cost' => '',
		 *                  'surcharge' => '',
		 *                  'discount' => '',
		 *                  'total_cost_type' => 'inherit',
		 *                  'title' => '',
		 *              )
		 *          );
		 *      }
		 */
		$table_rates = apply_filters( 'wcsdm_table_rates', $this->get_option( 'table_rates', array() ), $api_response, $package, $this );

		if ( $table_rates && is_array( $table_rates ) ) {
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
				 *              'max_cost' => '',
				 *              'surcharge' => '',
				 *              'discount' => '',
				 *              'total_cost_type' => 'inherit',
				 *              'title' => '',
				 *          );
				 *      }
				 */
				$rate = apply_filters( 'wcsdm_table_rates_row', $rate, $index, $api_response, $package, $this );

				$rate = $this->normalize_table_rate_row( $rate );

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
				if ( count( $table_rates_match ) > 1 ) {
					ksort( $table_rates_match, SORT_NUMERIC );
				}

				// Pick the lowest max distance rate row rules.
				$table_rates_match = reset( $table_rates_match );

				// Pick first rate row data.
				$rate = reset( $table_rates_match );

				// Hold costs data for flat total_cost_type.
				$flat = array();

				// Hold costs data for progressive total_cost_type.
				$progressive = array();

				foreach ( $package['contents'] as $item ) {
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

					if ( is_string( $item_cost ) ) {
						$item_cost = floatVal( $item_cost );
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

				$total_cost_type = $this->get_rate_field_value( 'total_cost_type', $rate, 'inherit' );

				if ( 'inherit' === $total_cost_type ) {
					$total_cost_type = $this->get_option( 'total_cost_type', 'flat__highest' );
				}

				if ( strpos( $total_cost_type, 'flat__' ) === 0 ) {
					switch ( $total_cost_type ) {
						case 'flat__lowest':
							$cost = min( $flat );
							break;

						case 'flat__average':
							$cost = ( array_sum( $flat ) / count( $flat ) );
							break;

						default:
							$cost = max( $flat );
							break;
					}
				} elseif ( strpos( $total_cost_type, 'progressive__' ) === 0 ) {
					switch ( $total_cost_type ) {
						case 'progressive__per_shipping_class':
							$costs = array();

							foreach ( $progressive as $value ) {
								$costs[ $value['class_id'] ] = $value['item_cost'];
							}

							$cost = array_sum( $costs );
							break;

						case 'progressive__per_product':
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

				$surcharge = $this->get_rate_field_value( 'surcharge', $rate, '' );

				if ( ! strlen( $surcharge ) ) {
					$surcharge = $this->get_option( 'surcharge', '' );
				}

				if ( $surcharge ) {
					$surcharge_type = $this->get_rate_field_value( 'surcharge_type', $rate, 'inherit' );

					if ( ! $surcharge_type || 'inherit' === $surcharge_type ) {
						$surcharge_type = $this->get_option( 'surcharge_type', 'fixed' );
					}

					if ( ! $surcharge_type ) {
						$surcharge_type = 'fixed';
					}

					if ( 'fixed' === $surcharge_type ) {
						$cost += $surcharge;
					} elseif ( 'percent' === $surcharge_type ) {
						$cost += ( ( $cost * $surcharge ) / 100 );
					}
				}

				$discount = $this->get_rate_field_value( 'discount', $rate, '' );

				if ( ! strlen( $discount ) ) {
					$discount = $this->get_option( 'discount', '' );
				}

				if ( $discount ) {
					$discount_type = $this->get_rate_field_value( 'discount_type', $rate, 'inherit' );

					if ( ! $discount_type || 'inherit' === $discount_type ) {
						$discount_type = $this->get_option( 'discount_type', 'fixed' );
					}

					if ( ! $discount_type ) {
						$discount_type = 'fixed';
					}

					if ( 'fixed' === $discount_type ) {
						$cost -= $discount;
					} elseif ( 'percent' === $discount_type ) {
						$cost -= ( ( $cost * $discount ) / 100 );
					}
				}

				$min_cost = $this->get_rate_field_value( 'min_cost', $rate, '' );

				if ( ! strlen( $min_cost ) ) {
					$min_cost = $this->get_option( 'min_cost', '' );
				}

				if ( $min_cost && $min_cost > $cost ) {
					$cost = $min_cost;
				}

				$max_cost = $this->get_rate_field_value( 'max_cost', $rate, '' );

				if ( ! strlen( $max_cost ) ) {
					$max_cost = $this->get_option( 'max_cost', '' );
				}

				if ( $max_cost && $max_cost < $cost ) {
					$cost = $max_cost;
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

		return new WP_Error(
			'rates_rules_not_match',
			__( 'No shipping table rates rules match.', 'wcsdm' ),
			array(
				'api_response' => $api_response,
				'table_rates'  => $table_rates,
				'package'      => $package,
			)
		);
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
	private function normalize_table_rate_row( $rate ) {
		if ( ! is_array( $rate ) ) {
			return false;
		}

		$rate = wp_parse_args(
			$rate,
			array(
				'max_distance'       => '0',
				'min_order_quantity' => '0',
				'max_order_quantity' => '0',
				'min_order_amount'   => '0',
				'max_order_amount'   => '0',
				'rate_class_0'       => '',
				'min_cost'           => '',
				'max_cost'           => '',
				'surcharge_type'     => 'fixed',
				'surcharge'          => '',
				'discount_type'      => 'fixed',
				'discount'           => '',
				'total_cost_type'    => 'inherit',
				'title'              => '',
			)
		);

		return $rate;
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

		switch ( $this->get_option( 'origin_type', 'address' ) ) {
			case 'coordinate':
				$origin_lat = $this->get_option( 'origin_lat', '' );
				$origin_lng = $this->get_option( 'origin_lng', '' );

				if ( ! empty( $origin_lat ) && ! empty( $origin_lng ) ) {
					$origin_info['origin_lat'] = $origin_lat;
					$origin_info['origin_lng'] = $origin_lng;
				}
				break;

			default:
				$origin_address = $this->get_option( 'origin_address', '' );

				if ( ! empty( $origin_address ) ) {
					$origin_info['origin_address'] = $origin_address;
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

		$destination_info = array();

		foreach ( wcsdm_include_address_fields() as $key ) {
			$destination_info[ $key ] = false;
		}

		$post_data = array();

		if ( ! empty( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security
			parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore WordPress.Security
		} else {
			// Support Old Versions.
			$post_data = $_POST; // phpcs:ignore WordPress.Security
		}

		if ( wcsdm_is_calc_shipping() ) {
			$shipping_fields = wcsdm_calc_shipping_fields();
		} elseif ( ! empty( $post_data['ship_to_different_address'] ) ) {
			$shipping_fields = wcsdm_shipping_fields();
		} else {
			$shipping_fields = wcsdm_billing_fields();
		}

		if ( ! $shipping_fields ) {
			return '';
		}

		foreach ( $shipping_fields as $field_key => $field ) {
			if ( ! isset( $destination_info[ $field_key ] ) ) {
				continue;
			}

			try {
				$required = isset( $field['required'] ) ? $field['required'] : false;
				$value    = isset( $package['destination'][ $field_key ] ) ? $package['destination'][ $field_key ] : '';

				if ( $required && ! $value ) {
					// translators: %s is field key.
					throw new Exception( sprintf( __( 'Shipping destination field is empty: %s.', 'wcsdm' ), $field_key ) );
				}

				if ( $value && 'postcode' === $field_key && ! empty( $package['destination']['country'] ) ) {
					$country_code = $package['destination']['country'];

					if ( ! WC_Validation::is_postcode( $value, $country_code ) ) {
						// translators: %s is field key.
						throw new Exception( sprintf( __( 'Shipping destination field is invalid: %s.', 'wcsdm' ), $field_key ) );
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
	 * @return float
	 */
	public function convert_distance( $meters ) {
		if ( 'imperial' === $this->get_option( 'distance_unit', 'metric' ) ) {
			return $this->convert_distance_to_mi( $meters );
		}

		return $this->convert_distance_to_km( $meters );
	}

	/**
	 * Convert Meters to Miles
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return float
	 */
	public function convert_distance_to_mi( $meters ) {
		return floatVal( ( $meters * 0.000621371 ) );
	}

	/**
	 * Convert Meters to Kilometers
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return float
	 */
	public function convert_distance_to_km( $meters ) {
		return floatVal( ( $meters * 0.001 ) );
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
		if ( $a['distance'] === $b['distance'] ) {
			return 0;
		}

		return ( $a['distance'] < $b['distance'] ) ? -1 : 1;
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
		if ( $a['distance'] === $b['distance'] ) {
			return 0;
		}

		return ( $a['distance'] > $b['distance'] ) ? -1 : 1;
	}

	/**
	 * Sort migration by version.
	 *
	 * @since 2.1.10
	 *
	 * @param Wcsdm_Migration $a Object 1 migration handler.
	 * @param Wcsdm_Migration $b Object 2 migration handler.
	 * @return int
	 */
	public function sort_version( $a, $b ) {
		return version_compare( $a::get_version(), $b::get_version(), '<=' ) ? -1 : 1;
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
	 * Auto prefixer a suffix with method ID and instance ID.
	 *
	 * @param mixed $suffix Suffing that will prefixed.
	 *
	 * @return string
	 */
	protected function autoprefixer( $suffix ) {
		return $this->id . '_' . $this->get_instance_id() . '_' . trim( $suffix, '_' );
	}

	/**
	 * Get text of formated distance.
	 *
	 * @param float $distance Distance in km/mi unit.
	 *
	 * @return string
	 */
	protected function get_text_of_distance( $distance ) {
		$distance_formatted = wc_format_decimal( $distance, 1, true );

		if ( $distance_formatted ) {
			$distance = $distance_formatted;
		}

		if ( 'imperial' === $this->get_option( 'distance_unit' ) ) {
			return $distance . ' mi';
		}

		return $distance . ' km';
	}

	/**
	 * Get text of formated duration.
	 *
	 * @param float $duration Duration in seconds unit.
	 *
	 * @return string
	 */
	protected function get_text_of_duration( $duration ) {
		$texts = array();

		/*** Days */
		$days = intval( intval( $duration ) / ( 3600 * 24 ) );

		if ( $days > 0 ) {
			// translators: %s is number of days.
			$texts[] = sprintf( _n( '%s day', '%s days', $days, 'wcsdm' ), number_format_i18n( $days ) );
		}

		/*** Hours */
		$hours = ( intval( $duration ) / 3600 ) % 24;

		if ( $hours > 0 ) {
			// translators: %s is number of hours.
			$texts[] = sprintf( _n( '%s hour', '%s hours', $hours, 'wcsdm' ), number_format_i18n( $hours ) );
		}

		/*** Minutes */
		$minutes = ( intval( $duration ) / 60 ) % 60;

		if ( $minutes > 0 ) {
			// translators: %s is number of minutes.
			$texts[] = sprintf( _n( '%s minute', '%s minutes', $minutes, 'wcsdm' ), number_format_i18n( $minutes ) );
		}

		return implode( ' ', $texts );
	}

	/**
	 * Write a log entry if logging is enabled.
	 *
	 * Conditionally logs messages based on the instance's 'enable_log' setting.
	 * Uses WooCommerce's logger with the shipping method instance ID as the source.
	 *
	 * @since 1.0.0
	 *
	 * @param string $level   The log level. Accepts 'error', 'info', or any valid WC_Logger level.
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function maybe_write_log( string $level, string $message ) {
		// Check if logging is enabled via global debug mode or instance-level setting.
		if ( 'yes' !== $this->get_option( 'enable_log' ) ) {
			return;
		}

		$context = array(
			'source' => $this->id . '--legacy--' . $this->get_instance_id(),
		);

		switch ( $level ) {
			case 'error':
				// Write the log entry using WooCommerce's logger.
				wc_get_logger()->error(
					$message,
					$context
				);
				break;

			case 'info':
				// Write the log entry using WooCommerce's logger.
				wc_get_logger()->info(
					$message,
					$context
				);
				break;

			default:
				// Write the log entry using WooCommerce's logger.
				wc_get_logger()->log(
					$level,
					$message,
					$context
				);
				break;
		}
	}
}
