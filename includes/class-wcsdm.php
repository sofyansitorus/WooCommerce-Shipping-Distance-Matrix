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
	private $google_api_url = 'https://maps.googleapis.com/maps/api/distancematrix/json';

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

		// Description shown in admin.
		$this->method_description = __( 'Shipping rates calculator based on products shipping class and route distances.', 'wcsdm' );

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
		// Load the settings API.
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->gmaps_api_key            = $this->get_option( 'gmaps_api_key' );
		$this->origin_lat               = $this->get_option( 'origin_lat' );
		$this->origin_lng               = $this->get_option( 'origin_lng' );
		$this->gmaps_api_units          = $this->get_option( 'gmaps_api_units', 'metric' );
		$this->gmaps_api_mode           = $this->get_option( 'gmaps_api_mode', 'driving' );
		$this->gmaps_api_avoid          = $this->get_option( 'gmaps_api_avoid' );
		$this->calc_type                = $this->get_option( 'calc_type', 'per_item' );
		$this->charge_per_distance_unit = $this->get_option( 'charge_per_distance_unit', 'no' );
		$this->enable_fallback_request  = $this->get_option( 'enable_fallback_request', 'no' );
		$this->show_distance            = $this->get_option( 'show_distance' );
		$this->ceil_distance            = $this->get_option( 'ceil_distance', 'no' );
		$this->table_rates              = $this->get_option( 'table_rates' );
		$this->tax_status               = $this->get_option( 'tax_status' );

		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		// Check if this shipping method is availbale for current order.
		add_filter( 'woocommerce_shipping_' . $this->id . '_is_available', array( $this, 'check_is_available' ), 10, 2 );
	}

	/**
	 * Init form fields.
	 *
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'                    => array(
				'title'       => __( 'Title', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'tax_status'               => array(
				'title'   => __( 'Tax status', 'wcsdm' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'gmaps_api_key'            => array(
				'title'       => __( 'API Key', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( 'This plugin require Google Maps Distance Matrix API Key and service is enabled. <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> to go to Google API Console to get API Key and to enable the service.', 'wcsdm' ),
				'default'     => '',
			),
			'origin'                   => array(
				'title'       => __( 'Store Location', 'wcsdm' ),
				'type'        => 'address_picker',
				'description' => __( '<a href="http://www.latlong.net/" target="_blank">Click here</a> to get your store location coordinates info.', 'wcsdm' ),
			),
			'origin_lat'               => array(
				'title' => __( 'Store Location Latitude', 'wcsdm' ),
				'type'  => 'coordinates',
			),
			'origin_lng'               => array(
				'title' => __( 'Store Location Logitude', 'wcsdm' ),
				'type'  => 'coordinates',
			),
			'gmaps_api_mode'           => array(
				'title'       => __( 'Travel Mode', 'wcsdm' ),
				'type'        => 'select',
				'description' => __( 'Google Maps Distance Matrix API travel mode parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'driving',
				'options'     => array(
					'driving'   => __( 'Driving', 'wcsdm' ),
					'walking'   => __( 'Walking', 'wcsdm' ),
					'bicycling' => __( 'Bicycling', 'wcsdm' ),
				),
			),
			'gmaps_api_avoid'          => array(
				'title'       => __( 'Restrictions', 'wcsdm' ),
				'type'        => 'select',
				'description' => __( 'Google Maps Distance Matrix API restrictions parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'driving',
				'options'     => array(
					''         => __( 'None', 'wcsdm' ),
					'tolls'    => __( 'Avoid Tolls', 'wcsdm' ),
					'highways' => __( 'Avoid Highways', 'wcsdm' ),
					'ferries'  => __( 'Avoid Ferries', 'wcsdm' ),
					'indoor'   => __( 'Avoid Indoor', 'wcsdm' ),
				),
			),
			'gmaps_api_units'          => array(
				'title'       => __( 'Distance Units', 'wcsdm' ),
				'type'        => 'select',
				'description' => __( 'Google Maps Distance Matrix API distance units parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'metric',
				'options'     => array(
					'metric'   => __( 'Kilometers', 'wcsdm' ),
					'imperial' => __( 'Miles', 'wcsdm' ),
				),
			),
			'show_distance'            => array(
				'title'       => __( 'Show distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'Show the distance info to customer during checkout.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'ceil_distance'            => array(
				'title'       => __( 'Round Distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'Round distance UP to the nearest integer.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'enable_fallback_request'  => array(
				'title'       => __( 'Enable Fallback Request', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'If there is no results for API request using full address, the system will attempt to make another API request to the Google API server without "Address Line 1" parameter. The fallback request will only using "Address Line 2", "City", "State/Province", "Postal Code" and "Country" parameters.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'calc_type'                => array(
				'title'       => __( 'Calculation type', 'wcsdm' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => 'per_item',
				'options'     => array(
					'per_item'           => __( 'Per item', 'wcsdm' ),
					'per_product'        => __( 'Per product', 'wcsdm' ),
					'per_shipping_class' => __( 'Per shipping class', 'wcsdm' ),
					'per_order'          => __( 'Per order', 'wcsdm' ),
				),
				'description' => __( '<strong>Per item</strong>: Charge shipping for each items multiplied with quantity.<br><strong>Per product</strong>: Charge shipping grouped by product.<br><strong>Per shipping class</strong>: Charge shipping grouped by product shipping class.<br><strong>Per order</strong>: Charge shipping for the most expensive item shipping cost.', 'wcsdm' ),
			),
			'charge_per_distance_unit' => array(
				'title'       => __( 'Charge per ', 'wcsdm' ) . '<span id="per_distance_unit_selected"></span>',
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'Charge customer based on shipping distance multiplied with shipping class rate defined. Example: If the rate defined is $4 and the shipping distance is 7 miles, the shipping cost will be $28.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'table_rates'              => array(
				'type' => 'table_rates',
			),
		);
	}


	/**
	 * Generate origin settings field.
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_address_picker_html( $key, $data ) {
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

		ob_start(); ?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php echo esc_html( $this->get_tooltip_html( $data ) ); ?>
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<input type="hidden" id="map-secret-key" value="<?php echo esc_attr( WCSDM_MAP_SECRET_KEY ); ?>">
				<div id="<?php echo esc_attr( $this->id ); ?>-map-wrapper" class="<?php echo esc_attr( $this->id ); ?>-map-wrapper"></div>
				<div id="<?php echo esc_attr( $this->id ); ?>-lat-lng-wrap">
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lat"><?php echo esc_html( 'Latitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lat" name="<?php echo esc_attr( $field_key ); ?>_lat" value="<?php echo esc_attr( $this->get_option( $key . '_lat' ) ); ?>" class="origin-coordinates"></div>
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lng"><?php echo esc_html( 'Longitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lng" name="<?php echo esc_attr( $field_key ); ?>_lng" value="<?php echo esc_attr( $this->get_option( $key . '_lng' ) ); ?>" class="origin-coordinates"></div>
				</div>
				<?php echo wp_kses( $this->get_description_html( $data ), wp_kses_allowed_html( 'post' ) ); ?>
				<script type="text/html" id="tmpl-<?php echo esc_attr( $this->id ); ?>-map-search">
					<input id="{{data.map_search_id}}" class="<?php echo esc_attr( $this->id ); ?>-map-search controls" type="text" placeholder="<?php echo esc_attr( __( 'Search your store location', 'wcsdm' ) ); ?>" autocomplete="off" />
				</script>
				<script type="text/html" id="tmpl-<?php echo esc_attr( $this->id ); ?>-map-canvas">
					<div id="{{data.map_canvas_id}}" class="<?php echo esc_attr( $this->id ); ?>-map-canvas"></div>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate coordinates settings field.
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_coordinates_html( $key, $data ) {}

	/**
	 * Generate table rates HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 */
	public function generate_table_rates_html( $key ) {
		ob_start();
		$shipping_classes = array();
		foreach ( WC()->shipping->get_shipping_classes() as $shipping_classes_key => $shipping_classes_value ) {
			$shipping_classes[ $shipping_classes_value->term_id ] = $shipping_classes_value;
		}
		ksort( $shipping_classes );
		$cols = array(
			'distance'  => __( 'Max. Distances', 'wcsdm' ),
			'cost_type' => __( 'Calculation Type', 'wcsdm' ),
			'class_0'   => __( 'Unspecified', 'wcsdm' ),
		);
		foreach ( $shipping_classes as $shipping_class_id => $shipping_class ) {
			$cols[ 'class_' . $shipping_class_id ] = $shipping_class->name;
		}
		$cols['base']            = __( 'Additional Cost', 'wcsdm' );
		$cols['free_min_amount'] = __( 'Min. Amount', 'wcsdm' );
		$cols['free_min_qty']    = __( 'Min. Quantity', 'wcsdm' );
		?>
		<tr valign="top">
			<td colspan="2">
				<table id="rates-list-table" class="widefat wc_input_table" cellspacing="0">
					<thead>
						<tr>
							<td class="col-checkbox"><div></div></td>
							<td class="col-distance"></td>
							<td class="col-cost-type"></td>
							<td colspan="<?php echo count( $shipping_classes ) + 1; ?>" class="cols-shipping-class">
								<strong><?php esc_html_e( 'Shipping Rate by Product Shipping Class', 'wcsdm' ); ?></strong><span class="tooltip" data-tooltip="<?php esc_attr_e( 'Enter 0 to disable shipping rate calculation for specified shipping class below.', 'wcsdm' ); ?>"></span>
							</td>
							<td class="col-base"></td>
							<td class="col-free-shipping" colspan="2">
								<strong><?php esc_html_e( 'Free Shipping', 'wcsdm' ); ?></strong><span class="tooltip" data-tooltip="<?php esc_attr_e( 'The shipping will be defined as FREE if any of conditionals below met. Enter 0 to disable free shipping.', 'wcsdm' ); ?>"></span>
							</td>
						</tr>
						<tr class="font-bold">
							<td class="col-checkbox"><div><input class="select-item" type="checkbox"></div></td>
							<?php foreach ( $cols as $col_key => $col_label ) : ?>
								<td class="col-data col-<?php echo esc_html( $col_key ); ?>"><?php echo esc_html( $col_label ); ?></td>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( $this->table_rates ) :
							foreach ( $this->table_rates as $table_rate ) :
								$this->generate_rate_row( $cols, $this->get_field_key( $key ), $table_rate );
							endforeach;
						endif;
						?>
					</tbody>
					<tfoot>
						<tr class="font-bold">
							<td class="col-checkbox">
								<div>
									<a href="#" class="add button" data-key="<?php echo esc_attr( $this->get_field_key( $key ) ); ?>"><?php esc_html_e( 'Add Rate', 'wcsdm' ); ?></a>
									<a href="#" class="remove_rows button" style="display: none"><?php esc_html_e( 'Remove Rate', 'wcsdm' ); ?></a>
								</div>
							</td>
							<?php foreach ( $cols as $col_key => $col_label ) : ?>
								<td class="col-data col-<?php echo esc_html( $col_key ); ?>"><?php echo esc_html( $col_label ); ?></td>
							<?php endforeach; ?>
						</tr>
					</tfoot>
				</table>
				<script type="text/template" id="tmpl-rates-list-input-table-row">
				<tr>
					<?php
					$this->generate_rate_row( $cols, $this->get_field_key( $key ) );
					?>
				</tr>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table rate columns
	 *
	 * @param array  $cols Table rate columns.
	 * @param string $field_key Table rate column key.
	 * @param array  $rate Table rate data.
	 * @return void
	 */
	private function generate_rate_row( $cols, $field_key, $rate = array() ) {
		?>
		<tr>
			<td class="col-checkbox"><div><input class="select-item" type="checkbox"></div></td>
			<?php foreach ( $cols as $col_key => $col_label ) : ?>
				<td class="col-data col-<?php echo esc_html( $col_key ); ?>">
					<?php
					$value = isset( $rate[ $col_key ] ) ? $rate[ $col_key ] : '';
					switch ( $col_key ) {
						case 'distance':
							?>
							<div class="field-group <?php echo esc_attr( $col_key ); ?>" data-unit-metric="KM" data-unit-imperial="MI">
								<div class="field-group-input">
									<input name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $col_key ); ?>[]" class="input-text regular-input no-decimal field-<?php echo esc_attr( $col_key ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="5">
								</div>
								<div class="field-group-icon"></div>
							</div>
							<?php
							break;
						case 'cost_type':
							?>
							<select class="select cost-type" name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $col_key ); ?>[]">
								<option value="flat" <?php selected( $value, 'flat' ); ?>><?php esc_html_e( 'Flat', 'wcsdm' ); ?></option>
								<option value="per_unit" <?php selected( $value, 'per_unit' ); ?>></option>
							</select>
							<?php
							break;
						case 'base':
							?>
							<div class="field-group <?php echo esc_attr( $col_key ); ?>">
								<div class="field-group-icon"><?php echo esc_attr( get_woocommerce_currency() ); ?></div>
								<div class="field-group-input">
									<input name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $col_key ); ?>[]" class="input-text regular-input field-<?php echo esc_attr( $col_key ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="any">
								</div>
							</div>
							<?php
							break;
						case 'free_min_qty':
							?>
							<div class="field-group <?php echo esc_attr( $col_key ); ?>">
								<div class="field-group-input">
									<input name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $col_key ); ?>[]" class="input-text regular-input no-decimal field-<?php echo esc_attr( $col_key ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="1">
								</div>
								<div class="field-group-icon">PCS</div>
							</div>
							<?php
							break;
						case 'free_min_amount':
							?>
							<div class="field-group <?php echo esc_attr( $col_key ); ?>">
								<div class="field-group-icon"><?php echo esc_attr( get_woocommerce_currency() ); ?></div>
								<div class="field-group-input">
									<input name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $col_key ); ?>[]" class="input-text regular-input field-<?php echo esc_attr( $col_key ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="any">
								</div>
							</div>
							<?php
							break;
						default:
							?>
							<div class="field-group field-group-cost <?php echo esc_attr( $col_key ); ?>">
								<div class="field-group-icon"><?php echo esc_attr( get_woocommerce_currency() ); ?></div>
								<div class="field-group-input">
									<input name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $col_key ); ?>[]" class="input-text regular-input field-cost field-<?php echo esc_attr( $col_key ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="any">
								</div>
							</div>
							<?php
							break;
					}
					?>
				</td>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * Validate gmaps_api_key settings field.
	 *
	 * @since    1.0.0
	 * @param  string $key Settings field key.
	 * @param  string $value Posted field value.
	 * @throws Exception If the field value is invalid.
	 * @return string
	 */
	public function validate_gmaps_api_key_field( $key, $value ) {
		try {
			if ( empty( $value ) ) {
				throw new Exception( __( 'API Key is required', 'wcsdm' ) );
			}
			return $value;
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return $this->gmaps_api_key;
		}
	}

	/**
	 * Validate origin_lat settings field.
	 *
	 * @since    1.0.0
	 * @param  string $key Settings field key.
	 * @param  string $value Posted field value.
	 * @throws Exception If the field value is invalid.
	 * @return string
	 */
	public function validate_origin_lat_field( $key, $value ) {
		try {
			if ( empty( $value ) ) {
				throw new Exception( __( 'Store Location Latitude is required', 'wcsdm' ) );
			}
			return $value;
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return $this->origin_lat;
		}
	}

	/**
	 * Validate origin_lng settings field.
	 *
	 * @since    1.0.0
	 * @param  string $key Settings field key.
	 * @param  string $value Posted field value.
	 * @throws Exception If the field value is invalid.
	 * @return string
	 */
	public function validate_origin_lng_field( $key, $value ) {
		try {
			if ( empty( $value ) ) {
				throw new Exception( __( 'Store Location Longitude is required', 'wcsdm' ) );
			}
			return $value;
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return $this->origin_lng;
		}
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
		try {
			$field_key = $this->get_field_key( $key );
			$post_data = $this->get_post_data();

			$rates = array();

			foreach ( $post_data as $post_data_key => $post_data_value ) {

				// Check if posted data key begin with field key value.
				if ( 0 !== strpos( $post_data_key, $field_key ) ) {
					continue;
				}

				$field_key_short = str_replace( $field_key . '_', '', $post_data_key );

				foreach ( $post_data_value as $index => $row_value ) {
					switch ( $field_key_short ) {
						case 'cost_type':
							$value = $row_value;
							break;
						case 'distance':
						case 'free_min_qty':
							$value = intval( $row_value );
							break;

						default:
							$value = wc_format_decimal( $row_value );
							if ( empty( $value ) ) {
								$value = 0;
							}
							break;
					}
					$rates[ $index ][ $field_key_short ] = $value;
				}
			}

			$rates_filtered = array();

			foreach ( $rates as $key => $value ) {
				if ( empty( $value['distance'] ) ) {
					continue;
				}
				$rates_filtered[ $value['distance'] ] = $value;
			}

			ksort( $rates_filtered );

			$value = array_values( $rates_filtered );

			if ( empty( $value ) ) {
				throw new Exception( __( 'Shipping Rates is required', 'wcsdm' ) );
			}
			return $value;
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return $this->table_rates;
		}
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
		if ( ! $available || empty( $package['contents'] ) || empty( $package['destination'] ) ) {
			return false;
		}

		return $available;
	}

	/**
	 * Calculate shipping function.
	 *
	 * @since    1.0.0
	 * @param array $package Package data array.
	 */
	public function calculate_shipping( $package = array() ) {

		$api_request = $this->api_request( $package );

		if ( ! $api_request ) {
			return;
		}

		$cost_total              = 0;
		$cost_per_order          = 0;
		$cost_per_shipping_class = array();
		$cost_per_product        = array();
		$cost_per_item           = 0;

		foreach ( $package['contents'] as $hash => $item ) {
			$shipping_class_id = $item['data']->get_shipping_class_id();
			$product_id        = $item['data']->get_id();
			$calculated_cost   = $this->calculate_cost( $api_request['distance'], $shipping_class_id );

			// Bail early if there is no rate found.
			if ( is_wp_error( $calculated_cost ) ) {
				return;
			}

			// Multiply shipping cost with distance.
			if ( 'yes' === $this->charge_per_distance_unit ) {
				$calculated_cost = $calculated_cost * $api_request['distance'];
			}

			// Calculate cost by calculation type setting.
			switch ( $this->calc_type ) {
				case 'per_order':
					if ( $calculated_cost > $cost_per_order ) {
						$cost_per_order = $calculated_cost;
					}
					break;
				case 'per_shipping_class':
					if ( isset( $cost_per_shipping_class[ $shipping_class_id ] ) ) {
						if ( $calculated_cost > $cost_per_shipping_class[ $shipping_class_id ] ) {
							$cost_per_shipping_class[ $shipping_class_id ] = $calculated_cost;
						}
					} else {
						$cost_per_shipping_class[ $shipping_class_id ] = $calculated_cost;
					}
					break;
				case 'per_product':
					if ( isset( $cost_per_product[ $product_id ] ) ) {
						if ( $calculated_cost > $cost_per_product[ $product_id ] ) {
							$cost_per_product[ $product_id ] = $calculated_cost;
						}
					} else {
						$cost_per_product[ $product_id ] = $calculated_cost;
					}
					break;
				default:
					$cost_per_item += $calculated_cost * $item['quantity'];
					break;
			}
		}

		switch ( $this->calc_type ) {
			case 'per_order':
				$cost_total = $cost_per_order;
				break;
			case 'per_shipping_class':
				$cost_total = array_sum( $cost_per_shipping_class );
				break;
			case 'per_product':
				$cost_total = array_sum( $cost_per_product );
				break;
			default:
				$cost_total = $cost_per_item;
				break;
		}

		// Apply shipping base fee.
		if ( 'yes' === $this->charge_per_distance_unit ) {
			$cost_total += $this->calculate_base_fee( $api_request['distance'] );
		}

		// Set shipping courier label.
		$label = $cost_total ? $this->title : __( 'Free Shipping', 'wcsdm' );
		if ( $cost_total && 'yes' === $this->show_distance && ! empty( $api_request['distance_text'] ) ) {
			$label = sprintf( '%s (%s)', $label, $api_request['distance_text'] );
		}

		$rate = array(
			'id'        => $this->get_rate_id(),
			'label'     => $label,
			'cost'      => $cost_total,
			'meta_data' => $api_request,
		);

		// Register the rate.
		$this->add_rate( $rate );

		/**
		 * Developers can add additional rates via action.
		 *
		 * This example shows how you can add an extra rate via custom function:
		 *
		 *      add_action( 'woocommerce_wcsdm_shipping_add_rate', 'add_another_custom_flat_rate', 10, 2 );
		 *
		 *      function add_another_custom_flat_rate( $method, $rate ) {
		 *          $new_rate          = $rate;
		 *          $new_rate['id']    .= ':' . 'custom_rate_name'; // Append a custom ID.
		 *          $new_rate['label'] = 'Rushed Shipping'; // Rename to 'Rushed Shipping'.
		 *          $new_rate['cost']  += 2; // Add $2 to the cost.
		 *
		 *          // Add it to WC.
		 *          $method->add_rate( $new_rate );
		 *      }.
		 */
		do_action( 'woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate );
	}

	/**
	 * Calculate cost by distance and shipping class
	 *
	 * @since    1.0.0
	 * @param int $distance Distance of shipping destination.
	 * @param int $class_id Shipping class ID of selected product.
	 */
	private function calculate_cost( $distance, $class_id ) {
		$class_id = intval( $class_id );

		if ( $this->table_rates ) {
			$offset = 0;
			foreach ( $this->table_rates as $rate ) {
				if ( $distance > $offset && $distance <= $rate['distance'] && isset( $rate[ 'class_' . $class_id ] ) && strlen( $rate[ 'class_' . $class_id ] ) ) {
					return $this->normalize_price( $rate[ 'class_' . $class_id ] );
				}
				$offset = $rate['distance'];
			}
		}

		return new WP_Error( 'no_rates', __( 'No rates data availbale.', 'wcsdm' ) );
	}

	/**
	 * Calculate base fee.
	 *
	 * @since    1.3.8
	 * @param int $distance Distance of shipping destination.
	 */
	private function calculate_base_fee( $distance ) {
		$base_fee = 0;

		if ( $this->table_rates ) {
			$offset = 0;
			foreach ( $this->table_rates as $rate ) {
				if ( $distance > $offset && $distance <= $rate['distance'] && isset( $rate['base'] ) && strlen( $rate['base'] ) ) {
					return $this->normalize_price( $rate['base'] );
				}
				$offset = $rate['distance'];
			}
		}

		return $base_fee;
	}

	/**
	 * Normalize price format to standard format for math procedure.
	 *
	 * @since    1.3.6
	 *
	 * @param  float $price Raw price data.
	 * @return string
	 */
	private function normalize_price( $price ) {

		$price = str_replace( ',', '', $price );

		if ( 1 < substr_count( $price, '.' ) ) {
			$price = preg_replace( '/\./', '', $price, ( substr_count( $price, '.' ) - 1 ) );
		}

		return $price;
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @since    1.0.0
	 * @param array $package The cart content data.
	 * @return array
	 */
	private function api_request( $package ) {
		if ( empty( $this->gmaps_api_key ) ) {
			return false;
		}

		$destination_info = $this->get_destination_info( $package['destination'] );
		if ( empty( $destination_info ) ) {
			return false;
		}

		$origin_info = $this->get_origin_info( $package );
		if ( empty( $origin_info ) ) {
			return false;
		}

		$request_url_args = array(
			'key'          => rawurlencode( $this->gmaps_api_key ),
			'mode'         => rawurlencode( $this->gmaps_api_mode ),
			'avoid'        => is_string( $this->gmaps_api_avoid ) ? rawurlencode( $this->gmaps_api_avoid ) : '',
			'units'        => rawurlencode( $this->gmaps_api_units ),
			'language'     => rawurlencode( get_locale() ),
			'origins'      => rawurlencode( implode( ',', $origin_info ) ),
			'destinations' => rawurlencode( implode( ',', $destination_info ) ),
		);

		$cache_key = $this->id . '_api_request_' . md5(
			wp_json_encode(
				array(
					'request_url_args' => $request_url_args,
					'table_rates'      => $this->table_rates,
				)
			)
		);

		// Check if the data already chached and return it.
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			$this->show_debug( __( 'Cache key', 'wcsdm' ) . ': ' . $cache_key );
			$this->show_debug( __( 'Cached data', 'wcsdm' ) . ': ' . wp_json_encode( $cached_data ) );
			return $cached_data;
		}

		$request_url = add_query_arg( $request_url_args, $this->google_api_url );

		$this->show_debug( __( 'API Request URL', 'wcsdm' ) . ': ' . str_replace( rawurlencode( $this->gmaps_api_key ), '**********', $request_url ), 'notice' );

		$data = $this->process_api_response( wp_remote_get( esc_url_raw( $request_url ) ) );

		// Try to make fallback request if no results found.
		if ( ! $data && 'yes' === $this->enable_fallback_request && ! empty( $destination_info['address_2'] ) ) {
			unset( $destination_info['address'] );
			$request_url_args['destinations'] = rawurlencode( implode( ',', $destination_info ) );

			$request_url = add_query_arg( $request_url_args, $this->google_api_url );

			$this->show_debug( __( 'API Fallback Request URL', 'wcsdm' ) . ': ' . str_replace( rawurlencode( $this->gmaps_api_key ), '**********', $request_url ), 'notice' );

			$data = $this->process_api_response( wp_remote_get( esc_url_raw( $request_url ) ) );
		}

		if ( $data ) {

			delete_transient( $cache_key ); // To make sure the transient data re-created, delete it first.
			set_transient( $cache_key, $data, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.

			return $data;
		}

		return false;
	}

	/**
	 * Process API Response.
	 *
	 * @since 1.3.4
	 * @throws Exception If API response data is invalid.
	 * @param array $raw_response HTTP API response.
	 * @return array|bool Formatted response data, false on failure.
	 */
	private function process_api_response( $raw_response ) {

		$distance      = 0;
		$distance_text = null;
		$response_data = null;

		try {
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
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$error_message = __( 'Error occured while decoding API response', 'wcsdm' );
				if ( function_exists( 'json_last_error_msg' ) ) {
					$error_message .= ': ' . json_last_error_msg();
				}
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

			$element_lvl_errors = array(
				'NOT_FOUND'                 => __( 'Origin and/or destination of this pairing could not be geocoded', 'wcsdm' ),
				'ZERO_RESULTS'              => __( 'No route could be found between the origin and destination', 'wcsdm' ),
				'MAX_ROUTE_LENGTH_EXCEEDED' => __( 'Requested route is too long and cannot be processed', 'wcsdm' ),
			);

			// Get the shipping distance.
			foreach ( $response_data['rows'] as $row ) {

				// Break the loop if distance is defined.
				if ( $distance && $distance_text ) {
					break;
				}

				foreach ( $row['elements'] as $element ) {
					if ( 'OK' !== $element['status'] ) {
						$error_message = __( 'API Response Error', 'wcsdm' ) . ': ' . $element['status'];
						if ( isset( $element_lvl_errors[ $element['status'] ] ) ) {
							$error_message .= ' - ' . $element_lvl_errors[ $element['status'] ];
						}
						throw new Exception( $error_message );
					}
					if ( ! empty( $element['distance']['value'] ) && $distance < $element['distance']['value'] ) {
						$distance      = $this->convert_m( $element['distance']['value'] );
						$distance_text = $element['distance']['text'];
					}
				}
			}

			if ( ! $distance || ! $distance_text ) {
				throw new Exception( __( 'Unknown error', 'wcsdm' ) );
			}
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), 'notice' );
			return false;
		}

		// Rounds distance UP to the nearest integer.
		if ( 'yes' === $this->ceil_distance ) {
			$distance = ceil( $distance );
		}

		return array(
			'distance'      => $distance,
			'distance_text' => $distance_text,
			'response'      => $response_data,
		);
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

		if ( ! empty( $this->origin_lat ) && ! empty( $this->origin_lng ) ) {
			$origin_info['lat'] = $this->origin_lat;
			$origin_info['lng'] = $this->origin_lng;
		}

		/**
		 * Developers can modify the origin info via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the shipping origin info via custom function:
		 *
		 *      add_action( 'woocommerce_wcsdm_shipping_origin_info', 'modify_shipping_origin_info', 10, 2 );
		 *
		 *      function modify_shipping_origin_info( $origin_info, $package ) {
		 *          return '1600 Amphitheatre Parkway,Mountain View,CA,94043';
		 *      }
		 */
		return apply_filters( 'woocommerce_' . $this->id . '_shipping_origin_info', $origin_info, $package );
	}

	/**
	 * Get shipping destination info
	 *
	 * @since    1.0.0
	 * @param array $data Shipping destination data in associative array format: address, city, state, postcode, country.
	 * @return string
	 */
	private function get_destination_info( $data ) {
		$destination_info = array();

		$keys = array( 'address', 'address_2', 'city', 'state', 'postcode', 'country' );

		// Remove destination field keys for shipping calculator request.
		if ( isset( $_POST['calc_shipping'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-cart' ) ) {
			$keys_remove = array( 'address', 'address_2' );
			if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_city', false ) ) {
				array_push( $keys_remove, 'city' );
			}
			if ( ! apply_filters( 'woocommerce_shipping_calculator_enable_postcode', false ) ) {
				array_push( $keys_remove, 'postcode' );
			}
			$keys = array_diff( $keys, $keys_remove );
		}

		$country_code = false;

		foreach ( $keys as $key ) {
			if ( empty( $data[ $key ] ) ) {
				continue;
			}
			switch ( $key ) {
				case 'country':
					if ( empty( $country_code ) ) {
						$country_code = $data[ $key ];
					}
					$full_country             = isset( WC()->countries->countries[ $country_code ] ) ? WC()->countries->countries[ $country_code ] : $country_code;
					$destination_info[ $key ] = trim( $full_country );
					break;
				case 'state':
					if ( empty( $country_code ) ) {
						$country_code = $data['country'];
					}
					$full_state               = isset( WC()->countries->states[ $country_code ][ $data[ $key ] ] ) ? WC()->countries->states[ $country_code ][ $data[ $key ] ] : $data[ $key ];
					$destination_info[ $key ] = trim( $full_state );
					break;
				default:
					$destination_info[ $key ] = trim( $data[ $key ] );
					break;
			}
		}

		/**
		 * Developers can modify the destination info via filter hooks.
		 *
		 * @since 1.0.1
		 *
		 * This example shows how you can modify the shipping destination info via custom function:
		 *
		 *      add_action( 'woocommerce_wcsdm_shipping_destination_info', 'modify_shipping_destination_info', 10, 2 );
		 *
		 *      function modify_shipping_destination_info( $destination_info, $destination_info_arr ) {
		 *          return '1600 Amphitheatre Parkway,Mountain View,CA,94043';
		 *      }
		 */
		return apply_filters( 'woocommerce_' . $this->id . '_shipping_destination_info', $destination_info, $this );
	}

	/**
	 * Convert Meters to Distance Unit
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_m( $meters ) {
		return ( 'metric' === $this->gmaps_api_units ) ? $this->convert_m_to_km( $meters ) : $this->convert_m_to_mi( $meters );
	}

	/**
	 * Convert Meters to Miles
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_m_to_mi( $meters ) {
		return $meters * 0.000621371;
	}

	/**
	 * Convert Meters to Kilometres
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_m_to_km( $meters ) {
		return $meters * 0.001;
	}

	/**
	 * Show debug info
	 *
	 * @since    1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $type The type of notice.
	 * @return void
	 */
	private function show_debug( $message, $type = 'success' ) {
		$debug_mode = 'yes' === get_option( 'woocommerce_shipping_debug_mode', 'no' );

		if ( $debug_mode && ! defined( 'WOOCOMMERCE_CHECKOUT' ) && ! defined( 'WC_DOING_AJAX' ) && ! wc_has_notice( $message ) ) {
			wc_add_notice( $message, $type );
		}
	}

	/**
	 * Sort product shipping class by ID
	 *
	 * @since    1.3.5
	 * @param array $a First index of the array.
	 * @param array $b Compared array.
	 * @return integer
	 */
	private function sort_product_shipping_class( $a, $b ) {
		$a = isset( $a['term_id'] ) ? (int) $a['term_id'] : 10;
		$b = isset( $b['term_id'] ) ? (int) $b['term_id'] : 10;
		if ( $a === $b ) {
			return 0;
		}
		return ( $a < $b ) ? -1 : 1;
	}
}
