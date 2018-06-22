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
	 * All options data
	 *
	 * @since    1.4.02
	 * @var array
	 */
	private $all_options = array();

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
		$this->method_description = __( 'Shipping rates calculator that allows you to easily offer shipping rates based on the distance.', 'wcsdm' );

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
		$this->all_options['title']                   = $this->get_option( 'title' );
		$this->all_options['gmaps_api_key']           = $this->get_option( 'gmaps_api_key' );
		$this->all_options['origin_lat']              = $this->get_option( 'origin_lat' );
		$this->all_options['origin_lng']              = $this->get_option( 'origin_lng' );
		$this->all_options['gmaps_api_units']         = $this->get_option( 'gmaps_api_units', 'metric' );
		$this->all_options['gmaps_api_mode']          = $this->get_option( 'gmaps_api_mode', 'driving' );
		$this->all_options['gmaps_api_avoid']         = $this->get_option( 'gmaps_api_avoid' );
		$this->all_options['prefered_route']          = $this->get_option( 'prefered_route', 'prefered_route' );
		$this->all_options['calc_type']               = $this->get_option( 'calc_type', 'per_item' );
		$this->all_options['enable_fallback_request'] = $this->get_option( 'enable_fallback_request', 'no' );
		$this->all_options['show_distance']           = $this->get_option( 'show_distance' );
		$this->all_options['ceil_distance']           = $this->get_option( 'ceil_distance', 'no' );
		$this->all_options['table_rates']             = $this->get_option( 'table_rates' );
		$this->all_options['tax_status']              = $this->get_option( 'tax_status' );

		foreach ( $this->all_options as $key => $value ) {
			$this->{$key} = $value;
		}

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
				'title'       => __( 'Google API Key', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( 'This plugin makes use of the Google Maps and Google Distance Matrix APIs. <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> obtain a Google API Key.', 'wcsdm' ),
				'default'     => '',
			),
			'origin'                  => array(
				'title'       => __( 'Store Location', 'wcsdm' ),
				'type'        => 'address_picker',
				'description' => __( 'Drag the and drop the store icon to your store location at the map or search your store location by typing an address into the search input field. If you see any error printed on the setting field, please click the link printed within the error message to find out the causes and solutions.', 'wcsdm' ),
				'desc_tip'    => true,
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
			'prefered_route'          => array(
				'title'       => __( 'Prefered Route', 'wcsdm' ),
				'type'        => 'select',
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
				'title'       => __( 'Progressive Total Cost', 'wcsdm' ),
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
				'title' => __( 'Distance Rate Rules &raquo; Advanced Settings', 'wcsdm' ),
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
				<?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?>
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="<?php echo esc_attr( $this->id ); ?>-map-error" class="notice notice-error"></div>
				<div id="<?php echo esc_attr( $this->id ); ?>-map-wrapper" class="<?php echo esc_attr( $this->id ); ?>-map-wrapper"></div>
				<div id="<?php echo esc_attr( $this->id ); ?>-lat-lng-wrap">
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lat"><?php echo esc_html( 'Latitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lat" name="<?php echo esc_attr( $field_key ); ?>_lat" value="<?php echo esc_attr( $this->get_option( $key . '_lat' ) ); ?>" class="origin-coordinates" readonly></div>
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lng"><?php echo esc_html( 'Longitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lng" name="<?php echo esc_attr( $field_key ); ?>_lng" value="<?php echo esc_attr( $this->get_option( $key . '_lng' ) ); ?>" class="origin-coordinates" readonly></div>
				</div>
				<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				<div id="<?php echo esc_attr( $this->id ); ?>-map-spinner" class="spinner" style="visibility: visible;"></div>
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
		?>
		<tr valign="top" id="wcsdm-table-row-rates" class="wcsdm-table-row wcsdm-table-row-rates">
			<td class="wcsdm-table-col wcsdm-table-col-rates" colspan="2">
				<table id="wcsdm-table-rates" class="wc_input_table widefat wcsdm-table wcsdm-table-rates" cellspacing="0">
					<thead>
						<?php $this->generate_rate_row_heading(); ?>
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
						<?php $this->generate_rate_row_heading( 'bottom' ); ?>
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
	 * @param string $position Row heading position.
	 * @return void
	 */
	private function generate_rate_row_heading( $position = 'top' ) {
		$fields = $this->rates_fields( true );
		?>
		<?php if ( 'top' === $position ) : ?>
		<tr id="row-heading-top" class="row-heading row-heading-top">
			<td colspan="<?php echo esc_attr( count( $fields ) + 1 ); ?>" class="full-width-col">
				<strong><?php esc_html_e( 'Distance Rate Rules', 'wcsdm' ); ?></strong>
			</td>
		</tr>
		<?php endif; ?>
		<tr class="row-heading row-heading-both">
			<td class="col-checkbox">
				<div>
					<?php if ( 'top' === $position ) : ?>
						<input class="select-item" type="checkbox">
					<?php else : ?>
						<a href="#" class="add_row button button-primary"><?php esc_html_e( 'Add Row', 'wcsdm' ); ?></a>
						<a href="#" class="remove_rows button button-secondary delete" style="display: none"><?php esc_html_e( 'Remove Rows', 'wcsdm' ); ?></a>
					<?php endif; ?>
				</div>
			</td>
			<?php foreach ( $fields as $key => $col ) : ?>
				<?php if ( ! $col['advanced'] ) : ?>
				<td class="col-centered col-<?php echo esc_html( $key ); ?>">
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
		<?php if ( 'top' !== $position ) : ?>
		<tr id="row-heading-bottom" class="row-heading row-heading-bottom">
			<td colspan="<?php echo esc_attr( count( $fields ) + 1 ); ?>" class="full-width-col col-centered">
				<a href="#" class="add_row button button-primary button-large button-no-rates"><?php esc_html_e( 'Add Rate', 'wcsdm' ); ?></a>
			</td>
		</tr>
		<?php endif; ?>
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
		$fields = $this->rates_fields();
		?>
		<tr>
			<td class="col-checkbox">
				<div><input class="select-item" type="checkbox"></div>
			</td>
			<?php foreach ( $fields as $key => $col ) : ?>
				<?php
				$data_id    = 'woocommerce_' . $this->id . '_' . $key;
				$input_name = $field_key . '_' . $key;
				$value      = isset( $rate[ $key ] ) ? $rate[ $key ] : ( isset( $col['default'] ) ? $col['default'] : '' );
				switch ( $key ) {
					case 'distance':
						?>
						<td class="col-<?php echo esc_html( $key ); ?>">
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
						<td class="col-<?php echo esc_html( $key ); ?>">
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
						<td class="col-<?php echo esc_html( $key ); ?>">
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
						<td class="col-<?php echo esc_html( $key ); ?>">
							<a href="#" class="advanced-rate-settings"><span class="dashicons free-shipping-<?php echo esc_attr( $value ); ?>"></span></a>
							<input name="<?php echo esc_attr( $input_name ); ?>[]" type="hidden" value="<?php echo esc_attr( $value ); ?>" class="wcsdm-input wcsdm-input-<?php echo esc_attr( $key ); ?> <?php echo esc_attr( $data_id ); ?>" data-id="<?php echo esc_attr( $data_id ); ?>">
						</td>
						<?php
						break;
				}
				?>
			<?php endforeach; ?>
			<td class="col-advanced">
				<?php
				foreach ( $fields as $key => $col ) :
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
				<table id="wcsdm-table-advanced" class="form-table wcsdm-table wcsdm-table-advanced" cellspacing="0">
					<thead>
						<tr>
							<td colspan="2" class="full-width-col"><strong><?php echo wp_kses_post( $data['title'] ); ?></strong></td>
						</tr>
					</thead>
					<tbody>
						<?php $this->generate_settings_html( $this->rates_fields( false, true ) ); ?>
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
	 * @param bool $heading_only Is fields will be displayed in table heading only.
	 * @param bool $advanced_form Is fields will be displayed in advanced settings form.
	 * @return array
	 */
	public function rates_fields( $heading_only = false, $advanced_form = false ) {
		$fields = array(
			'shipping_label'  => array_merge( $this->instance_form_fields['title'], array(
				'class'       => 'wcsdm-input',
				'description' => $this->instance_form_fields['title']['description'] . ' ' . __( 'Leave blank to use the global title settings.', 'wcsdm' ),
				'default'     => '',
				'desc_tip'    => true,
				'advanced'    => true,
				'heading'     => false,
			) ),
			'distance'        => array(
				'type'              => 'number',
				'title'             => __( 'Maximum Distances', 'wcsdm' ),
				'class'             => 'wcsdm-input field-distance',
				'description'       => __( 'The maximum distances rule for the shipping rate. This input is required.', 'wcsdm' ),
				'desc_tip'          => true,
				'advanced'          => false,
				'heading'           => true,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'cost_type'       => array(
				'type'        => 'select',
				'title'       => __( 'Cost Type', 'wcsdm' ),
				'class'       => 'wcsdm-input',
				'default'     => 'flat',
				'options'     => array(
					'flat'     => __( 'Flat', 'wcsdm' ),
					'per_unit' => '',
				),
				'description' => __( 'Determine wether to use flat price or flexible price per distances unit.', 'wcsdm' ),
				'desc_tip'    => true,
				'advanced'    => false,
				'heading'     => true,
			),
			'class_0'         => array(
				'type'              => 'number',
				'title'             => __( 'Default Shipping Rate', 'wcsdm' ),
				'class'             => 'wcsdm-input',
				'description'       => __( 'The shipping rate within the distances range. This input is required.', 'wcsdm' ),
				'desc_tip'          => true,
				'advanced'          => false,
				'heading'           => true,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'base'            => array(
				'type'              => 'number',
				'title'             => __( 'Additional Cost', 'wcsdm' ),
				'class'             => 'wcsdm-input',
				'default'           => '0',
				'description'       => __( 'Surcharge that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'advanced'          => true,
				'heading'           => false,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'free'            => array(
				'type'        => 'select',
				'title'       => __( 'Free Shipping', 'wcsdm' ),
				'class'       => 'wcsdm-input',
				'default'     => 'no',
				'options'     => array(
					'no'      => __( 'Disable', 'wcsdm' ),
					'yes'     => __( 'Yes, enable as Free Shipping', 'wcsdm' ),
					'yes_alt' => __( 'Yes, enable as Free Shipping if any of rules below is matched', 'wcsdm' ),
				),
				'description' => __( 'Free shipping options within the distances range.', 'wcsdm' ),
				'desc_tip'    => true,
				'advanced'    => false,
				'heading'     => true,
			),
			'free_min_amount' => array(
				'type'              => 'number',
				'title'             => __( 'Rule #1: Minimum Order Amount', 'wcsdm' ),
				'class'             => 'wcsdm-input free-shipping-yes_alt',
				'description'       => __( 'The free shipping rule for minimum order amount. Leave blank to disable this rule. But at least one rule must be defined either by minimum order amount or minimum order quantity.', 'wcsdm' ),
				'desc_tip'          => true,
				'advanced'          => true,
				'heading'           => false,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'free_min_qty'    => array(
				'type'              => 'number',
				'title'             => __( 'Rule #2: Minimum Order Quantity', 'wcsdm' ),
				'class'             => 'wcsdm-input free-shipping-yes_alt',
				'description'       => __( 'The free shipping rule for minimum order quantity. Leave blank to disable this rule. But at least one rule must be defined either by minimum order amount or minimum order quantity.', 'wcsdm' ),
				'desc_tip'          => true,
				'advanced'          => true,
				'heading'           => false,
				'custom_attributes' => array(
					'min' => '0',
				),
			),
			'advanced'        => array(
				'type'        => 'link',
				'title'       => __( 'Advanced', 'wcsdm' ),
				'description' => __( 'Advanced Settings', 'wcsdm' ),
				'advanced'    => false,
				'heading'     => true,
			),
		);

		$shipping_classes = array();
		foreach ( WC()->shipping->get_shipping_classes() as $shipping_classes_key => $shipping_classes_value ) {
			$shipping_classes[ $shipping_classes_value->term_id ] = $shipping_classes_value;
		}

		if ( $shipping_classes ) {
			$new_fields = array();
			foreach ( $fields as $key => $field ) {
				$new_fields[ $key ] = $field;
				if ( 'class_0' === $key ) {
					foreach ( $shipping_classes as $class_id => $class_obj ) {
						$new_fields[ 'class_' . $class_id ] = array_merge(
							$field, array(
								// translators: %s is Product shipping class name.
								'title'       => sprintf( __( 'Shipping Rate for "%s" Shipping Class', 'wcsdm' ), $class_obj->name ),
								'description' => __( 'Shipping rate for specific product shipping class. This rate will override the default shipping rate defined above. Blank or zero value will be ignored.', 'wcsdm' ),
								'desc_tip'    => true,
								'advanced'    => true,
								'heading'     => false,
							)
						);
					}
				}
			}
			$fields = $new_fields;
		}

		if ( $heading_only ) {
			foreach ( $fields as $key => $field ) {
				if ( ! $field ) {
					unset( $field[ $key ] );
				}
			}
		}

		if ( $advanced_form ) {
			unset( $fields['advanced'] );
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
						case 'shipping_label':
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
					if ( is_numeric( $value['distance'] ) ) {
						throw new Exception( __( 'Maximum distance input field value must be greater than zero', 'wcsdm' ) );
					}
					throw new Exception( __( 'Maximum distance input field is required', 'wcsdm' ) );
				}
				if ( empty( $value['class_0'] ) ) {
					if ( is_numeric( $value['class_0'] ) ) {
						throw new Exception( __( 'Shipping rate input field value must be greater than zero', 'wcsdm' ) );
					}
					throw new Exception( __( 'Shipping rate input field is required', 'wcsdm' ) );
				}
				if ( 'yes_alt' === $value['free'] && empty( $value['free_min_amount'] ) && empty( $value['free_min_qty'] ) ) {
					throw new Exception( __( 'You must define at least one free shipping rule either by minimum order amount or minimum order quantity', 'wcsdm' ) );
				}
				$rates_filtered[ $value['distance'] ] = $value;
			}

			if ( empty( $rates_filtered ) ) {
				throw new Exception( __( 'Shipping rates table is empty', 'wcsdm' ) );
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

		$api_request = $this->api_request( $package );

		if ( ! $api_request ) {
			return;
		}

		try {
			$rate_data = $this->get_rate_by_distance( $api_request['distance'] );

			// Bail early if there is no rate found.
			if ( is_wp_error( $rate_data ) ) {
				throw new Exception( $rate_data->get_error_message() );
			}

			// Set shipping courier label.
			$label = empty( $rate_data['shipping_label'] ) ? $this->title : $rate_data['shipping_label'];
			if ( 'yes' === $this->show_distance && ! empty( $api_request['distance_text'] ) ) {
				$label = sprintf( '%s (%s)', $label, $api_request['distance_text'] );
			}

			// Check if free shipping.
			if ( 'no' !== $rate_data['free'] ) {
				$is_free_shipping = 'yes' === $rate_data['free'];

				if ( ! $is_free_shipping ) {
					// Check if free shipping by matc with rules defined.
					if ( strlen( $rate_data['free_min_amount'] ) && $woocommerce->cart->get_cart_contents_total() >= $rate_data['free_min_amount'] ) {
						$is_free_shipping = true;
					}

					// Check if free shipping by minimum order quantity.
					if ( strlen( $rate_data['free_min_qty'] ) && $woocommerce->cart->get_cart_contents_count() >= $rate_data['free_min_qty'] ) {
						$is_free_shipping = true;
					}
				}

				// Apply free shipping rate.
				if ( $is_free_shipping ) {
					// Register the rate.
					$this->register_rate(
						array(
							'id'        => $this->get_rate_id(),
							'label'     => $label,
							'cost'      => 0,
							'taxes'     => false,
							'package'   => $package,
							'meta_data' => $api_request,
						)
					);
					return;
				}
			}

			$cost_total              = 0;
			$cost_per_order          = 0;
			$cost_per_shipping_class = array();
			$cost_per_product        = array();
			$cost_per_item           = 0;

			foreach ( $package['contents'] as $hash => $item ) {
				$shipping_class_id = $item['data']->get_shipping_class_id();
				$product_id        = $item['data']->get_id();
				$default_rate      = isset( $rate_data['class_0'] ) ? $rate_data['class_0'] : false;

				$calculated_cost = isset( $rate_data[ 'class_' . $shipping_class_id ] ) ? $rate_data[ 'class_' . $shipping_class_id ] : false;

				if ( ! $calculated_cost && $default_rate ) {
					$calculated_cost = $default_rate;
				}

				if ( ! $calculated_cost ) {
					if ( is_numeric( $calculated_cost ) ) {
						throw new Exception( __( 'Use the free shipping option instead of set the shipping rate as zero', 'wcsdm' ) );
					}
					throw new Exception( __( 'Unable to calculate the shipping rate', 'wcsdm' ) );
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
						if ( ! isset( $cost_per_shipping_class[ $shipping_class_id ] ) ) {
							$cost_per_shipping_class[ $shipping_class_id ] = $calculated_cost;
						}
						if ( isset( $cost_per_shipping_class[ $shipping_class_id ] ) && $calculated_cost > $cost_per_shipping_class[ $shipping_class_id ] ) {
							$cost_per_shipping_class[ $shipping_class_id ] = $calculated_cost;
						}
						break;
					case 'per_product':
						if ( ! isset( $cost_per_product[ $product_id ] ) ) {
							$cost_per_product[ $product_id ] = $calculated_cost;
						}
						if ( isset( $cost_per_product[ $product_id ] ) && $calculated_cost > $cost_per_product[ $product_id ] ) {
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

			$rate = array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => $cost_total,
				'package'   => $package,
				'meta_data' => $api_request,
			);

			// Register the rate.
			$this->register_rate( $rate );
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), is_cart() || $this->is_calc_shipping() ? 'error' : 'notice' );
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

		// translators: %1$s distance value, %2$s distance unit.
		return new WP_Error( 'no_rates', sprintf( __( 'No shipping rates defined within distance range: %1$s %2$s', 'wcsdm' ), $distance, 'imperial' === $this->gmaps_api_units ? 'mi' : 'km' ) );
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @since    1.0.0
	 * @param array $package The cart content data.
	 * @throws Exception Throw error if validation not passed.
	 * @return mixed array of API response data, false on failure.
	 */
	private function api_request( $package ) {
		try {
			$destination_info = $this->get_destination_info( $package['destination'] );
			if ( empty( $destination_info ) ) {
				return false;
			}

			if ( empty( $this->gmaps_api_key ) ) {
				throw new Exception( __( 'API Key is empty', 'wcsdm' ) );
			}

			$origin_info = $this->get_origin_info( $package );
			if ( empty( $origin_info ) ) {
				throw new Exception( __( 'Origin info is empty', 'wcsdm' ) );
			}

			$cache_key = $this->id . '_api_request_' . md5(
				wp_json_encode(
					array(
						'destination_info' => $destination_info,
						'origin_info'      => $origin_info,
						'package'          => $package,
						'all_options'      => $this->all_options,
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

			$request_url_args = array(
				'key'          => rawurlencode( $this->gmaps_api_key ),
				'mode'         => rawurlencode( $this->gmaps_api_mode ),
				'avoid'        => is_string( $this->gmaps_api_avoid ) ? rawurlencode( $this->gmaps_api_avoid ) : '',
				'units'        => rawurlencode( $this->gmaps_api_units ),
				'language'     => rawurlencode( get_locale() ),
				'origins'      => rawurlencode( implode( ',', $origin_info ) ),
				'destinations' => rawurlencode( implode( ',', $destination_info ) ),
			);

			$request_url = add_query_arg( $request_url_args, $this->google_api_url );

			$this->show_debug( __( 'API Request URL', 'wcsdm' ) . ': ' . str_replace( rawurlencode( $this->gmaps_api_key ), '**********', $request_url ) );

			$data = $this->process_api_response( wp_remote_get( esc_url_raw( $request_url ) ) );

			// Try to make fallback request if no results found.
			if ( ! $data && 'yes' === $this->enable_fallback_request && ! empty( $destination_info['address_2'] ) ) {
				unset( $destination_info['address'] );
				$request_url_args['destinations'] = rawurlencode( implode( ',', $destination_info ) );

				$request_url = add_query_arg( $request_url_args, $this->google_api_url );

				$this->show_debug( __( 'API Fallback Request URL', 'wcsdm' ) . ': ' . str_replace( rawurlencode( $this->gmaps_api_key ), '**********', $request_url ) );

				$data = $this->process_api_response( wp_remote_get( esc_url_raw( $request_url ) ) );
			}

			if ( empty( $data ) ) {
				return false;
			}

			delete_transient( $cache_key ); // To make sure the transient data re-created, delete it first.
			set_transient( $cache_key, $data, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.

			return $data;
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), is_cart() || $this->is_calc_shipping() ? 'error' : 'notice' );
			return false;
		}
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
						'distance'      => $this->convert_distance( $element['distance']['value'] ),
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
				switch ( $this->prefered_route ) {
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

			$result = $results[0];

			// Rounds distance UP to the nearest integer.
			if ( 'yes' === $this->ceil_distance ) {
				$result['distance']      = ceil( $result['distance'] );
				$result['distance_text'] = $result['distance'] . preg_replace( '/[0-9\.,]/', '', $result['distance_text'] );
			}

			$result['response'] = $response_data;

			return $result;
		} catch ( Exception $e ) {
			$this->show_debug( $e->getMessage(), is_cart() || $this->is_calc_shipping() ? 'error' : 'notice' );
			return false;
		}
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
	 * @throws Exception Throw error if validation not passed.
	 * @param array $data Shipping destination data in associative array format: address, city, state, postcode, country.
	 * @return string
	 */
	private function get_destination_info( $data ) {
		$errors = array();

		$country_code = isset( $data['country'] ) && ! empty( $data['country'] ) ? $data['country'] : false;

		if ( ! $country_code ) {
			throw new Exception( __( 'Shipping destination country is not defined', 'wcsdm' ) );
		}

		$fields_default = WC()->countries->get_default_address_fields();

		$country_locale = WC()->countries->get_country_locale();
		$country_locale = isset( $country_locale[ $country_code ] ) ? $country_locale[ $country_code ] : $country_locale['default'];

		$fields_rules = array(
			'address'   => false,
			'address_2' => false,
			'city'      => true,
			'state'     => WC()->countries->get_states( $country_code ),
			'postcode'  => true,
			'country'   => true,
		);

		$destination_info = array();

		foreach ( $fields_rules as $field => $calculator_enable ) {
			$calculator_enable = apply_filters( 'woocommerce_shipping_calculator_enable_' . $field, $calculator_enable );
			if ( $this->is_calc_shipping() && ! $calculator_enable ) {
				continue;
			}

			$field_data = isset( $fields_default[ $field ] ) ? $fields_default[ $field ] : false;
			if ( empty( $field_data ) ) {
				continue;
			}

			if ( isset( $country_locale[ $field ] ) ) {
				$field_data = array_merge( $field_data, $country_locale[ $field ] );
			}

			if ( isset( $field_data['hidden'] ) && $field_data['hidden'] ) {
				continue;
			}

			$field_label = isset( $field_data['label'] ) ? $field_data['label'] : $field;
			$field_value = isset( $data[ $field ] ) ? $data[ $field ] : '';

			if ( empty( $field_value ) && $field_data['required'] ) {
				// translators: %s is shipping destination field label.
				array_push( $errors, sprintf( __( 'Shipping destination field is empty: %s', 'wcsdm' ), $field_label ) );
				continue;
			}

			if ( ! empty( $field_value ) ) {
				switch ( $field ) {
					case 'postcode':
						$poscode_valid = WC_Validation::is_postcode( $field_value, $country_code );
						if ( $poscode_valid ) {
							$poscode_valid = $this->validate_postcode( $field_value, $country_code );
						}

						if ( ! $poscode_valid ) {
							// translators: %s is shipping destination field label.
							array_push( $errors, sprintf( __( 'Shipping destination field is invalid: %s', 'wcsdm' ), $field_label ) );
						}

						if ( $poscode_valid ) {
							$destination_info[ $field ] = $field_value;
						}
						break;

					case 'state':
						if ( isset( WC()->countries->states[ $country_code ][ $field_value ] ) ) {
							$field_value = WC()->countries->states[ $country_code ][ $field_value ];
						}
						$destination_info[ $field ] = $field_value;
						break;

					case 'country':
						if ( isset( WC()->countries->countries[ $field_value ] ) ) {
							$field_value = WC()->countries->countries[ $field_value ];
						}
						$destination_info[ $field ] = $field_value;
						break;

					default:
						$destination_info[ $field ] = $field_value;
						break;
				}
			}
		}

		error_log(print_r($destination_info, true));

		if ( $errors ) {
			foreach ( $errors as $error ) {
				$this->show_debug( $error, is_cart() || $this->is_calc_shipping() ? 'error' : 'notice' );
			}
			return false;
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
	 * Check if current request is shipping calculator form.
	 *
	 * @return bool
	 */
	private function is_calc_shipping() {
		if ( isset( $_POST['calc_shipping'], $_POST['woocommerce-shipping-calculator-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-shipping-calculator-nonce'] ) ), 'woocommerce-shipping-calculator' ) ) {
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
	private function convert_distance( $meters ) {
		return ( 'metric' === $this->gmaps_api_units ) ? $this->convert_distance_to_km( $meters ) : $this->convert_distance_to_mi( $meters );
	}

	/**
	 * Convert Meters to Miles
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_distance_to_mi( $meters ) {
		return wc_format_decimal( ( $meters * 0.000621371 ), 1 );
	}

	/**
	 * Convert Meters to Kilometres
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_distance_to_km( $meters ) {
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
	private function shortest_duration_results( $a, $b ) {
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
	private function longest_duration_results( $a, $b ) {
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
	private function shortest_distance_results( $a, $b ) {
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
	private function longest_distance_results( $a, $b ) {
		if ( $a['distance'] === $b['distance'] ) {
			return 0;
		}
		return ( $a['distance'] > $b['distance'] ) ? -1 : 1;
	}

	/**
	 * Validate postal code
	 *
	 * @since    1.4.7
	 * @param array $postcode Postal code to validate.
	 * @param array $country_code Country code.
	 * @return bool
	 */
	private function validate_postcode( $postcode, $country_code ) {
		$patterns = array(
			'GB' => 'GIR[ ]?0AA|((AB|AL|B|BA|BB|BD|BH|BL|BN|BR|BS|BT|CA|CB|CF|CH|CM|CO|CR|CT|CV|CW|DA|DD|DE|DG|DH|DL|DN|DT|DY|E|EC|EH|EN|EX|FK|FY|G|GL|GY|GU|HA|HD|HG|HP|HR|HS|HU|HX|IG|IM|IP|IV|JE|KA|KT|KW|KY|L|LA|LD|LE|LL|LN|LS|LU|M|ME|MK|ML|N|NE|NG|NN|NP|NR|NW|OL|OX|PA|PE|PH|PL|PO|PR|RG|RH|RM|S|SA|SE|SG|SK|SL|SM|SN|SO|SP|SR|SS|ST|SW|SY|TA|TD|TF|TN|TQ|TR|TS|TW|UB|W|WA|WC|WD|WF|WN|WR|WS|WV|YO|ZE)(\d[\dA-Z]?[ ]?\d[ABD-HJLN-UW-Z]{2}))|BFPO[ ]?\d{1,4}',
			'JE' => 'JE\d[\dA-Z]?[ ]?\d[ABD-HJLN-UW-Z]{2}',
			'GG' => 'GY\d[\dA-Z]?[ ]?\d[ABD-HJLN-UW-Z]{2}',
			'IM' => 'IM\d[\dA-Z]?[ ]?\d[ABD-HJLN-UW-Z]{2}',
			'US' => '\d{5}([ \-]\d{4})?',
			'CA' => '[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ ]?\d[ABCEGHJ-NPRSTV-Z]\d',
			'DE' => '\d{5}',
			'JP' => '\d{3}-\d{4}',
			'FR' => '\d{2}[ ]?\d{3}',
			'AU' => '\d{4}',
			'IT' => '\d{5}',
			'CH' => '\d{4}',
			'AT' => '\d{4}',
			'ES' => '\d{5}',
			'NL' => '\d{4}[ ]?[A-Z]{2}',
			'BE' => '\d{4}',
			'DK' => '\d{4}',
			'SE' => '\d{3}[ ]?\d{2}',
			'NO' => '\d{4}',
			'BR' => '\d{5}[\-]?\d{3}',
			'PT' => '\d{4}([\-]\d{3})?',
			'FI' => '\d{5}',
			'AX' => '22\d{3}',
			'KR' => '\d{3}[\-]\d{3}',
			'CN' => '\d{6}',
			'TW' => '\d{3}(\d{2})?',
			'SG' => '\d{6}',
			'DZ' => '\d{5}',
			'AD' => 'AD\d{3}',
			'AR' => '([A-HJ-NP-Z])?\d{4}([A-Z]{3})?',
			'AM' => '(37)?\d{4}',
			'AZ' => '\d{4}',
			'BH' => '((1[0-2]|[2-9])\d{2})?',
			'BD' => '\d{4}',
			'BB' => '(BB\d{5})?',
			'BY' => '\d{6}',
			'BM' => '[A-Z]{2}[ ]?[A-Z0-9]{2}',
			'BA' => '\d{5}',
			'IO' => 'BBND 1ZZ',
			'BN' => '[A-Z]{2}[ ]?\d{4}',
			'BG' => '\d{4}',
			'KH' => '\d{5}',
			'CV' => '\d{4}',
			'CL' => '\d{7}',
			'CR' => '\d{4,5}|\d{3}-\d{4}',
			'HR' => '\d{5}',
			'CY' => '\d{4}',
			'CZ' => '\d{3}[ ]?\d{2}',
			'DO' => '\d{5}',
			'EC' => '([A-Z]\d{4}[A-Z]|(?:[A-Z]{2})?\d{6})?',
			'EG' => '\d{5}',
			'EE' => '\d{5}',
			'FO' => '\d{3}',
			'GE' => '\d{4}',
			'GR' => '\d{3}[ ]?\d{2}',
			'GL' => '39\d{2}',
			'GT' => '\d{5}',
			'HT' => '\d{4}',
			'HN' => '(?:\d{5})?',
			'HU' => '\d{4}',
			'IS' => '\d{3}',
			'IN' => '\d{6}',
			'ID' => '\d{5}',
			'IL' => '\d{5}',
			'JO' => '\d{5}',
			'KZ' => '\d{6}',
			'KE' => '\d{5}',
			'KW' => '\d{5}',
			'LA' => '\d{5}',
			'LV' => '\d{4}',
			'LB' => '(\d{4}([ ]?\d{4})?)?',
			'LI' => '(948[5-9])|(949[0-7])',
			'LT' => '\d{5}',
			'LU' => '\d{4}',
			'MK' => '\d{4}',
			'MY' => '\d{5}',
			'MV' => '\d{5}',
			'MT' => '[A-Z]{3}[ ]?\d{2,4}',
			'MU' => '(\d{3}[A-Z]{2}\d{3})?',
			'MX' => '\d{5}',
			'MD' => '\d{4}',
			'MC' => '980\d{2}',
			'MA' => '\d{5}',
			'NP' => '\d{5}',
			'NZ' => '\d{4}',
			'NI' => '((\d{4}-)?\d{3}-\d{3}(-\d{1})?)?',
			'NG' => '(\d{6})?',
			'OM' => '(PC )?\d{3}',
			'PK' => '\d{5}',
			'PY' => '\d{4}',
			'PH' => '\d{4}',
			'PL' => '\d{2}-\d{3}',
			'PR' => '00[679]\d{2}([ \-]\d{4})?',
			'RO' => '\d{6}',
			'RU' => '\d{6}',
			'SM' => '4789\d',
			'SA' => '\d{5}',
			'SN' => '\d{5}',
			'SK' => '\d{3}[ ]?\d{2}',
			'SI' => '\d{4}',
			'ZA' => '\d{4}',
			'LK' => '\d{5}',
			'TJ' => '\d{6}',
			'TH' => '\d{5}',
			'TN' => '\d{4}',
			'TR' => '\d{5}',
			'TM' => '\d{6}',
			'UA' => '\d{5}',
			'UY' => '\d{5}',
			'UZ' => '\d{6}',
			'VA' => '00120',
			'VE' => '\d{4}',
			'ZM' => '\d{5}',
			'AS' => '96799',
			'CC' => '6799',
			'CK' => '\d{4}',
			'RS' => '\d{6}',
			'ME' => '8\d{4}',
			'CS' => '\d{5}',
			'YU' => '\d{5}',
			'CX' => '6798',
			'ET' => '\d{4}',
			'FK' => 'FIQQ 1ZZ',
			'NF' => '2899',
			'FM' => '(9694[1-4])([ \-]\d{4})?',
			'GF' => '9[78]3\d{2}',
			'GN' => '\d{3}',
			'GP' => '9[78][01]\d{2}',
			'GS' => 'SIQQ 1ZZ',
			'GU' => '969[123]\d([ \-]\d{4})?',
			'GW' => '\d{4}',
			'HM' => '\d{4}',
			'IQ' => '\d{5}',
			'KG' => '\d{6}',
			'LR' => '\d{4}',
			'LS' => '\d{3}',
			'MG' => '\d{3}',
			'MH' => '969[67]\d([ \-]\d{4})?',
			'MN' => '\d{6}',
			'MP' => '9695[012]([ \-]\d{4})?',
			'MQ' => '9[78]2\d{2}',
			'NC' => '988\d{2}',
			'NE' => '\d{4}',
			'VI' => '008(([0-4]\d)|(5[01]))([ \-]\d{4})?',
			'PF' => '987\d{2}',
			'PG' => '\d{3}',
			'PM' => '9[78]5\d{2}',
			'PN' => 'PCRN 1ZZ',
			'PW' => '96940',
			'RE' => '9[78]4\d{2}',
			'SH' => '(ASCN|STHL) 1ZZ',
			'SJ' => '\d{4}',
			'SO' => '\d{5}',
			'SZ' => '[HLMS]\d{3}',
			'TC' => 'TKCA 1ZZ',
			'WF' => '986\d{2}',
			'XK' => '\d{5}',
			'YT' => '976\d{2}',
		);

		$pattern = isset( $patterns[ $country_code ] ) ? $patterns[ $country_code ] : false;

		if ( ! $pattern ) {
			return true;
		}

		return preg_match( "/^$pattern$/", $postcode, $matches );
	}

	/**
	 * Show debug info
	 *
	 * @since    1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $type The type of notice.
	 * @return void
	 */
	private function show_debug( $message, $type = 'notice' ) {
		$debug_mode = 'yes' === get_option( 'woocommerce_shipping_debug_mode', 'no' );

		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		if ( $debug_mode && ! defined( 'WOOCOMMERCE_CHECKOUT' ) && ! defined( 'WC_DOING_AJAX' ) && ! wc_has_notice( $message ) ) {
			wc_add_notice( 'WCSDM => ' . $message, $type );
		}
	}
}
