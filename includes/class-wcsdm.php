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
								$this->generate_rate_row( $table_rate );
							endforeach;
						endif;
						?>
					</tbody>
					<tfoot>
						<?php $this->generate_rate_row_heading( 'bottom' ); ?>
					</tfoot>
				</table>
				<script type="text/template" id="tmpl-rates-list-input-table-row">
					<?php $this->generate_rate_row(); ?>
				</script>
				<script type="text/template" id="tmpl-btn-apply-changes">
					<button id="btn-apply-changes" class="button button-primary button-large"><?php esc_html_e( 'Apply Changes', 'wcsdm' ); ?></button>
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
		$fields = $this->rates_fields( 'dummy' );
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
	 * @param array $rate Table rate data.
	 * @return void
	 */
	private function generate_rate_row( $rate = array() ) {
		$fields = $this->rates_fields( 'dummy' );
		?>
		<tr>
			<td class="col-checkbox">
				<div><input class="select-item" type="checkbox"></div>
			</td>
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
		<tr valign="top" id="wcsdm-table-row-advanced" class="wcsdm-table-row wcsdm-table-row-advanced">
			<td class="wcsdm-table-col wcsdm-table-col-advanced" colspan="2">
				<table id="wcsdm-table-advanced" class="form-table wcsdm-table wcsdm-table-advanced" cellspacing="0">
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
	 * Generate advanced settings form
	 *
	 * @since 1.2.4
	 * @param string $key  Settings field key.
	 * @param array  $data Settings field data.
	 */
	public function generate_action_link_html( $key, $data ) {
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

			$html = $this->generate_text_html( $field_key, array_merge(
				$field,
				array(
					'type' => 'hidden',
				)
			) );

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
		<a href="#" class="advanced-rate-settings" title="<?php echo esc_attr( $data['description'] ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
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
			'custom_attributes' => array(
				'min' => '0',
			),
			'context'           => array( 'advanced', 'dummy', 'hidden' ),
		);

		$fields = array(
			'shipping_label' => array_merge( $this->instance_form_fields['title'], array(
				'description' => $this->instance_form_fields['title']['description'] . ' ' . __( 'Leave blank to use the global title settings.', 'wcsdm' ),
				'default'     => '',
				'desc_tip'    => true,
				'context'     => array( 'advanced', 'hidden' ),
			) ),
			'distance'       => array(
				'type'              => 'number',
				'title'             => __( 'Max. Distances', 'wcsdm' ),
				'class'             => 'field-distance',
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
					'per_unit' => '',
					'formula'  => __( 'Formula', 'wcsdm' ),
				),
				'description' => __( 'Determine wether to use flat price or flexible price per distances unit.', 'wcsdm' ),
				'desc_tip'    => true,
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
				'context'     => array( 'advanced', 'hidden' ),
			),
			'base'           => array(
				'type'              => 'number',
				'title'             => __( 'Additional Cost', 'wcsdm' ),
				'default'           => '0',
				'description'       => __( 'Surcharge that will be added to the total shipping cost.', 'wcsdm' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min' => '0',
				),
				'context'           => array( 'advanced', 'hidden' ),
			),
			'advanced'       => array(
				'type'    => 'action_link',
				'title'   => __( 'Advanced', 'wcsdm' ),
				'context' => array( 'dummy' ),
			),
		);

		foreach ( $this->get_shipping_classes() as $class_id => $class_obj ) {
			$fields = $this->array_insert_after( $fields, 'cost_class', array(
				'class_' . $class_id => array_merge(
					$class_0_field,
					array(
						// translators: %s is Product shipping class name.
						'title'       => sprintf( __( '"%s" Class Shipping Rate', 'wcsdm' ), $class_obj->name ),
						'description' => __( 'Shipping rate for specific product shipping class. This rate will override the default shipping rate defined above. Blank or zero value will be ignored.', 'wcsdm' ),
						'context'     => array( 'advanced', 'hidden' ),
						'class'       => 'cost_class--bind',
						'required'    => array( 'cost_class' => 'yes' ),
					)
				),
			) );
		}

		$populated_fields = array();

		foreach ( $fields as $key => $field ) {
			if ( ! empty( $context ) && ! in_array( $context, $field['context'], true ) ) {
				continue;
			}

			$field_class = 'wcsdm-rate-field wcsdm-rate-field--' . $key;

			if ( ! empty( $context ) ) {
				$field_class .= ' wcsdm-rate-field--' . $context . ' wcsdm-rate-field--' . $context . '--' . $key;
			}

			if ( isset( $field['class'] ) ) {
				$field_class .= ' ' . $field['class'];
			}

			$field_custom_attributes = array_merge(
				isset( $field['custom_attributes'] ) ? $field['custom_attributes'] : array(),
				array(
					'data-key' => $key,
					'data-type' => $field['type'],
				)
			);

			$populated_fields[ $key ] = array_merge( $field, array(
				'class'             => $field_class,
				'custom_attributes' => $field_custom_attributes,
			) );
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
					$is_required = isset( $field['required'] ) ? $field['required'] : false;
					if ( is_array( $is_required ) ) {
						$is_required_array = true;
						foreach ( $is_required as $required_key => $required_value ) {
							if ( ! isset( $post_data[ $this->get_field_key( $required_key ) ][ $index ] ) ) {
								$is_required_array = false;
								break;
							}

							if ( $post_data[ $this->get_field_key( $required_key ) ][ $index ] !== $required_value ) {
								$is_required_array = false;
								break;
							}
						}
						$is_required = $is_required_array;
					}

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

						default:
							$value = $row_value;
							break;
					}

					$rates[ $index ][ $data_key ] = $value;
				}
			}

			$rates_filtered = array();

			foreach ( $rates as $index => $rate ) {
				$index_array = array( $rate['class_0'] );

				foreach ( $this->get_shipping_classes() as $class_id => $class_object ) {
					$index_array[] = isset( $rate[ 'class_' . $class_id ] ) ? $rate[ 'class_' . $class_id ] : 0;
				}

				$rates_filtered[ implode( $index_array, '_' ) ] = $rate;
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
			$this->show_debug( $e->getMessage(), 'error' );
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

			return $result;
		} catch ( Exception $e ) {
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
		return apply_filters( 'woocommerce_' . $this->id . '_shipping_destination_info', $destination_info, $this );
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
		$index = array_search( $key, $keys );
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
