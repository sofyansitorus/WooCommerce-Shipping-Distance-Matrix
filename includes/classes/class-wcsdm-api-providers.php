<?php
/**
 * API Providers manager class for WooReer
 *
 * This file contains the Wcsdm_API_Providers class which implements a singleton
 * pattern for managing distance calculation API providers. It handles auto-discovery,
 * registration, and retrieval of all available API provider implementations used
 * by the WooReer shipping plugin.
 *
 * Key Features:
 * - Singleton pattern ensures single instance throughout application lifecycle
 * - Auto-discovery of provider implementations via file system scanning
 * - Registry-based provider management with slug-based indexing
 * - Data locking mechanism to prevent modifications after initialization
 * - Action hook for external provider registration and customization
 * - Validation to prevent duplicate provider registrations
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Providers manager class.
 *
 * Manages registration and retrieval of distance calculation API providers.
 * Uses singleton pattern to ensure only one instance exists throughout the application lifecycle.
 *
 * This class is responsible for:
 * - Auto-discovering and registering API provider implementations
 * - Maintaining a registry of available distance calculation providers
 * - Providing access to registered providers throughout the plugin
 * - Preventing duplicate provider registrations
 * - Locking the provider registry after initialization to prevent modifications
 *
 * The provider discovery mechanism scans the api-providers directory for files
 * matching the pattern 'class-wcsdm-api-provider-*.php' and automatically
 * instantiates and registers valid provider implementations. After initial
 * registration, the registry is locked to maintain data integrity.
 *
 * @since 3.0
 */
class Wcsdm_API_Providers {

	/**
	 * Singleton instance.
	 *
	 * Holds the single instance of this class to implement the singleton pattern.
	 *
	 * @since 3.0
	 * @var   Wcsdm_API_Providers|null
	 */
	private static $instance = null;

	/**
	 * Registered API providers.
	 *
	 * Associative array storing all registered API provider instances,
	 * indexed by their unique slug identifiers.
	 *
	 * @since 3.0
	 * @var   array<string, Wcsdm_API_Provider_Base>
	 */
	private $providers = array();

	/**
	 * Data lock flag.
	 *
	 * Prevents modification of the providers registry after initial registration
	 * is complete. Set to true after register_providers() completes to ensure
	 * data integrity throughout the application lifecycle.
	 *
	 * @since 3.0
	 * @var   bool
	 */
	private $data_locked = false;

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * Initializes the providers array. This constructor is private to enforce
	 * the singleton pattern - use init() to get the instance.
	 *
	 * @since 3.0
	 */
	private function __construct() {
		$this->providers = array();
	}

	/**
	 * Get singleton instance.
	 *
	 * Creates the singleton instance if it doesn't exist, then scans and registers
	 * all available API providers. Returns the existing instance on subsequent calls.
	 * This method is idempotent - calling it multiple times will always return the
	 * same instance without re-registering providers.
	 *
	 * @since  3.0
	 * @return Wcsdm_API_Providers The singleton instance of the providers manager.
	 */
	public static function init():Wcsdm_API_Providers {
		// Create instance only once (lazy initialization).
		if ( null === self::$instance ) {
			self::$instance = new self();
			// Auto-discover and register all available providers.
			self::$instance->register_providers();
		}

		return self::$instance;
	}

	/**
	 * Scan and register all API providers.
	 *
	 * Automatically discovers and registers all concrete API provider implementations
	 * by scanning the api-providers directory for files matching the naming pattern
	 * 'class-wcsdm-api-provider-*.php'. Skips interface and base class files.
	 *
	 * After registration completes, fires the 'wcsdm_api_providers_registered' action
	 * to allow external code to interact with or modify the registered providers.
	 * Once complete, locks the data to prevent further modifications.
	 *
	 * @since  3.0
	 * @return void
	 */
	private function register_providers():void {
		// Get the absolute path to the api-providers directory.
		$providers_dir = WCSDM_PATH . 'includes/api-providers/';

		// Verify the directory exists before attempting to scan.
		if ( is_dir( $providers_dir ) ) {
			// Scan for provider files matching the naming pattern.
			// Use glob to find all provider class files matching the naming convention.
			$provider_files = glob( $providers_dir . 'class-wcsdm-api-provider-*.php' );

			// Iterate through each discovered provider file.
			foreach ( $provider_files as $file ) {
				// Skip interface file - it's not a concrete provider implementation.
				if ( strpos( $file, '-interface.php' ) !== false ) {
					continue;
				}

				// Skip base class file - it's an abstract class, not a concrete provider.
				if ( strpos( $file, '-base.php' ) !== false ) {
					continue;
				}

				// Extract class name from filename following WordPress naming conventions.
				// Example: 'class-wcsdm-api-provider-google.php' becomes 'Wcsdm_Api_Provider_Google'.
				$class_name = wcsdm_convert_file_path_to_class_name( $file );

				// Verify the class exists and implements the required interface before registering.
				if ( class_exists( $class_name ) ) {
					// Instantiate and register the provider.
					$this->register_provider( new $class_name() );
				}
			}
		}

		/**
		 * Fires after all API providers have been registered.
		 *
		 * This action allows external code to interact with or modify the registered
		 * providers before the registry is locked. You can use this to register custom
		 * providers, unregister existing ones, or perform any provider-related setup.
		 *
		 * @since 3.0
		 *
		 * @param Wcsdm_API_Providers $providers_manager The providers manager instance.
		 */
		do_action( 'wcsdm_api_providers_registered', $this );

		// Lock the data to prevent further modifications after initial registration.
		$this->data_locked = true;
	}

	/**
	 * Register a distance calculation API provider.
	 *
	 * Adds a new provider instance to the registry. Each provider must have a unique
	 * slug identifier. This method can only be called before the data is locked
	 * (i.e., during the initial registration phase).
	 *
	 * @since  3.0
	 * @param  Wcsdm_API_Provider_Base $provider Provider instance to register.
	 * @return void
	 * @throws Exception If a provider with the same slug is already registered.
	 * @throws Exception If attempting to register after data is locked.
	 */
	public function register_provider( Wcsdm_API_Provider_Base $provider ):void {
		// Prevent duplicate registrations by checking if slug already exists.
		if ( isset( $this->providers[ $provider->get_slug() ] ) ) {
			throw new Exception( sprintf( 'Duplicate API provider slug detected: %s', $provider->get_slug() ) );
		}

		// Prevent modifications after initial registration is complete.
		if ( $this->data_locked ) {
			throw new Exception( 'Cannot register provider after data is locked.' );
		}

		// Add provider to the registry indexed by its unique slug.
		$this->providers[ $provider->get_slug() ] = $provider;
	}

	/**
	 * Unregister a provider by slug.
	 *
	 * Removes a provider from the registry by its unique slug identifier.
	 * This method can only be called before the data is locked (i.e., during
	 * the initial registration phase). Silently does nothing if the provider
	 * slug doesn't exist.
	 *
	 * @since  3.0
	 * @param  string $slug The unique slug of the provider to unregister.
	 * @return void
	 * @throws Exception If attempting to unregister after data is locked.
	 */
	public function unregister_provider( string $slug ):void {
		// Check if provider exists in the registry.
		if ( isset( $this->providers[ $slug ] ) ) {
			// Prevent modifications after initial registration is complete.
			if ( $this->data_locked ) {
				throw new Exception( 'Cannot unregister provider after data is locked.' );
			}

			// Remove the provider from the registry.
			unset( $this->providers[ $slug ] );
		}
	}

	/**
	 * Get a specific provider by slug.
	 *
	 * Retrieves a registered API provider instance by its unique slug identifier.
	 * Returns null if no provider is found with the given slug. This method is
	 * typically used when you need to work with a specific distance calculation
	 * provider based on user configuration or settings.
	 *
	 * @since  3.0
	 * @param  string $slug Provider slug (e.g., 'google', 'mapbox', 'distancematrix').
	 * @return Wcsdm_API_Provider_Base|null Provider instance or null if not found.
	 */
	public function get_provider( string $slug ):?Wcsdm_API_Provider_Base {
		return isset( $this->providers[ $slug ] ) ? $this->providers[ $slug ] : null;
	}

	/**
	 * Get all registered providers.
	 *
	 * Returns an associative array of all registered API provider instances,
	 * indexed by their slug. This is useful for:
	 * - Displaying available providers in admin settings
	 * - Generating provider selection dropdowns
	 * - Performing operations across all providers
	 * - Debugging or logging available providers
	 *
	 * @since  3.0
	 * @return array<string, Wcsdm_API_Provider_Base> Array of provider instances indexed by slug.
	 */
	public function get_all_providers():array {
		return $this->providers;
	}

}
