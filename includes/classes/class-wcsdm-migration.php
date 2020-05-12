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
abstract class Wcsdm_Migration {

	/**
	 * WC Shipping instance
	 *
	 * @var WC_Shipping_Method
	 */
	protected $wc_shipping;

	/**
	 * Class constructor
	 *
	 * @param WC_Shipping_Method|false $wc_shipping Shipping instance.
	 */
	public function __construct( $wc_shipping = false ) {
		if ( $wc_shipping ) {
			$this->set_instance( $wc_shipping );
		}
	}

	/**
	 * Get migration options data to update
	 *
	 * @return array
	 */
	public function get_update_options() {
		return array();
	}

	/**
	 * Set WC Shipping instance
	 *
	 * @param WC_Shipping_Method|false $wc_shipping Shipping instance.
	 *
	 * @return void
	 */
	public function set_instance( $wc_shipping ) {
		$this->wc_shipping = $wc_shipping;
	}

	/**
	 * Get migration options data to delete
	 *
	 * @return array
	 */
	public function get_delete_options() {
		return array();
	}

	/**
	 * Plugin migration data version
	 *
	 * @return string
	 */
	abstract public static function get_version();
}
