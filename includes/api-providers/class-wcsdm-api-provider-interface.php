<?php
/**
 * WooReer API Provider Interface
 *
 * This file defines the interface contract that all API providers must implement
 * to integrate with the WooReer shipping distance calculation system.
 *
 * The interface ensures consistency across different API providers (Google, Mapbox,
 * DistanceMatrix, etc.) by standardizing the methods required for:
 * - Provider identification and display names
 * - Settings field configuration
 * - Distance calculation between locations
 *
 * Any class implementing this interface can be used as a distance calculation
 * provider in WooReer, allowing for flexible integration of various mapping and
 * routing APIs.
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 *
 * @see        Wcsdm_API_Provider_Base For the base implementation class
 * @see        Wcsdm_Location For location data structure
 * @see        Wcsdm_Calculate_Distance_Result For calculation result structure
 */

// Prevent direct access to this file for security purposes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for WooReer API Providers
 *
 * Defines the contract that all distance calculation API providers must fulfill
 * to integrate with the WooReer shipping method. This interface ensures that all
 * providers expose a consistent API for identification, configuration, and
 * distance calculation operations.
 *
 * Implementation Requirements:
 * - Providers must have a unique slug for identification
 * - Providers must supply a human-readable name for display
 * - Providers must define their configuration settings fields
 * - Providers must implement distance calculation logic
 *
 * Usage Example:
 * ```php
 * class My_Custom_Provider extends Wcsdm_API_Provider_Base implements Wcsdm_API_Provider_Interface {
 *     public function get_slug():string {
 *         return 'my_custom_api';
 *     }
 *
 *     public function get_name():string {
 *         return 'My Custom API';
 *     }
 *
 *     public function get_settings_fields( string $context ):array {
 *         return [ ... ];
 *     }
 *
 *     public function calculate_distance( ... ):Wcsdm_Calculate_Distance_Result {
 *         // Implementation logic
 *     }
 * }
 * ```
 *
 * @since 3.0
 */
interface Wcsdm_API_Provider_Interface {

	/**
	 * Get the unique identifier for this API provider.
	 *
	 * Returns a unique slug that identifies this provider in the system.
	 * The slug should be lowercase, alphanumeric with underscores, and should
	 * not change across versions to maintain consistency.
	 *
	 * @since 3.0
	 * @return string The provider's unique slug (e.g., 'google', 'mapbox', 'distancematrix').
	 */
	public function get_slug():string;

	/**
	 * Get the human-readable name of this API provider.
	 *
	 * Returns the display name shown to users in the admin interface when
	 * selecting an API provider for distance calculations.
	 *
	 * @since 3.0
	 * @return string The provider's display name (e.g., 'Google Maps', 'Mapbox').
	 */
	public function get_name():string;

	/**
	 * Get the settings fields configuration for this API provider.
	 *
	 * Returns an array of settings field definitions that will be rendered in
	 * the WooCommerce shipping method settings page. Each field definition should
	 * follow the WooCommerce settings API format.
	 *
	 * The context parameter allows providers to return different fields based on
	 * where they're being displayed (e.g., 'settings', 'instance_settings').
	 *
	 * @since 3.0
	 * @param string $context The context in which fields are being requested.
	 * @return array Array of settings field definitions in WooCommerce format.
	 */
	public function get_settings_fields( string $context ):array;

	/**
	 * Calculate the distance between two locations.
	 *
	 * Performs the actual distance calculation between the destination and origin
	 * locations using the provider's specific API. The method should handle API
	 * requests, parse responses, handle errors, and return a standardized result.
	 *
	 * The shipping method instance provides access to provider-specific settings
	 * such as API keys, routing preferences, and other configuration options.
	 *
	 * @since 3.0
	 * @param Wcsdm_Location        $destination The destination location (typically customer address).
	 * @param Wcsdm_Location        $origin      The origin location (typically store/warehouse address).
	 * @param Wcsdm_Shipping_Method $instance    The shipping method instance with provider settings.
	 * @return Wcsdm_Calculate_Distance_Result The calculation result with distance or error information.
	 */
	public function calculate_distance( Wcsdm_Location $destination, Wcsdm_Location $origin, Wcsdm_Shipping_Method $instance ):Wcsdm_Calculate_Distance_Result;
}
