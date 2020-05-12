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
class Wcsdm_Migration_2_1_0 extends Wcsdm_Migration {

	/**
	 * Plugin migration data version
	 *
	 * @since 2.1.10
	 *
	 * @return string
	 */
	public static function get_version() {
		return '2.1.0';
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
			'api_key'         => 'api_key_browser',
			'api_key_picker'  => 'api_key_server',
			'preferred_route' => 'prefered_route',
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
			'api_key_browser',
			'api_key_server',
			'prefered_route',
		);
	}
}
