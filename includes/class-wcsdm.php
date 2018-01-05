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
		$this->id = 'wcsdm';

		// Title shown in admin.
		$this->method_title = __( 'Shipping Distance Matrix', 'wcsdm' );

		// Description shown in admin.
		$this->method_description = __( 'Shipping rates calculator based on distance and product shipping class.', 'wcsdm' );

		$this->enabled = $this->get_option( 'enabled' );

		$this->title = $this->get_option( 'title', $this->method_title );

		$this->instance_id = absint( $instance_id );

		$this->supports = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->tax_status = $this->get_option( 'tax_status' );

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
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Define user set variables.
		$this->title      = $this->get_option( 'title' );
		$this->tax_status = $this->get_option( 'tax_status' );

		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		// Check if this shipping method is availbale for current order.
		apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', array( $this, 'check_is_available' ), 10, 2 );
	}

	/**
	 * Init form fields.
	 * 
	 * @since    1.0.0
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'            => array(
				'title'       => __( 'Title', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'gmaps_api_key'    => array(
				'title'       => __( 'API Key', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( '<a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">Click here</a> to get a Google Maps Distance Matrix API Key.', 'wcsdm' ),
				'default'     => '',
			),
			'origin_lat'       => array(
				'title'       => __( 'Store Location Latitude', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( '<a href="http://www.latlong.net/" target="_blank">Click here</a> to get your store location coordinates info.', 'wcsdm' ),
				'default'     => '',
			),
			'origin_lng'       => array(
				'title'       => __( 'Store Location Longitude', 'wcsdm' ),
				'type'        => 'text',
				'description' => __( '<a href="http://www.latlong.net/" target="_blank">Click here</a> to get your store location coordinates info.', 'wcsdm' ),
				'default'     => '',
			),
			'gmaps_api_units'  => array(
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
			'gmaps_api_mode'   => array(
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
			'gmaps_api_avoid'  => array(
				'title'       => __( 'Restrictions', 'wcsdm' ),
				'type'        => 'multiselect',
				'description' => __( 'Google Maps Distance Matrix API restrictions parameter.', 'wcsdm' ),
				'desc_tip'    => true,
				'default'     => 'driving',
				'options'     => array(
					'tolls'    => __( 'Avoid Tolls', 'wcsdm' ),
					'highways' => __( 'Avoid Highways', 'wcsdm' ),
					'ferries'  => __( 'Avoid Ferries', 'wcsdm' ),
					'indoor'   => __( 'Avoid Indoor', 'wcsdm' ),
				),
			),
			'calc_type'       => array(
				'title'   => __( 'Calculation type', 'wcsdm' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'per_item',
				'options' => array(
					'per_item'  => __( 'Per item: Charge shipping for each items individually', 'wcsdm' ),
					'per_order' => __( 'Per order: Charge shipping for the most expensive shipping cost', 'wcsdm' ),
				),
			),
			'tax_status'       => array(
				'title'   => __( 'Tax status', 'wcsdm' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'shipping_rates'   => array(
				'title'       => __( 'Shipping Rates', 'wcsdm' ),
				'type'        => 'title',
				'description' => __( 'Table rates for each shipping class and maximum distances. Leave blank to disable. Fill 0 (zero) to set as free shipping.', 'wcsdm' ),
			),
			'rates'            => array(
				'type' => 'rates_table',
			),
		);
	}

	/**
	 * Generate table rates HTML form.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 */
	public function generate_rates_table_html( $key ) {
		ob_start();
		$field_key        = $this->get_field_key( $key );
		$shipping_classes = WC()->shipping->get_shipping_classes();
		$options          = $this->get_option( $key ); ?>
		<tr valign="top">
			<td>
				<table id="rates-list-table" class="widefat wc_input_table" cellspacing="0">
					<thead>
						<tr>
							<td class="col-select"></td>
							<td></td>
							<td colspan="<?php echo count( $shipping_classes ) + 1; ?>"><?php esc_html_e( 'Cost by Shipping Class', 'wcsdm' ); ?></td>
						</tr>
						<tr>
							<td class="col-select"><input class="select-item" type="checkbox"></td>
							<td><?php esc_html_e( 'Maximum Distances', 'wcsdm' ); ?></td>
							<td><?php esc_html_e( 'Undefined', 'wcsdm' ); ?></td>
							<?php if ( $shipping_classes ) : ?>
							<?php foreach ( $shipping_classes as $shipping_class ) : ?>
							<td><?php echo esc_html( $shipping_class->name ); ?></td>
							<?php endforeach; ?>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php if ( $options ) : ?>
						<?php foreach ( $options as $option ) : ?>
						<tr>
						<td class="col-select"><input class="select-item" type="checkbox"></td>
						<?php foreach ( $option as $key => $value ) : ?>
						<td class="col-<?php echo esc_attr( $key ); ?>"><input name="<?php echo esc_attr( $field_key ); ?>_<?php echo esc_attr( $key ); ?>[]" type="number" value="<?php echo esc_attr( $value ); ?>" min="0"></td>
						<?php endforeach; ?>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="<?php echo count( $shipping_classes ) + 3; ?>"><a href="#" class="add button" data-key="<?php echo esc_attr( $field_key ); ?>"><?php esc_html_e( '+ Add row', 'wcsdm' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected row(s)', 'wcsdm' ); ?></a></th>
						</tr>
					</tfoot>
				</table>
				<script type="text/template" id="tmpl-rates-list-input-table-row">
				<tr>
					<td class="col-select"><input class="select-item" type="checkbox"></td>
					<td class="col-distance"><input name="{{{ data.field_key }}}_distance[]" type="number" value="" min="0"></td>
					<td class="col-no-class"><input name="{{{ data.field_key }}}_class_0[]" type="number" value="" min="0"></td>
					<?php if ( $shipping_classes ) : ?>
					<?php foreach ( $shipping_classes as $shipping_class ) : ?>
					<td class="col-has-class col-class<?php echo esc_attr( $shipping_class->term_id ); ?>"><input name="{{{ data.field_key }}}_class_<?php echo esc_attr( $shipping_class->term_id ); ?>[]" type="number" value="" min="0"></td>
					<?php endforeach; ?>
					<?php endif; ?>
				</tr>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save rates table data.
	 *
	 * @since    1.0.0
	 * @param string $key Input field key.
	 * @param string $value Input field currenet value.
	 */
	public function validate_rates_field( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		$post_data = $this->get_post_data();

		$rates = array();

		foreach ( $post_data as $post_data_key => $post_data_value ) {
			if ( 0 === strpos( $post_data_key, $field_key ) ) {
				$field_key_short = str_replace( $field_key . '_', '', $post_data_key );
				foreach ( $post_data_value as $index => $row_value ) {
					$rates[ $index ][ $field_key_short ] = $row_value;
				}
			}
		}

		$rates_filtered = array();

		foreach ( $rates as $key => $value ) {
			$value['distance'] = intval( $value['distance'] );
			if ( ! empty( $value['distance'] ) ) {
				$rates_filtered[ $value['distance'] ] = $value;
			}
		}

		ksort( $rates_filtered );

		return array_values( $rates_filtered );
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

		$api_request = $this->api_request( $package['destination'] );

		if ( ! $api_request ) {
			return false;
		}

		foreach ( $package['contents'] as $item ) {
			$shipping_cost = $this->calculate_cost( $api_request['distance'], $item['data']->get_shipping_class_id() );
			if ( is_wp_error( $shipping_cost ) ) {
				return false;
			}
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
		$shipping_cost_total = 0;

		$api_request = $this->api_request( $package['destination'] );

		if ( ! $api_request ) {
			return;
		}

		$calc_type = $this->get_option( 'calc_type' );

		foreach ( $package['contents'] as $hash => $item ) {
			$shipping_cost = $this->calculate_cost( $api_request['distance'], $item['data']->get_shipping_class_id() );
			if ( is_wp_error( $shipping_cost ) ) {
				return;
			}
			switch ( $calc_type ) {
				case 'per_order':
					if ( $shipping_cost > $shipping_cost_total ) {
						$shipping_cost_total = $shipping_cost;
					}
					break;
				default:
					$shipping_cost_total += $shipping_cost * $item['quantity'];
					$api_request[ $hash ] = array(
						'quantity'      => $item['quantity'],
						'shipping_cost' => $shipping_cost,
					);
					break;
			}
		}

		$rate = array(
			'id'        => $this->id,
			'label'     => $this->title,
			'cost'      => $shipping_cost_total,
			'meta_data' => $api_request,
		);

		// Register the rate.
		$this->add_rate( $rate );

		/**
		 * Developers can add additional flat rates based on this one via this action.
		 *
		 * This example shows how you can add an extra rate based on this flat rate via custom function:
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

		$rates = $this->get_option( 'rates' );

		if ( $rates ) {
			$offset = 0;
			foreach ( $rates as $rate ) {
				if ( $distance > $offset && $distance <= $rate['distance'] && isset( $rate[ 'class_' . $class_id ] ) && is_numeric( $rate[ 'class_' . $class_id ] ) ) {
					return $rate[ 'class_' . $class_id ];
				}
				$offset = $rate['distance'];
			}
		}

		return new WP_Error( 'no_rates', __( 'No rates data availbale.' ) );
	}

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @since    1.0.0
	 * @param array $destination Destination info in assciative array: address, address_2, city, state, postcode, country.
	 * @return array
	 */
	private function api_request( $destination ) {

		$destination = $this->get_destination_info( $destination );

		if ( empty( $destination ) ) {
			return false;
		}

		$origins = $this->get_origin_info();

		if ( empty( $origins ) ) {
			return false;
		}

		$request_url = add_query_arg(
			array(
				'key'          => rawurlencode( $this->get_option( 'gmaps_api_key' ) ),
				'units'        => rawurlencode( $this->get_option( 'gmaps_api_units', 'metric' ) ),
				'mode'         => rawurlencode( $this->get_option( 'gmaps_api_mode' ) ),
				'mode'         => rawurlencode( implode( ',', $this->get_option( 'gmaps_api_avoid' ) ) ),
				'destinations' => rawurlencode( $destination ),
				'origins'      => rawurlencode( $origins ),
			), $this->google_api_url
		);
		$this->show_debug( 'Google Maps Distance Matrix API request URL: ' . $request_url );

		$response = wp_remote_retrieve_body( wp_remote_get( esc_url_raw( $request_url ) ) );
		$this->show_debug( 'Google Maps Distance Matrix API response: ' . $response );

		$response = json_decode( $response, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $response['rows'] ) ) {
			return false;
		}

		if ( empty( $response['destination_addresses'] ) || empty( $response['origin_addresses'] ) ) {
			return false;
		}

		$distance  = 0;
		$api_units = $this->get_option( 'gmaps_api_units' );

		foreach ( $response['rows'] as $rows ) {
			foreach ( $rows['elements'] as $element ) {
				if ( 'OK' === $element['status'] ) {
					if ( 'metric' === $api_units ) {
						$element_distance = ceil( str_replace( ' km', '', $element['distance']['text'] ) );
						if ( $element_distance > $distance ) {
							$distance = $element_distance;
						}
					}
					if ( 'imperial' === $api_units ) {
						$element_distance = ceil( str_replace( ' mi', '', $element['distance']['text'] ) );
						if ( $element_distance > $distance ) {
							$distance = $element_distance;
						}
					}
				}
			}
		}

		if ( $distance ) {
			return array(
				'distance' => $distance,
				'response' => $response,
			);
		}

		return false;
	}

	/**
	 * Get shipping origin info
	 *
	 * @since    1.0.0
	 * @return string
	 */
	private function get_origin_info() {
		$origin_info = array();
		$origin_lat  = $this->get_option( 'origin_lat' );
		$origin_lng  = $this->get_option( 'origin_lng' );
		if ( empty( $origin_lat ) && empty( $origin_lng ) ) {
			return false;
		}
		$origin_info[] = $origin_lat;
		$origin_info[] = $origin_lng;
		return implode( ',', $origin_info );
	}

	/**
	 * Get shipping destination info
	 *
	 * @since    1.0.0
	 * @param array $data Shipping destination data in associative array format: address, city, state, postcode, country.
	 * @return string
	 */
	private function get_destination_info( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$info = array();

		$keys = array( 'address', 'address_2', 'city', 'state', 'postcode', 'country' );

		$country_code = false;

		foreach ( $keys as $key ) {
			if ( ! isset( $data[ $key ] ) || empty( $data[ $key ] ) ) {
				continue;
			}
			switch ( $key ) {
				case 'country':
					if ( empty( $country_code ) ) {
						$country_code = $data[ $key ];
					}
					$full_country = isset( WC()->countries->countries[ $country_code ] ) ? WC()->countries->countries[ $country_code ] : $country_code;
					$info[]       = trim( $full_country );
					break;
				case 'state':
					if ( empty( $country_code ) ) {
						$country_code = $data['country'];
					}
					$full_state = isset( WC()->countries->states[ $country_code ][ $data[ $key ] ] ) ? WC()->countries->states[ $country_code ][ $data[ $key ] ] : $data[ $key ];
					$info[]     = trim( $full_state );
					break;
				default:
					$info[] = trim( $data[ $key ] );
					break;
			}
		}
		return implode( ', ', $info );
	}

	/**
	 * Show debug info
	 *
	 * @since    1.0.0
	 * @param string $message The text to display in the notice.
	 * @return void
	 */
	private function show_debug( $message ) {
		$debug_mode = 'yes' === get_option( 'woocommerce_shipping_debug_mode', 'no' );

		if ( $debug_mode && ! defined( 'WOOCOMMERCE_CHECKOUT' ) && ! defined( 'WC_DOING_AJAX' ) && ! wc_has_notice( $message ) ) {
			wc_add_notice( $message );
		}
	}
}
