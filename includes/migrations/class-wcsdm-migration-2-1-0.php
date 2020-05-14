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
