<?php
/**
 * The file that defines the data migration plugin class
 *
 * @package    Wcsdm
 * @subpackage Legacy/Migrations
 * @since      2.1.10
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The data migration plugin class.
 *
 * @since      2.1.10
 */
class Wcsdm_Legacy_Migration_2_1_0 extends Wcsdm_Legacy_Migration {

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
	 * Get options pair data
	 *
	 * @since 2.1.10
	 *
	 * @return array
	 */
	protected function get_options_pair() {
		return array(
			'api_key'         => 'api_key_browser',
			'api_key_picker'  => 'api_key_server',
			'preferred_route' => 'prefered_route',
		);
	}
}
