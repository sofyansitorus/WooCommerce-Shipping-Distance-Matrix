<?php
/**
 * Constants file
 *
 * @package    Wcsdm
 * @subpackage Legacy
 * @since      2.1.7
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Define plugin legacy constants.
if ( ! defined( 'WCSDM_DEFAULT_LAT' ) ) {
	define( 'WCSDM_DEFAULT_LAT', '-6.178784361374902' );
}
if ( ! defined( 'WCSDM_DEFAULT_LNG' ) ) {
	define( 'WCSDM_DEFAULT_LNG', '106.82303292695315' );
}
if ( ! defined( 'WCSDM_TEST_LAT' ) ) {
	define( 'WCSDM_TEST_LAT', '-6.181472315327319' );
}
if ( ! defined( 'WCSDM_TEST_LNG' ) ) {
	define( 'WCSDM_TEST_LNG', '106.8170462364319' );
}
