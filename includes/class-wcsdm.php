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
	 * All debugs data
	 *
	 * @since    1.4.02
	 * @var array
	 */
	private $_debugs = array();

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
			'gmaps_api_key_dummy'     => array(
				'title'       => __( 'Google API Key', 'wcsdm' ),
				'type'        => 'api_key',
				'description' => __( 'This plugin makes use of the Google Maps and Google Distance Matrix APIs. <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> obtain a Google API Key.', 'wcsdm' ),
				'default'     => '',
			),
			'gmaps_api_key'           => array(
				'title'       => __( 'Google API Key', 'wcsdm' ),
				'type'        => 'custom',
				'description' => __( 'This plugin makes use of the Google Maps and Google Distance Matrix APIs. <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> obtain a Google API Key.', 'wcsdm' ),
				'default'     => '',
			),
			'origin_lat'              => array(
				'title' => __( 'Store Location Latitude', 'wcsdm' ),
				'type'  => 'custom',
			),
			'origin_lng'              => array(
				'title' => __( 'Store Location Logitude', 'wcsdm' ),
				'type'  => 'custom',
			),
			'store_location'          => array(
				'type'        => 'store_location',
				'title'       => __( 'Store Location', 'wcsdm' ),
				'description' => __( 'This plugin makes use of the Google Maps and Google Distance Matrix APIs. <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> obtain a Google API Key.', 'wcsdm' ),
				'default'     => '',
				'desc_tip'    => true,
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
				'type'  => 'table_rates',
				'title' => __( 'Distance Rate Rules', 'wcsdm' ),
			),
			'table_advanced'          => array(
				'type'  => 'table_advanced',
				'title' => __( 'Distance Rate Rules &raquo; Advanced Settings', 'wcsdm' ),
			),
			'js_template'             => array(
				'type' => 'js_template',
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
				<div id="<?php echo esc_attr( $this->id ); ?>-map-wrapper" class="<?php echo esc_attr( $this->id ); ?>-map-wrapper"></div>
				<div id="<?php echo esc_attr( $this->id ); ?>-lat-lng-wrap">
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lat"><?php echo esc_html( 'Latitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lat" name="<?php echo esc_attr( $field_key ); ?>_lat" value="<?php echo esc_attr( $this->get_option( $key . '_lat' ) ); ?>" class="origin-coordinates" readonly></div>
					<div><label for="<?php echo esc_attr( $field_key ); ?>_lng"><?php echo esc_html( 'Longitude', 'wcsdm' ); ?></label><input type="text" id="<?php echo esc_attr( $field_key ); ?>_lng" name="<?php echo esc_attr( $field_key ); ?>_lng" value="<?php echo esc_attr( $this->get_option( $key . '_lng' ) ); ?>" class="origin-coordinates" readonly></div>
				</div>
				<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				<div id="<?php echo esc_attr( $this->id ); ?>-map-spinner" class="spinner" style="visibility: visible;"></div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate API Key Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
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
		<tr valign="top" id="wcsdm-row-api-key" class="wcsdm-row wcsdm-row--hidden wcsdm-row-api-key">
			<td class="wcsdm-col wcsdm-col-map-picker" colspan="2">
				<table id="wcsdm-table-map-picker" class="wcsdm-table wcsdm-table-map-picker" cellspacing="0">
					<thead>
						<tr>
							<th colspan="2" class="full-width-col"><?php esc_html_e( 'Store Location Picker', 'wcsdm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<th scope="row" class="titledesc">
								<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
							</th>
							<td class="forminp">
								<fieldset>
									<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
									<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" id="<?php echo esc_attr( $field_key ); ?>" value="" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> data-title="<?php echo esc_attr( $data['title'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
								</fieldset>
							</td>
						</tr>
						<tr id="wcsdm-row-map-picker-canvas">
							<td colspan="2"><div id="wcsdm-map-picker-canvas"></div><div id="wcsdm-map-picker-instruction"><?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?></div></td>
						</tr>
						<tr id="wcsdm-row-map-picker-lat-lng">
							<td colspan="2"><div id="map-picker-lat-lng"></div></td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate custom settings field.
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_custom_html( $key, $data ) {
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
		<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" data-title="<?php echo esc_attr( $data['title'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate store location picker settings field.
	 *
	 * @since 1.2.4
	 * @param string $key Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_store_location_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$lat_key   = $this->get_field_key( 'origin_lat' );
		$lng_key   = $this->get_field_key( 'origin_lng' );

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
		<tr valign="top" id="wcsdm-row-store-location" class="wcsdm-row wcsdm-row-store-location">
			<th scope="row" class="titledesc">
				<?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?>
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="wcsdm-col-store-location"></div>
				<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
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
		<tr>
			<td colspan="2" class="full-width-col"><strong><?php echo wp_kses_post( $data['title'] ); ?></strong></td>
		</tr>
		<tr valign="top" id="wcsdm-row-rates" class="wcsdm-row wcsdm-row-rates">
			<td class="wcsdm-col wcsdm-col-rates" colspan="2">
				<table id="wcsdm-table-rates" class="wc_input_table widefat wcsdm-table wcsdm-table-rates" cellspacing="0">
					<thead>
						<?php $this->generate_rate_row_heading(); ?>
					</thead>
					<tbody>
						<?php
						if ( $this->table_rates ) :
							foreach ( $this->table_rates as $table_rate ) :
								$this->generate_rate_row( $table_rate );
							endforeach;
						endif;
						?>
					</tbody>
					<tfoot>
						<?php $this->generate_rate_row_heading( 'bottom' ); ?>
					</tfoot>
				</table>
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
		$fields = $this->rates_fields( 'dummy' );
		?>
		<tr>
			<?php foreach ( $fields as $key => $col ) : ?>
				<td class="col-centered col-<?php echo esc_html( $key ); ?>">
					<?php if ( 'top' === $position && ! empty( $col['description'] ) ) : ?>
						<span class="tooltip" data-tooltip="<?php echo esc_attr( $col['description'] ); ?>">
					<?php endif; ?>
					<strong><?php echo esc_html( $col['title'] ); ?></strong>
					<?php if ( 'top' === $position && ! empty( $col['description'] ) ) : ?>
						</span>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
		</tr>
		<?php
	}

	/**
	 * Generate table rate columns
	 *
	 * @param array $rate Table rate data.
	 * @return void
	 */
	private function generate_rate_row( $rate = array() ) {
		$fields = $this->rates_fields( 'dummy' );
		?>
		<tr>
			<?php
			foreach ( $fields as $key => $field ) {

				if ( 'advanced' === $key ) {
					$field['rate'] = $rate;
				}

				$type  = $this->get_field_type( $field );
				$value = isset( $rate[ $key ] ) ? $rate[ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' );

				$html = '';

				if ( method_exists( $this, 'generate_' . $type . '_html' ) ) {
					$html .= $this->{'generate_' . $type . '_html'}( $key, $field );
				} else {
					$html .= $this->generate_text_html( $key, $field );
				}

				preg_match( '/<td(\s*)class="forminp">(.*)<\/td>/s', $html, $matches );

				echo '<td class="col-' . $key . '">'; // WPCS: XSS ok.
				if ( 'advanced' === $key ) {
					echo $html; // WPCS: XSS ok.
				} else {
					$output  = count( $matches ) === 3 && ! empty( $matches[2] ) ? $matches[2] : $html;
					$find    = array( 'value=""', 'id=', 'name=' );
					$replace = array( 'value="' . $value . '"', 'data-id=', 'data-name=' );
					echo str_replace( $find, $replace, $output ); // WPCS: XSS ok.
				}
				echo '</td>'; // WPCS: XSS ok.
			}
			?>
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
		<tr valign="top" id="wcsdm-row-advanced" class="wcsdm-row wcsdm-row--hidden wcsdm-row-advanced">
			<td class="wcsdm-col wcsdm-col-advanced" colspan="2">
				<table id="wcsdm-table-advanced" class="wcsdm-table wcsdm-table-advanced" cellspacing="0">
					<thead>
						<tr>
							<td colspan="2" class="full-width-col"><strong><?php echo wp_kses_post( $data['title'] ); ?></strong></td>
						</tr>
					</thead>
					<tbody>
						<?php
						$find    = array( 'id=', 'name=' );
						$replace = array( 'data-id=', 'data-name=' );
						$output  = $this->generate_settings_html( $this->rates_fields( 'advanced' ), false );
						echo str_replace( $find, $replace, $output ); // WPCS: XSS ok.
						?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate delete rate button
	 *
	 * @since 1.2.4
	 * @param string $key  Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_delete_link_html( $key, $data ) {
		$defaults = array(
			'title'       => '',
			'desc_tip'    => false,
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<a href="#" class="btn-delete-rate" title="<?php echo esc_attr( $data['description'] ); ?>"><span class="dashicons dashicons-trash"></span></a>
		<?php
		return ob_get_clean();

	}
	/**
	 * Generate advanced settings form
	 *
	 * @since 1.2.4
	 * @param string $key  Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_advanced_link_html( $key, $data ) {
		$defaults = array(
			'title'       => '',
			'desc_tip'    => false,
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );
		$rate = isset( $data['rate'] ) ? $data['rate'] : array();

		ob_start();

		foreach ( $this->rates_fields( 'hidden' ) as $field_key => $field ) {
			$value = isset( $rate[ $field_key ] ) ? $rate[ $field_key ] : ( isset( $field['default'] ) ? $field['default'] : '' );

			$html = $this->generate_text_html(
				$field_key, array_merge(
					$field,
					array(
						'type' => 'hidden',
					)
				)
			);

			preg_match( '/<input(.*)name="([^"]+)"(.*)\/>/s', $html, $matches );

			if ( count( $matches ) === 4 && ! empty( $matches[0] ) && ! empty( $matches[2] ) ) {
				$find    = array( 'name="' . $matches[2] . '"', 'id=', 'value=""' );
				$replace = array( 'name="' . $matches[2] . '[]"', 'data-id=', 'value="' . $value . '"' );
				echo str_replace( $find, $replace, $matches[0] ); // WPCS: XSS ok.
			} else {
				echo $html; // WPCS: XSS ok.
			}
		}
		?>
		<a href="#" class="wcsdm-btn-advanced-link" title="<?php echo esc_attr( $data['description'] ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate JS template
	 *
	 * @since 1.4.8
	 */
	public function generate_js_template_html() {
		ob_start();
		?>
		<script type="text/template" id="tmpl-rates-list-input-table-row">
			<?php $this->generate_rate_row(); ?>
		</script>
		<script type="text/template" id="tmpl-wcsdm-error">
			<div id="wcsdm-error" class="notice notice-error"><strong class="error-message">{{data.title}}</strong><hr />{{{data.content}}}</div>
		</script>
		<script type="text/html" id="tmpl-wcsdm-map-search">
			<input id="wcsdm-map-search" class="wcsdm-map-search controls" type="text" placeholder="<?php echo esc_attr( __( 'Search your store location', 'wcsdm' ) ); ?>" autocomplete="off" />
		</script>
		<script type="text/html" id="tmpl-wcsdm-lat-lng-table">
			<table class="wcsdm-table wcsdm-table-store-location">
				<thead>
					<tr>
						<td><?php esc_html_e( 'Latitude', 'wcsdm' ); ?></td>
						<td><?php esc_html_e( 'Longitude', 'wcsdm' ); ?></td>
						<# if ( !data.hideButton ) { #>
						<td class="col-btn-select-map">&nbsp;</td>
						<# } #>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<input class="input-text regular-input" type="text" id="<?php echo esc_attr( $this->get_field_key( 'origin_lat_dummy' ) ); ?>" value="{{data.origin_lat}}" placeholder="<?php esc_attr_e( 'Latitude', 'wcsdm' ); ?>" data-title="<?php esc_attr_e( 'Latitude', 'wcsdm' ); ?>" readonly="readonly" />
						</td>
						<td>
							<input class="input-text regular-input" type="text" id="<?php echo esc_attr( $this->get_field_key( 'origin_lng_dummy' ) ); ?>" value="{{data.origin_lng}}" placeholder="<?php esc_attr_e( 'Longitude', 'wcsdm' ); ?>" data-title="<?php esc_attr_e( 'Longitude', 'wcsdm' ); ?>" readonly="readonly" />
						</td>
						<# if ( !data.hideButton ) { #>
						<td class="col-btn-map-picker">
							<button id="wcsdm-btn-map-picker" class="button button-primary button-large"><span class="dashicons dashicons-location-alt"></span></button>
						</td>
						<# } #>
					</tr>
				</tbody>
			</table>
		</script>
		<script type="text/template" id="tmpl-wcsdm-buttons-footer-primary">
			<div id="wcsdm-buttons-footer-primary" class="wcsdm-buttons-footer wcsdm-buttons-footer--primary">
				<button id="wcsdm-btn-primary-add-rate" class="button button-primary button-large"><span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add New Rate', 'wcsdm' ); ?></button>
				<button id="wcsdm-btn-primary-save-changes" class="button button-primary button-large"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Save Changes', 'wcsdm' ); ?></button>
			</div>
		</script>
		<script type="text/template" id="tmpl-wcsdm-buttons-footer-advanced">
			<div id="wcsdm-buttons-footer-advanced" class="wcsdm-buttons-footer wcsdm-buttons-footer--advanced">
				<button id="{{data.id_cancel}}" class="button button-primary button-large"><span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Cancel', 'wcsdm' ); ?></button>
				<button id="{{data.id_apply}}" class="button button-primary button-large"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Apply Changes', 'wcsdm' ); ?></button>
			</div>
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Populate rate fields
	 *
	 * @since    1.4.2
	 *
	 * @param bool $context Field context as filter.
	 * @return array
	 */
	public function rates_fields( $context = '' ) {
		$class_0_field = array(
			'type'              => 'number',
			'title'             => 'advanced' === $context ? __( 'Default Shipping Rate', 'wcsdm' ) : __( 'Shipping Rate', 'wcsdm' ),
			'description'       => __( 'The shipping rate within the distances range. This input is required.', 'wcsdm' ),
			'desc_tip'          => true,
			'required'          => true,
			'default'           => '0',
			'custom_attributes' => array(
				'min' => '0',
			),
			'context'           => array( 'advanced', 'dummy', 'hidden' ),
			'cost_field'        => true,
		);

		$fields = array(
			'shipping_label' => array_merge(
				$this->instance_form_fields['title'], array(
					'description' => $this->instance_form_fields['title']['description'] . ' ' . __( 'Leave blank to use the global title settings.', 'wcsdm' ),
					'default'     => '',
					'desc_tip'    => true,
					'context'     => array( 'advanced', 'hidden' ),
				)
			),
			'distance'       => array(
				'type'              => 'number',
				'title'             => __( 'Max. Distances', 'wcsdm' ),
				'description'       => __( 'The maximum distances rule for the shipping rate. This input is required and the value must be unique.', 'wcsdm' ),
				'desc_tip'          => true,
				'required'          => true,
				'custom_attributes' => array(
					'min' => '1',
				),
				'context'           => array( 'advanced', 'dummy', 'hidden' ),
			),
			'min_order_amt'  => array(
				'type'              => 'number',
				'title'             => __( 'Min. Order Amount', 'wcsdm' ),
				'description'       => __( 'The free shipping rule for minimum order amount. Leave blank to disable this rule. But at least one rule must be defined either by minimum order amount or minimum order quantity.', 'wcsdm' ),
				'desc_tip'          => true,
				'required'          => true,
				'default'           => 0,
				'custom_attributes' => array(
					'min' => '0',
				),
				'context'           => array( 'advanced', 'dummy', 'hidden' ),
			),
			'min_order_qty'  => array(
				'type'              => 'number',
				'title'             => __( 'Min. Order Quantity', 'wcsdm' ),
				'description'       => __( 'The free shipping rule for minimum order quantity. Leave blank to disable this rule. But at least one rule must be defined either by minimum order amount or minimum order quantity.', 'wcsdm' ),
				'desc_tip'          => true,
				'required'          => true,
				'default'           => 0,
				'custom_attributes' => array(
					'min' => '0',
				),
				'context'           => array( 'advanced', 'dummy', 'hidden' ),
			),
			'cost_type'      => array(
				'type'        => 'select',
				'title'       => __( 'Calculation Method', 'wcsdm' ),
				'default'     => 'flat',
				'options'     => array(
					'flat'     => __( 'Flat', 'wcsdm' ),
					'per_unit' => __( 'Per Unit', 'wcsdm' ),
					'formula'  => __( 'Formula', 'wcsdm' ),
				),
				'description' => __( 'Determine wether to use flat price or flexible price per distances unit.', 'wcsdm' ),
				'desc_tip'    => true,
				'required'    => true,
				'context'     => array( 'advanced', 'dummy', 'hidden' ),
			),
			'class_0'        => $class_0_field,
			'cost_class'     => array(
				'type'        => 'select',
				'title'       => __( 'Rate per Shipping Class', 'wcsdm' ),
				'default'     => 'no',
				'options'     => array(
					'no'  => __( 'Disable', 'wcsdm' ),
					'yes' => __( 'Enable', 'wcsdm' ),
				),
				'description' => __( 'Determine wether to use different rate per product shipping class.', 'wcsdm' ),
				'desc_tip'    => true,
				'required'    => true,
				'context'     => array( 'advanced', 'hidden' ),
			),
			'base'           => array(
				'type'              => 'number',
				'title'             => __( 'Additional Cost', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Surcharge that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'required'          => true,
				'custom_attributes' => array(
					'min' => '0',
				),
				'context'           => array( 'advanced', 'hidden' ),
			),
			'advanced'       => array(
				'type'    => 'advanced_link',
				'title'   => __( 'Advanced', 'wcsdm' ),
				'context' => array( 'dummy' ),
			),
			'delete'         => array(
				'type'    => 'delete_link',
				'title'   => __( 'Delete', 'wcsdm' ),
				'context' => array( 'dummy' ),
			),
		);

		foreach ( $this->get_shipping_classes() as $class_id => $class_obj ) {
			$fields = $this->array_insert_after(
				$fields, 'cost_class', array(
					'class_' . $class_id => array_merge(
						$class_0_field,
						array(
							// translators: %s is Product shipping class name.
							'title'       => sprintf( __( '"%s" Class Shipping Rate', 'wcsdm' ), $class_obj->name ),
							'description' => __( 'Shipping rate for specific product shipping class. This rate will override the default shipping rate defined above. Blank or zero value will be ignored.', 'wcsdm' ),
							'context'     => array( 'advanced', 'hidden' ),
							'show_if'     => array( 'cost_class' => array( 'yes' ) ),
						)
					),
				)
			);
		}

		$populated_fields = array();

		foreach ( $fields as $key => $field ) {
			if ( ! empty( $context ) && ! in_array( $context, $field['context'], true ) ) {
				continue;
			}

			$field_classes = array(
				'wcsdm-rate-field',
				'wcsdm-rate-field--' . $key,
				'wcsdm-rate-field--' . $field['type'],
			);

			if ( ! empty( $context ) ) {
				$field_classes[] = 'wcsdm-rate-field--' . $context;
				$field_classes[] = 'wcsdm-rate-field--' . $context . '--' . $key;
				$field_classes[] = 'wcsdm-rate-field--' . $context . '--' . $field['type'];
			}

			if ( ! empty( $field['cost_field'] ) ) {
				$field_classes[] = 'wcsdm-cost-field';
				$field_classes[] = 'wcsdm-cost-field--' . $key;
				$field_classes[] = 'wcsdm-cost-field--' . $field['type'];
			}

			if ( ! empty( $field['class'] ) ) {
				$field_classes[] = $field['class'];
			}

			$custom_attributes = ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ? $field['custom_attributes'] : array();

			$populated_fields[ $key ] = array_merge(
				$field, array(
					'class'             => implode( $field_classes, ' ' ),
					'custom_attributes' => array_merge(
						$custom_attributes, array(
							'data-key'        => $key,
							'data-title'      => isset( $field['title'] ) ? $field['title'] : '',
							'data-type'       => isset( $field['type'] ) ? $field['type'] : '',
							'data-options'    => isset( $field['options'] ) ? wp_json_encode( $field['options'] ) : '{}',
							'data-required'   => ! empty( $field['required'] ) ? true : false,
							'data-cost_field' => ! empty( $field['cost_field'] ) ? true : false,
							'data-show_if'    => ! empty( $field['show_if'] ) && is_array( $field['show_if'] ) ? wp_json_encode( $field['show_if'] ) : false,
							'data-hide_if'    => ! empty( $field['hide_if'] ) && is_array( $field['hide_if'] ) ? wp_json_encode( $field['hide_if'] ) : false,
						)
					),
				)
			);
		}

		return $populated_fields;
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

			$api_response = $this->api_request(
				array(
					'origin_info'      => array( WCSDM_TEST_ORIGIN_LAT, WCSDM_TEST_ORIGIN_LNG ),
					'destination_info' => array( WCSDM_TEST_DESTINATION_LAT, WCSDM_TEST_DESTINATION_LNG ),
				), false, true
			);

			if ( is_wp_error( $api_response ) ) {
				throw new Exception( $api_response->get_error_message() );
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

			foreach ( $this->rates_fields( 'hidden' ) as $data_key => $field ) {
				$post_data_key   = $this->get_field_key( $data_key );
				$post_data_value = isset( $post_data[ $post_data_key ] ) ? $post_data[ $post_data_key ] : array();

				foreach ( $post_data_value as $index => $row_value ) {
					$ignore_field = false;
					$show_if      = ! empty( $field['show_if'] ) && is_array( $field['show_if'] ) ? $field['show_if'] : false;
					$hide_if      = ! empty( $field['hide_if'] ) && is_array( $field['hide_if'] ) ? $field['hide_if'] : false;

					if ( $show_if ) {
						foreach ( $show_if as $show_if_key => $show_if_value ) {
							$post_data_show_if_key   = $this->get_field_key( $show_if_key );
							$post_data_show_if_value = isset( $post_data[ $post_data_show_if_key ][ $index ] ) ? $post_data [ $post_data_show_if_key ][ $index ] : null;

							if ( ! in_array( $post_data_show_if_value, $show_if_value, true ) ) {
								$ignore_field = true;
								break;
							}
						}
					}

					if ( $hide_if ) {
						foreach ( $hide_if as $hide_if_key => $hide_if_value ) {
							$post_data_hide_if_key   = $this->get_field_key( $hide_if_key );
							$post_data_hide_if_value = isset( $post_data[ $post_data_hide_if_key ][ $index ] ) ? $post_data [ $post_data_hide_if_key ][ $index ] : null;

							if ( in_array( $post_data_hide_if_value, $hide_if_value, true ) ) {
								$ignore_field = true;
								break;
							}
						}
					}

					if ( ! $ignore_field ) {
						$is_required = isset( $field['required'] ) ? $field['required'] : false;

						if ( $is_required && ! strlen( $row_value ) ) {
							// translators: %s is field name.
							throw new Exception( sprintf( __( '%s field is required', 'wcsdm' ), $field['title'] ) );
						}

						switch ( $field['type'] ) {
							case 'number':
								if ( strlen( $row_value ) ) {
									if ( ! is_numeric( $row_value ) ) {
										// translators: %s is field name.
										throw new Exception( sprintf( __( '%s field is invalid number', 'wcsdm' ), $field['title'] ) );
									}

									$min_value = isset( $field['custom_attributes']['min'] ) ? $field['custom_attributes']['min'] : false;
									if ( false !== $min_value && $row_value < $min_value ) {
										// translators: %1$s is field name, %2$d minimum value validation rule.
										throw new Exception( sprintf( __( '%1$s must be greater than %2$d', 'wcsdm' ), $field['title'], $field['custom_attributes']['min'] ) );
									}
								}

								$value = $row_value;
								break;

							case 'select':
								if ( strlen( $row_value ) && ! isset( $field['options'][ $row_value ] ) ) {
									// translators: %s is field name.
									throw new Exception( sprintf( __( '%s selected option is invalid', 'wcsdm' ), $field['title'] ) );
								}

								$value = $row_value;
								break;

							default:
								$value = $row_value;
								break;
						}
					}

					$rates[ $index ][ $data_key ] = $value;
				}
			}

			$rates_filtered = array();

			foreach ( $rates as $index => $rate ) {
				$rates_filtered[ $rate['distance'] . '_' . $rate['min_order_amt'] . '_' . $rate['min_order_qty'] ] = $rate;
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

		try {
			$api_request = $this->api_request(
				array(
					'origin_info'      => $this->get_origin_info( $package ),
					'destination_info' => $this->get_destination_info( $package ),
					'package'          => $package,
					'all_options'      => $this->all_options,
				)
			);

			if ( ! $api_request ) {
				return;
			}

			$rate_data = $this->get_rate_by_distance( $api_request['distance'], $package );

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
			$this->show_debug( $e->getMessage(), 'error' );
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
		$rates = array();

		// Get cart subtotal.
		$subtotal = WC()->cart->get_subtotal();

		// Get cart contents count.
		$cart_contents_count = WC()->cart->get_cart_contents_count();

		if ( $this->table_rates ) {
			foreach ( $this->table_rates as $rate ) {
				if ( $distance > $rate['distance'] ) {
					continue;
				}

				if ( $subtotal < $rate['min_order_amt'] ) {
					continue;
				}

				if ( $cart_contents_count < $rate['min_order_qty'] ) {
					continue;
				}

				if ( ! isset( $rates[ $rate['distance'] ] ) ) {
					$rates[ $rate['distance'] ] = array();
				}

				$rates[ $rate['distance'] ][] = $rate;
			}
		}
		error_log( print_r( array( '$rates' => $rates ), true ) );

		if ( $rates ) {
			// // Sort the rates by distance
			// ksort( $rates );
			// // Get first rates data
			// $rates = array_shift( $rates );
			// // Get cart subtotal
			// $subtotal = WC()->cart->get_subtotal();
			// // Get cart contents count
			// $cart_contents_count = WC()->cart->get_cart_contents_count();
			// usort( $rates, array( $this, 'sort_rate_by_distance' ) );
			// usort( $rates, array( $this, 'sort_rate_by_min_order_amt' ) );
			// usort( $rates, array( $this, 'sort_rate_by_min_order_qty' ) );
			// error_log( print_r( array( '$subtotal' => $subtotal ), true ) );
			// error_log( print_r( array( '$cart_contents_count' => $cart_contents_count ), true ) );
			error_log( print_r( array( '$rates' => $rates ), true ) );
		}

		$distance_unit = 'imperial' === $this->gmaps_api_units ? 'mi' : 'km';

		// translators: %1$s distance value, %2$s distance unit.
		return new WP_Error( 'no_rates', sprintf( __( 'No shipping rates defined within distance range: %1$s %2$s', 'wcsdm' ), $distance, $distance_unit ) );
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @since    1.0.0
	 * @param array $params API request parameters.
	 * @param bool  $cache_data Use cached data.
	 * @param bool  $return_wp_error Return wp_error object on failure.
	 * @throws Exception Throw error if validation not passed.
	 * @return mixed array of API response data, false on failure.
	 */
	private function api_request( $params, $cache_data = true, $return_wp_error = false ) {
		$request_data = wp_parse_args(
			$params, array(
				'origin_info'      => '',
				'destination_info' => '',
			)
		);

		try {
			if ( empty( $this->gmaps_api_key ) ) {
				throw new Exception( __( 'API Key is empty', 'wcsdm' ) );
			}

			if ( empty( $request_data['origin_info'] ) ) {
				throw new Exception( __( 'Origin info is empty', 'wcsdm' ) );
			}

			if ( empty( $request_data['destination_info'] ) ) {
				throw new Exception( __( 'Destination info is empty', 'wcsdm' ) );
			}

			$cache_key = $this->id . '_api_request_' . md5( wp_json_encode( $request_data ) );

			if ( $cache_data ) {
				// Check if the data already chached and return it.
				$cached_data = get_transient( $cache_key );

				if ( false !== $cached_data ) {
					$this->show_debug( __( 'Cache key', 'wcsdm' ) . ': ' . $cache_key );
					$this->show_debug( __( 'Cached data', 'wcsdm' ) . ': ' . wp_json_encode( $cached_data ) );
					return $cached_data;
				}
			}

			$origins      = is_array( $request_data['origin_info'] ) ? implode( ',', $request_data['origin_info'] ) : $request_data['origin_info'];
			$destinations = is_array( $request_data['destination_info'] ) ? implode( ',', $request_data['destination_info'] ) : $request_data['destination_info'];

			$request_url_args = array(
				'key'          => rawurlencode( $this->gmaps_api_key ),
				'mode'         => rawurlencode( $this->gmaps_api_mode ),
				'avoid'        => is_string( $this->gmaps_api_avoid ) ? rawurlencode( $this->gmaps_api_avoid ) : '',
				'units'        => rawurlencode( $this->gmaps_api_units ),
				'language'     => rawurlencode( get_locale() ),
				'origins'      => rawurlencode( $origins ),
				'destinations' => rawurlencode( $destinations ),
			);

			$request_url = add_query_arg( $request_url_args, $this->google_api_url );

			$this->show_debug( __( 'API Request URL', 'wcsdm' ) . ': ' . str_replace( rawurlencode( $this->gmaps_api_key ), '**********', $request_url ) );

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
			if ( json_last_error_msg() !== 'No error' ) {
				// translators: %s is last json error message string.
				throw new Exception( sprintf( __( 'Error occured while decoding API response: %s', 'wcsdm' ), json_last_error_msg() ) );
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
					$distance      = $this->convert_distance( $element['distance']['value'] );
					$distance_text = 'metric' === $this->gmaps_api_units ? $distance . ' km' : $distance . ' mi';
					$results[]     = array(
						'distance'      => $distance,
						'distance_text' => $distance_text,
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

			$result['response'] = $response_data;

			if ( $cache_data ) {
				delete_transient( $cache_key ); // To make sure the transient data re-created, delete it first.
				set_transient( $cache_key, $result, HOUR_IN_SECONDS ); // Store the data to transient with expiration in 1 hour for later use.
			}

			return $result;
		} catch ( Exception $e ) {
			if ( $return_wp_error ) {
				return new WP_Error( 'api_request_error', $e->getMessage() );
			}

			$this->show_debug( $e->getMessage(), 'error' );
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
		return apply_filters( 'woocommerce_' . $this->id . '_shipping_origin_info', $origin_info, $package, $this );
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
		$errors = array();
		$data   = $package['destination'];

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

						if ( ! $poscode_valid ) {
							// translators: %s is shipping destination field label.
							array_push( $errors, sprintf( __( 'Shipping destination field is invalid: %s', 'wcsdm' ), $field_label ) );
							continue;
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

		if ( $errors ) {
			foreach ( $errors as $error ) {
				if ( $this->is_calc_shipping() ) {
					wc_add_notice( $error, 'error' );
				} else {
					$this->show_debug( $error, 'error' );
				}
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
		return apply_filters( 'woocommerce_' . $this->id . '_shipping_destination_info', $destination_info, $package, $this );
	}

	/**
	 * Get products shipping classes list
	 *
	 * @return array
	 */
	private function get_shipping_classes() {
		$shipping_classes = array();

		foreach ( WC()->shipping->get_shipping_classes() as $object ) {
			$shipping_classes[ $object->term_id ] = $object;
		}

		return $shipping_classes;
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
		$result = $meters * 0.000621371;

		// Rounds distance UP to the nearest integer.
		if ( 'yes' === $this->ceil_distance ) {
			$result = ceil( $result );
		}

		return wc_format_decimal( $result, 1 );
	}

	/**
	 * Convert Meters to Kilometres
	 *
	 * @since    1.3.2
	 * @param int $meters Number of meters to convert.
	 * @return int
	 */
	private function convert_distance_to_km( $meters ) {
		$result = $meters * 0.001;

		// Rounds distance UP to the nearest integer.
		if ( 'yes' === $this->ceil_distance ) {
			$result = ceil( $result );
		}

		return wc_format_decimal( $result, 1 );
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
	 * Sort rates list ascending by distance
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function sort_rate_by_distance( $a, $b ) {
		if ( $a['distance'] === $b['distance'] ) {
			return 0;
		}
		return ( $a['distance'] > $b['distance'] ) ? -1 : 1;
	}

	/**
	 * Sort rates list ascending by minimum order amount
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function sort_rate_by_min_order_amt( $a, $b ) {
		if ( $a['min_order_amt'] === $b['min_order_amt'] ) {
			return 0;
		}
		return ( $a['min_order_amt'] > $b['min_order_amt'] ) ? -1 : 1;
	}

	/**
	 * Sort rates list ascending by minimum order quantity
	 *
	 * @since    1.4.4
	 * @param array $a Array 1 that will be sorted.
	 * @param array $b Array 2 that will be compared.
	 * @return int
	 */
	private function sort_rate_by_min_order_qty( $a, $b ) {
		if ( $a['min_order_qty'] === $b['min_order_qty'] ) {
			return 0;
		}
		return ( $a['min_order_qty'] > $b['min_order_qty'] ) ? -1 : 1;
	}

	/**
	 * Insert a value or key/value pair after a specific key in an array.  If key doesn't exist, value is appended
	 * to the end of the array.
	 *
	 * @param array  $array Existing array.
	 * @param string $key  Array key target.
	 * @param array  $new New array data.
	 *
	 * @return array
	 */
	private function array_insert_after( $array, $key, $new ) {
		$keys  = array_keys( $array );
		$index = array_search( $key, $keys, true );
		$pos   = false === $index ? count( $array ) : $index + 1;

		return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
	}

	/**
	 * Show debug info
	 *
	 * @since    1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $type The type of notice.
	 * @return void
	 */
	private function show_debug( $message, $type = '' ) {
		if ( empty( $message ) ) {
			return;
		}

		if ( get_option( 'woocommerce_shipping_debug_mode', 'no' ) !== 'yes' ) {
			return;
		}

		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			return;
		}

		if ( defined( 'WC_DOING_AJAX' ) ) {
			return;
		}

		$debug_key = md5( $message );

		if ( isset( $this->_debugs[ $debug_key ] ) ) {
			return;
		}

		$this->_debugs[ $debug_key ] = $message;

		$debug_prefix = strtoupper( WCSDM_METHOD_ID );

		if ( ! empty( $type ) ) {
			$debug_prefix .= '_' . strtoupper( $type );
		}

		wc_add_notice( $debug_prefix . ' => ' . $message, 'notice' );
	}
}
