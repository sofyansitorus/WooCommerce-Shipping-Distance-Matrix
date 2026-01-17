<?php
/**
 * Base class for WooReer API providers.
 *
 * This file defines the base abstract class that all API providers must extend.
 * It provides common functionality and enforces implementation of required methods
 * through the Wcsdm_API_Provider_Interface. This class serves as the foundation
 * for all distance calculation API providers used in WooReer.
 *
 * ## Core Responsibilities
 *
 * The base class handles:
 * - Settings field management and retrieval with context-specific filtering
 * - Field key generation with automatic namespacing to prevent conflicts
 * - Request header population from provider settings
 * - Request parameter population from provider settings
 * - Extensibility through WordPress filter hooks at critical points
 * - Consistent validation and error handling across providers
 *
 * ## Implementation Guidelines
 *
 * Child classes should:
 * - Implement all methods required by Wcsdm_API_Provider_Interface
 * - Define their settings fields in the constructor by populating $settings_fields
 * - Not override final methods as they provide standardized behavior
 * - Use the provided helper methods for consistent functionality
 *
 * ## Architecture Pattern
 *
 * This class follows the Template Method pattern, where the base class defines
 * the algorithm structure (field management, header/param population) while
 * subclasses implement specific behaviors through the interface methods.
 *
 * @package    Wcsdm
 * @subpackage ApiProviders
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 *
 * @see        Wcsdm_API_Provider_Interface For required interface implementation
 * @see        Wcsdm_Request_Headers For request headers structure
 * @see        Wcsdm_Request_Params For request parameters structure
 * @see        Wcsdm_Shipping_Method For the shipping method instance context
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base abstract class for API providers.
 *
 * Provides shared functionality for all API provider implementations including
 * settings field management, field key namespacing, and request data population
 * for headers and parameters. Child classes must implement the interface methods
 * for provider identification and distance calculation.
 *
 * @since 3.0
 */
abstract class Wcsdm_API_Provider_Base implements Wcsdm_API_Provider_Interface {

	/**
	 * Array of settings field definitions for this provider.
	 *
	 * Each field definition follows the WooCommerce settings API format and can
	 * include additional keys for API request mapping:
	 * - api_request_headers_key: Maps to HTTP request header
	 * - api_request_headers_sanitizer: Callback to sanitize header value
	 * - api_request_params_key: Maps to API request parameter
	 * - api_request_params_sanitizer: Callback to sanitize parameter value
	 *
	 * Child classes should populate this array in their constructor.
	 *
	 * @since 3.0
	 * @var array
	 */
	protected $settings_fields = array();

	/**
	 * Get settings fields for this API provider.
	 *
	 * Returns an array of settings field definitions based on the provided context.
	 * The method is marked as final to ensure consistent field retrieval across all
	 * providers. Child classes should populate the $settings_fields property instead
	 * of overriding this method.
	 *
	 * Supported contexts:
	 * - 'settings': Fields displayed in the admin settings interface
	 * - 'calculation': Fields used during distance calculation
	 *
	 * @since 3.0
	 *
	 * @param string $context The context for which to retrieve settings fields.
	 *                        Must be either 'settings' or 'calculation'.
	 * @return array Array of settings field definitions in WooCommerce format.
	 *
	 * @throws InvalidArgumentException If an invalid context is provided.
	 */
	final public function get_settings_fields( string $context ):array {
		// Define allowed contexts for field retrieval.
		$allowed_contexts = array( 'settings', 'calculation' );

		// Validate that the provided context is supported.
		if ( ! in_array( $context, $allowed_contexts, true ) ) {
			throw new InvalidArgumentException( 'Invalid context provided: ' . $context );
		}

		/**
		 * Filters the settings fields for this API provider.
		 *
		 * Allows modification of settings fields before they are returned.
		 * The filter hook is dynamic and includes the provider's slug.
		 *
		 * @since 3.0
		 *
		 * @param array  $settings_fields The settings field definitions.
		 * @param string $context         The context ('settings' or 'calculation').
		 */
		return apply_filters( 'wcsdm_api_provider_' . $this->get_slug() . '_settings_fields', $this->settings_fields, $context );
	}

	/**
	 * Generate a namespaced field key for this provider.
	 *
	 * Ensures that all field keys are properly namespaced with the provider's slug
	 * to prevent conflicts between different providers. If the key is already
	 * prefixed with the provider's slug, it returns the key unchanged.
	 *
	 * Example: For a provider with slug 'google', the key 'api_key' becomes 'google_api_key'.
	 *
	 * @since 3.0
	 *
	 * @param string $key The base field key to namespace.
	 * @return string The namespaced field key with provider slug prefix.
	 */
	final public function get_field_key( string $key ):string {
		// Create the prefix using the provider's slug.
		$prefix = $this->get_slug() . '_';

		// Return the key unchanged if it already has the correct prefix.
		if ( 0 === strpos( $key, $prefix ) ) {
			return $key;
		}

		// Add the prefix to the key.
		return $prefix . $key;
	}

	/**
	 * Populate HTTP request headers from provider settings.
	 *
	 * Iterates through the provider's settings fields and builds a collection of
	 * HTTP request headers based on field configurations. Only fields with the
	 * 'api_request_headers_key' property are processed.
	 *
	 * The method handles:
	 * - Automatic field key namespacing
	 * - Context-specific value retrieval (saved options vs. POST data)
	 * - Optional value sanitization via callbacks
	 * - Filtering non-empty string values only
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Shipping_Method $instance The shipping method instance containing settings.
	 * @param string                $context  The context ('settings' or 'calculation').
	 * @param array                 $initial_headers Optional associative array of initial headers to include.
	 * @return Wcsdm_Request_Headers The populated request headers object.
	 */
	final public function populate_request_headers( Wcsdm_Shipping_Method $instance, string $context, array $initial_headers = array() ):Wcsdm_Request_Headers {
		// Initialize an empty request headers object.
		$request_headers = new Wcsdm_Request_Headers( $initial_headers );

		// Get settings fields for the current context.
		$settings_fields = $this->get_settings_fields( $context );

		// Process each settings field to extract header values.
		foreach ( $settings_fields as $field_key => $field ) {
			// Skip fields that don't map to request headers.
			if ( ! isset( $field['api_request_headers_key'] ) ) {
				continue;
			}

			// Ensure the field key is properly namespaced.
			$field_key = $this->get_field_key( $field_key );

			// Retrieve the field value based on context.
			if ( 'settings' === $context ) {
				// During settings save, use POST data with fallback to default.
				$option_value = $instance->get_post_data_value( $field_key, $field['default'] ?? null );
			} else {
				// During calculation, use saved option values.
				$option_value = $instance->get_option( $field_key );
			}

			// Apply custom sanitization if defined for this header.
			if ( isset( $field['api_request_headers_sanitizer'] ) && is_callable( $field['api_request_headers_sanitizer'] ) ) {
				$option_value = call_user_func( $field['api_request_headers_sanitizer'], $option_value );
			}

			// Add the header only if it's a non-empty string.
			if ( is_string( $option_value ) && '' !== $option_value ) {
				$request_headers->add_header( $option_value, $field['api_request_headers_key'] );
			}
		}

		/**
		 * Filters the request headers for this API provider.
		 *
		 * Allows modification of request headers before they are used in API calls.
		 * The filter hook is dynamic and includes the provider's slug.
		 *
		 * @since 3.0
		 *
		 * @param Wcsdm_Request_Headers $request_headers The populated request headers.
		 * @param Wcsdm_Shipping_Method $instance        The shipping method instance.
		 * @param string                $context         The context ('settings' or 'calculation').
		 */
		return apply_filters( 'wcsdm_api_provider_' . $this->get_slug() . '_request_headers', $request_headers, $instance, $context );
	}

	/**
	 * Populate API request parameters from provider settings.
	 *
	 * Iterates through the provider's settings fields and builds a collection of
	 * API request parameters based on field configurations. Only fields with the
	 * 'api_request_params_key' property are processed.
	 *
	 * The method handles:
	 * - Automatic field key namespacing
	 * - Context-specific value retrieval (saved options vs. POST data)
	 * - Optional value sanitization via callbacks
	 * - Filtering to include only non-null values
	 * - Overwriting existing parameters with the same key
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Shipping_Method $instance The shipping method instance containing settings.
	 * @param string                $context  The context ('settings' or 'calculation').
	 * @param array                 $initial_params Optional associative array of initial parameters to include.
	 *
	 * @return Wcsdm_Request_Params The populated request parameters object.
	 */
	final public function populate_request_params( Wcsdm_Shipping_Method $instance, string $context, array $initial_params = array() ):Wcsdm_Request_Params {
		// Initialize an empty request parameters object.
		$request_params = new Wcsdm_Request_Params( $initial_params );

		// Get settings fields for the current context.
		$settings_fields = $this->get_settings_fields( $context );

		// Process each settings field to extract parameter values.
		foreach ( $settings_fields as $field_key => $field ) {
			// Skip fields that don't map to request parameters.
			if ( ! isset( $field['api_request_params_key'] ) ) {
				continue;
			}

			// Ensure the field key is properly namespaced.
			$field_key = $this->get_field_key( $field_key );

			// Retrieve the field value based on context.
			if ( 'settings' === $context ) {
				// During settings save, use POST data with fallback to default.
				$option_value = $instance->get_post_data_value( $field_key, $field['default'] ?? null );
			} else {
				// During calculation, use saved option values.
				$option_value = $instance->get_option( $field_key );
			}

			// Apply custom sanitization if defined for this parameter.
			if ( isset( $field['api_request_params_sanitizer'] ) && is_callable( $field['api_request_params_sanitizer'] ) ) {
				$option_value = call_user_func( $field['api_request_params_sanitizer'], $option_value );
			}

			// Add the parameter only if it's not null. The third parameter (true)
			// indicates that this value should overwrite any existing parameter with the same key.
			if ( null !== $option_value ) {
				$request_params->add_param( $option_value, $field['api_request_params_key'], true );
			} else {
				// If the value is null, ensure the parameter is removed.
				$request_params->remove_param( $field['api_request_params_key'] );
			}
		}

		/**
		 * Filters the request parameters for this API provider.
		 *
		 * Allows modification of request parameters before they are used in API calls.
		 * The filter hook is dynamic and includes the provider's slug.
		 *
		 * @since 3.0
		 *
		 * @param Wcsdm_Request_Params  $request_params The populated request parameters.
		 * @param Wcsdm_Shipping_Method $instance       The shipping method instance.
		 * @param string                $context        The context ('settings' or 'calculation').
		 */
		return apply_filters( 'wcsdm_api_provider_' . $this->get_slug() . '_request_params', $request_params, $instance, $context );
	}
}
