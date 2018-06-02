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
		$this->title                   = $this->get_option( 'title' );
		$this->gmaps_api_key           = $this->get_option( 'gmaps_api_key' );
		$this->origin_lat              = $this->get_option( 'origin_lat' );
		$this->origin_lng              = $this->get_option( 'origin_lng' );
		$this->gmaps_api_units         = $this->get_option( 'gmaps_api_units', 'metric' );
		$this->gmaps_api_mode          = $this->get_option( 'gmaps_api_mode', 'driving' );
		$this->gmaps_api_avoid         = $this->get_option( 'gmaps_api_avoid' );
		$this->calc_type               = $this->get_option( 'calc_type', 'per_item' );
		$this->enable_fallback_request = $this->get_option( 'enable_fallback_request', 'no' );
		$this->show_distance           = $this->get_option( 'show_distance' );
		$this->ceil_distance           = $this->get_option( 'ceil_distance', 'no' );
		$this->table_rates             = $this->get_option( 'table_rates' );
		$this->tax_status              = $this->get_option( 'tax_status' );

		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		// Check if this shipping method is availbale for current order.
		add_filter( 'woocommerce_shipping_' . $this->id . '_is_available', array( $this, 'check_is_available' ), 10, 2 );

		// Show city field on the cart shipping calculator.
		add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_true' );
	}

	/**
	 * Init form fields.
	 *
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'                   => array(
				'title'       => __( 'Title', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'tax_status'              => array(
				'title'   => __( 'Tax Status', 'wcsdm' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'gmaps_api_key'           => array(
				'title'       => __( 'API Key', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( 'This plugin require Google Maps Distance Matrix API Key and service is enabled. <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> to go to Google API Console to get API Key and to enable the service.', 'wcsdm' ),
				'default'     => '',
			),
			'origin'                  => array(
				'title'       => __( 'Store Location', 'wcsdm' ),
				'type'        => 'address_picker',
				'description' => __( '<a href="http://www.latlong.net/" target="_blank">Click here</a> to get your store location coordinates info.', 'wcsdm' ),
			),
			'origin_lat'              => array(
				'title' => __( 'Store Location Latitude', 'wcsdm' ),
				'type'  => 'coordinates',
			),
			'origin_lng'              => array(
				'title' => __( 'Store Location Logitude', 'wcsdm' ),
				'type'  => 'coordinates',
			),
			'gmaps_api_mode'          => array(
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
			'gmaps_api_avoid'         => array(
				'title'       => __( 'Route Restrictions', 'wcsdm' ),
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
			'gmaps_api_units'         => array(
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
			'show_distance'           => array(
				'title'       => __( 'Show Distance Info', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'Show the distance info to customer during checkout.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'ceil_distance'           => array(
				'title'       => __( 'Round Up Distance', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'Round up distance to the nearest integer.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'enable_fallback_request' => array(
				'title'       => __( 'Enable Fallback Request', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
				'type'        => 'checkbox',
				'description' => __( 'If there is no results for API request using full address, the system will attempt to make another API request to the Google API server without "Address Line 1" parameter. The fallback request will only using "Address Line 2", "City", "State/Province", "Postal Code" and "Country" parameters.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'calc_type'               => array(
				'title'       => __( 'Progressive Shipping Cost', 'wcsdm' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => 'per_order',
				'options'     => array(
					'per_order'          => __( 'No', 'wcsdm' ),
					'per_shipping_class' => __( 'Per Shipping Class: Accumulate total shipping cost by shipping class ID', 'wcsdm' ),
					'per_product'        => __( 'Per Product: Accumulate total shipping cost by product ID', 'wcsdm' ),
					'per_item'           => __( 'Per Unit: Accumulate total shipping cost by quantity', 'wcsdm' ),
				),
				'description' => __( 'By default the total shipping cost will be flat by choosing the most expensive shipping rate by comparing for each items added into the cart. Example: there was 2 products with different shipping class in the cart, then the highest rate will be used as the total shipping cost.', 'wcsdm' ),
				'desc_tip'    => true,
			),
			'table_rates'             => array(
				'type' => 'table_rates',
			),
			'table_advanced'          => array(
				'type'  => 'table_advanced',
				'title' => __( 'Advanced Rate Settings', 'wcsdm' ),
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
				<div id="<?php echo esc_attr( $this->id ); ?>-map-wrapper" class="<?php echo esc_attr( $this->id ); ?>-map-wrapper"></div>
				<div id="<?php echo esc_attr( $this->id ); ?>-lat-lng-wrap">
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lat"><?php echo esc_html( 'Latitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lat" name="<?php echo esc_attr( $field_key ); ?>_lat" value="<?php echo esc_attr( $this->get_option( $key . '_lat' ) ); ?>" class="origin-coordinates"></div>
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lng"><?php echo esc_html( 'Longitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lng" name="<?php echo esc_attr( $field_key ); ?>_lng" value="<?php echo esc_attr( $this->get_option( $key . '_lng' ) ); ?>" class="origin-coordinates"></div>
				</div>
				<?php echo wp_kses( $this->get_description_html( $data ), wp_kses_allowed_html( 'post' ) ); ?>
				<script type="text/html" id="tmpl-<?php echo esc_attr( $this->id ); ?>-map-search">
					<input id="wcsdm-map-search" class="<?php echo esc_attr( $this->id ); ?>-map-search controls" type="text" placeholder="<?php echo esc_attr( __( 'Search your store location', 'wcsdm' ) ); ?>" autocomplete="off" />
				</script>
				<script type="text/html" id="tmpl-<?php echo esc_attr( $this->id ); ?>-map-canvas">
					<div id="wcsdm-map-canvas" class="<?php echo esc_attr( $this->id ); ?>-map-canvas"></div>
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
	public function generate_coordinates_html( $key, $data ) {
	}

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
		if ( $shipping_classes ) {
			ksort( $shipping_classes );
		}
		$cols = array(
			'distance'  => __( 'Maximum Distances', 'wcsdm' ),
			'cost_type' => __( 'Cost Type', 'wcsdm' ),
			'class_0'   => $shipping_classes ? __( 'None', 'wcsdm' ) : __( 'Shipping Rate', 'wcsdm' ),
		);
		foreach ( $shipping_classes as $shipping_class_id => $shipping_class ) {
			$cols[ 'class_' . $shipping_class_id ] = $shipping_class->name;
		}
		$cols['advanced'] = __( 'Advanced', 'wcsdm' );
		?>
		<tr valign="top" id="wcsdm-table-row-rates" class="wcsdm-table-row wcsdm-table-row-rates">
			<td class="wcsdm-table-col wcsdm-table-col-rates" colspan="2">
				<table id="wcsdm-table-rates" class="wc_input_table widefat wcsdm-table wcsdm-table-rates" cellspacing="0">
					<thead>
						<?php $this->generate_rate_row_heading( $cols ); ?>
					</thead>
					<tbody>
						<?php
						if ( $this->table_rates ) :
							foreach ( $this->table_rates as $table_rate ) :
								$this->generate_rate_row( $this->get_field_key( $key ), $table_rate );
							endforeach;
						endif;
						?>
					</tbody>
					<tfoot>
						<?php $this->generate_rate_row_heading( $cols, 'bottom' ); ?>
					</tfoot>
				</table>
				<script type="text/template" id="tmpl-rates-list-input-table-row">
					<?php $this->generate_rate_row( $this->get_field_key( $key ) ); ?>
				</script>
				<script type="text/template" id="tmpl-btn-advanced">
					<button id="btn-dummy" class="button button-primary button-large"><?php esc_html_e( 'Apply Changes', 'wcsdm' ); ?></button>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate table rate row heading
	 *
	 * @param array  $cols Table rate columns.
	 * @param string $position Row heading position.
	 * @return void
	 */
	private function generate_rate_row_heading( $cols, $position = 'top' ) {
		?>
		<tr class="font-bold">
			<td class="col-checkbox">
				<div>
					<?php if ( 'top' === $position ) : ?>
						<input class="select-item" type="checkbox">
					<?php else : ?>
						<a href="#" class="add_row button"><?php esc_html_e( 'Add Row', 'wcsdm' ); ?></a>
						<a href="#" class="remove_rows button" style="display: none"><?php esc_html_e( 'Remove Rows', 'wcsdm' ); ?></a>
					<?php endif; ?>
				</div>
			</td>
			<?php foreach ( $this->rates_fields( false ) as $key => $col ) : ?>
				<?php if ( ! $col['advanced'] ) : ?>
				<td class="col-data col-<?php echo esc_html( $key ); ?>">
					<?php if ( 'top' === $position && 'advanced' !== $key && ! empty( $col['description'] ) ) : ?>
						<span class="tooltip" data-tooltip="<?php echo esc_attr( $col['description'] ); ?>">
					<?php endif; ?>
					<strong><?php echo esc_html( $col['title'] ); ?></strong>
					<?php if ( 'top' === $position && 'advanced' !== $key && ! empty( $col['description'] ) ) : ?>
						</span>
					<?php endif; ?>
				</td>
				<?php endif; ?>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * Generate table rate columns
	 *
	 * @param string $field_key Table rate column key.
	 * @param array  $rate Table rate data.
	 * @return void
	 */
	private function generate_rate_row( $field_key, $rate = array() ) {
		?>
		<tr>
			<td class="col-checkbox">
				<div><input class="select-item" type="checkbox"></div>
			</td>
			<?php foreach ( $this->rates_fields( false ) as $key => $col ) : ?>
				<?php
				$data_id    = 'woocommerce_' . $this->id . '_' . $key;
				$input_name = $field_key . '_' . $key;
				$value      = isset( $rate[ $key ] ) ? $rate[ $key ] : ( isset( $col['default'] ) ? $col['default'] : '' );
				switch ( $key ) {
					case 'distance':
						?>
						<td class="col-data col-<?php echo esc_html( $key ); ?>">
							<div class="field-groups has-units <?php echo esc_attr( $key ); ?>">
								<div class="field-group-item field-group-item-input">
									<input class="input-text regular-input input-cost wcsdm-input-dummy field-<?php echo esc_attr( $key ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="any">
								</div>
								<div class="field-group-item field-group-item-units"></div>
							</div>
							<input name="<?php echo esc_attr( $input_name ); ?>[]" type="hidden" value="<?php echo esc_attr( $value ); ?>" class="wcsdm-input wcsdm-input-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $data_id ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>">
						</td>
						<?php
						break;
					case 'cost_type':
						?>
						<td class="col-data col-<?php echo esc_html( $key ); ?>">
							<select class="select cost-type wcsdm-input-dummy" data-id="<?php echo esc_attr( $data_id ); ?>">
								<?php foreach ( $col['options'] as $option_value => $option_text ) : ?>
									<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_text ); ?></option>
								<?php endforeach; ?>
							</select>
							<input name="<?php echo esc_attr( $input_name ); ?>[]" type="hidden" value="<?php echo esc_attr( $value ); ?>" class="wcsdm-input wcsdm-input-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $data_id ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>">
						</td>
						<?php
						break;
					case 'class_0':
						?>
						<td class="col-data col-<?php echo esc_html( $key ); ?>">
							<div class="field-groups has-units <?php echo esc_attr( $key ); ?>">
								<div class="field-group-item field-group-item-units"><?php echo esc_attr( get_woocommerce_currency() ); ?></div>
								<div class="field-group-item field-group-item-input">
									<input class="input-text regular-input input-cost wcsdm-input-dummy field-<?php echo esc_attr( $key ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>" type="number" value="<?php echo esc_attr( $value ); ?>" min="0" step="any">
								</div>
							</div>
							<input name="<?php echo esc_attr( $input_name ); ?>[]" type="hidden" value="<?php echo esc_attr( $value ); ?>" class="wcsdm-input wcsdm-input-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $data_id ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>">
						</td>
						<?php
						break;
					case 'free':
						?>
						<td class="col-data col-<?php echo esc_html( $key ); ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( in_array( $value, array( 'yes', 'yes_alt' ), true ) ? 'yes' : 'no' ); ?>"></span>
							<input name="<?php echo esc_attr( $input_name ); ?>[]" type="hidden" value="<?php echo esc_attr( $value ); ?>" class="wcsdm-input wcsdm-input-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $data_id ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>">
						</td>
						<?php
						break;
				}
				?>
			<?php endforeach; ?>
			<td class="col-advanced">
				<?php
				foreach ( $this->rates_fields( false ) as $key => $col ) :
					$data_id    = 'woocommerce_' . $this->id . '_' . $key;
					$input_name = $field_key . '_' . $key;
					$value      = isset( $rate[ $key ] ) ? $rate[ $key ] : '';
					switch ( $key ) {
						case 'distance':
						case 'cost_type':
						case 'class_0':
						case 'free':
							// Do nothing.
							break;
						case 'advanced':
							?>
							<a href="#" class="advanced-rate-settings" title="<?php echo esc_attr( $col['description'] ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
							<?php
							break;
						default:
							?>
							<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>[]" value="<?php echo esc_attr( $value ); ?>" class="wcsdm-input wcsdm-input-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $data_id ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>">
							<?php
							break;
					}
					?>
				<?php endforeach; ?>
			</td>
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
		<tr valign="top" id="wcsdm-table-row-advanced" class="wcsdm-table-row wcsdm-table-row-advanced">
			<td class="wcsdm-table-col wcsdm-table-col-advanced" colspan="2">
				<h3 class="wc-settings-sub-title"><?php echo wp_kses_post( $data['title'] ); ?></h3>
				<table id="wcsdm-table-advanced" class="form-table wcsdm-table wcsdm-table-advanced" cellspacing="0">
					<tbody>
						<?php $this->generate_settings_html( $this->rates_fields() ); ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Populate rate fields
	 *
	 * @since    1.4.2
	 *
	 * @param bool $advanced Is fields will be displayed in adnaced settings form.
	 * @return array
	 */
	public function rates_fields( $advanced = true ) {
		$shipping_classes = array();
		foreach ( WC()->shipping->get_shipping_classes() as $shipping_classes_key => $shipping_classes_value ) {
			$shipping_classes[ $shipping_classes_value->term_id ] = $shipping_classes_value;
		}

		$fields = array(
			'distance'        => array(
				'type'     => 'number',
				'title'    => __( 'Maximum Distances', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'advanced' => false,
			),
			'cost_type'       => array(
				'type'     => 'select',
				'title'    => __( 'Cost Type', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'default'  => 'flat',
				'options'  => array(
					'flat'     => __( 'Flat', 'wcsdm' ),
					'per_unit' => '',
				),
				'advanced' => false,
			),
			'class_0'         => array(
				'type'     => 'number',
				'title'    => __( 'Shipping Rate', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'advanced' => false,
			),
			'base'            => array(
				'type'     => 'number',
				'title'    => __( 'Addional Cost', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'default'  => '0',
				'advanced' => true,
			),
			'free'            => array(
				'type'     => 'select',
				'title'    => __( 'Free Shipping', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'default'  => 'no',
				'options'  => array(
					'no'      => __( 'No', 'wcsdm' ),
					'yes'     => __( 'Yes, set as Free Shipping', 'wcsdm' ),
					'yes_alt' => __( 'Yes, set as Free Shipping if any of rules below is matched', 'wcsdm' ),
				),
				'advanced' => false,
			),
			'free_min_amount' => array(
				'type'     => 'number',
				'title'    => __( 'Rule #1: Minimum Order Amount', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'advanced' => true,
			),
			'free_min_qty'    => array(
				'type'     => 'number',
				'title'    => __( 'Rule #2: Minimum Order Quantity', 'wcsdm' ),
				'class'    => 'wcsdm-input',
				'advanced' => true,
			),
			'advanced'        => array(
				'type'        => 'link',
				'title'       => __( 'Advanced', 'wcsdm' ),
				'description' => __( 'Advanced Settings', 'wcsdm' ),
				'advanced'    => false,
			),
		);

		if ( $advanced ) {
			unset( $fields['advanced'] );
		}

		if ( $shipping_classes ) {
			$new_fields = array();
			foreach ( $fields as $key => $field ) {
				$new_fields[ $key ] = $field;
				if ( 'class_0' === $key ) {
					foreach ( $shipping_classes as $class_id => $class_obj ) {
						$new_fields[ 'class_' . $class_id ] = array_merge($field, array(
							// translators: %s is Product shipping class name.
							'title'       => sprintf( __( 'Shipping Rate for "%s"', 'wcsdm' ), $class_obj->name ),
							'description' => __( 'Shipping rate for specific product shipping class. This rate will override the default shipping rate defined above.', 'wcsdm' ),
							'desc_tip'    => true,
							'advanced'    => true,
						));
					}
				}
			}
			return $new_fields;
		}

		return $fields;
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

				$data_key = str_replace( $field_key . '_', '', $post_data_key );

				foreach ( $post_data_value as $index => $row_value ) {
					switch ( $data_key ) {
						case 'free':
						case 'cost_type':
							$value = $row_value;
							break;

						case 'distance':
							$value = intval( $row_value );
							break;

						case 'free_min_qty':
							$value = strlen( $row_value ) ? intval( $row_value ) : '';
							break;

						case 'base':
							$value = strlen( $row_value ) ? wc_format_decimal( $row_value ) : '';
							if ( empty( $value ) ) {
								$value = 0;
							}
							break;

						default:
							$value = strlen( $row_value ) ? wc_format_decimal( $row_value ) : '';
							break;
					}
					if ( is_numeric( $value ) && $value < 0 ) {
						$value = 0;
					}
					$rates[ $index ][ $data_key ] = $value;
				}
			}

			$rates_filtered = array();

			foreach ( $rates as $key => $value ) {
				if ( empty( $value['distance'] ) ) {
					continue;
				}
				$rates_filtered[ $value['distance'] ] = $value;
			}

			if ( empty( $rates_filtered ) ) {
				throw new Exception( __( 'Shipping Rates is required', 'wcsdm' ) );
			}

			ksort( $rates_filtered );

			return array_values( $rates_filtered );
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
			$api_request = $this->api_request( $package );

			if ( ! $api_request ) {
				throw new Exception( __( 'API response is empty', 'wcsdm' ) );
			}

			$rate_data = $this->get_rate_by_distance( $api_request['distance'] );

			// Bail early if there is no rate found.
			if ( is_wp_error( $rate_data ) ) {
				throw new Exception( $rate_data->get_error_message() );
			}

			// Check if free shipping by minimum order amount.
			$is_free_shipping = false;
			if ( strlen( $rate_data['free_min_amount'] ) && $woocommerce->cart->get_cart_contents_total() >= $rate_data['free_min_amount'] ) {
				$is_free_shipping = true;
			}

			// Check if free shipping by minimum order quantity.
			if ( strlen( $rate_data['free_min_qty'] ) && $woocommerce->cart->get_cart_contents_count() >= $rate_data['free_min_qty'] ) {
				$is_free_shipping = true;
			}

			// Apply free shipping rate.
			if ( $is_free_shipping ) {
				// Register the rate.
				$this->register_rate(
					array(
						'id'        => $this->get_rate_id(),
						'label'     => __( 'Free Shipping', 'wcsdm' ),
						'cost'      => 0,
						'meta_data' => $api_request,
					)
				);
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

				$calculated_cost = isset( $rate_data[ 'class_' . $shipping_class_id ] ) ? $rate_data[ 'class_' . $shipping_class_id ] : false;

				if ( ! $calculated_cost && is_numeric( $calculated_cost ) ) {
					throw new Exception( __( 'Product shipping class rate is not defined', 'wcsdm' ) );
				}

				// Multiply shipping cost with distance unit.
				if ( 'per_unit' === $rate_data['cost_type'] ) {
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
			if ( ! empty( $rate_data['base'] ) ) {
				$cost_total += $rate_data['base'];
			}

			// Set shipping courier label.
			$label = $this->title;
			if ( 'yes' === $this->show_distance && ! empty( $api_request['distance_text'] ) ) {
				$label = sprintf( '%s (%s)', $label, $api_request['distance_text'] );
			}

			$rate = array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => $cost_total,
				'meta_data' => $api_request,
			);

			// Register the rate.
			$this->register_rate( $rate );
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage() );
		}
	}

	/**
	 * Register shipping rate to cart.
	 *
	 * @since 1.4
	 * @param array $rate Shipping rate date.
	 * @return void
	 */
	private function register_rate( $rate ) {

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
	 * Get shipping table rate by distance and shipping class
	 *
	 * @since    1.0.0
	 * @param int $distance Distance of shipping destination.
	 */
	private function get_rate_by_distance( $distance ) {
		if ( $this->table_rates ) {
			$offset = 0;
			foreach ( $this->table_rates as $rate ) {
				if ( $distance > $offset && $distance <= $rate['distance'] ) {
					return $rate;
				}
				$offset = $rate['distance'];
			}
		}

		return new WP_Error( 'no_rates', __( 'No rates data availbale.', 'wcsdm' ) );
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
					'package'          => $package,
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
			$distance      = ceil( $distance );
			$distance_text = $distance . preg_replace( '/[0-9\.,]/', '', $distance_text );
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
}
