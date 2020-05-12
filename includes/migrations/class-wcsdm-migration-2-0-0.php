<?php
/**
 * The file that defines the data migration plugin class
 *
 * @link       https://github.com/sofyansitorus
 *
 * @since      2.1.10
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The data migration plugin class.
 *
 * @since      2.1.10
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
class Wcsdm_Migration_2_0_0 extends Wcsdm_Migration {

	/**
	 * Plugin migration data version
	 *
	 * @since 2.1.10
	 *
	 * @return string
	 */
	public static function get_version() {
		return '2.0.0';
	}

	/**
	 * Get migration options data to update
	 *
	 * @since 2.1.10
	 *
	 * @return array
	 */
	public function get_update_options() {
		$update_options = array();

		$pair_options = array(
			'api_key_browser'    => 'gmaps_api_key',
			'api_key_server'     => 'gmaps_api_key',
			'travel_mode'        => 'gmaps_api_mode',
			'route_restrictions' => 'gmaps_api_avoid',
			'distance_unit'      => 'gmaps_api_units',
			'round_up_distance'  => 'ceil_distance',
			'round_up_distance'  => 'calc_type',
		);

		foreach ( $pair_options as $option_new => $option_old ) {
			if ( isset( $this->wc_shipping->instance_settings[ $option_old ] ) && ! isset( $this->wc_shipping->instance_settings[ $option_new ] ) ) {
				$update_options[ $option_new ] = $this->wc_shipping->instance_settings[ $option_old ];
			}
		}

		return $update_options;
	}

	/**
	 * Get migration options data to delete
	 *
	 * @since 2.1.10
	 *
	 * @return array
	 */
	public function get_delete_options() {
		return array(
			'gmaps_api_key',
			'gmaps_api_mode',
			'gmaps_api_avoid',
			'gmaps_api_units',
			'ceil_distance',
			'calc_type',
			'origin',
			'enable_fallback_request',
		);
	}
}
