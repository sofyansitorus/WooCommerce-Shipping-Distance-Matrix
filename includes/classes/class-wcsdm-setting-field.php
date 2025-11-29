<?php
/**
 * WooReer Setting Field Class
 *
 * This file defines the Wcsdm_Setting_Field class which handles the configuration
 * and management of individual setting fields within the WooReer plugin.
 * It provides methods for normalizing field settings, handling different
 * display contexts (hidden, advanced, dummy), and managing rate-specific
 * field configurations.
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 * @license    GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling setting fields in the WooReer plugin.
 *
 * This class manages the configuration and behavior of individual setting fields.
 * It handles:
 * - Field settings normalization and validation
 * - Context-specific configurations (hidden, advanced, dummy)
 * - Rate field specific logic and overrides
 * - Field type handling (text, select, tab_title, etc.)
 *
 * @since 3.0
 */
class Wcsdm_Setting_Field {
	/**
	 * The unique key identifier for the setting field.
	 *
	 * This key is used to uniquely identify the field within the settings system
	 * and serves as the field's ID in forms and configuration arrays.
	 *
	 * @since 3.0
	 * @var string
	 */
	private $key;

	/**
	 * Field settings array.
	 *
	 * Contains all configuration settings for the field including type, title,
	 * description, default value, validation rules, and context-specific overrides.
	 * The settings array follows WooCommerce settings API structure.
	 *
	 * @since 3.0
	 * @var array
	 */
	private $settings = array();

	/**
	 * Available field display contexts.
	 *
	 * Defines the different contexts in which a field can be displayed:
	 * - 'hidden': Field is not displayed but may be used internally
	 * - 'advanced': Field is shown in advanced mode with full functionality
	 * - 'dummy': Field is shown in simplified mode, typically for UI preview
	 *
	 * @since 3.0
	 * @var array
	 */
	private $contexts = array(
		'hidden',
		'advanced',
		'dummy',
	);

	/**
	 * Constructor.
	 *
	 * Initializes the setting field with the provided settings and key.
	 * The settings are normalized during construction to ensure consistency
	 * and proper configuration across all contexts.
	 *
	 * @since 3.0
	 *
	 * @param array  $settings Field settings configuration array. Should follow WooCommerce settings API structure.
	 * @param string $key      Unique field key identifier.
	 */
	public function __construct( array $settings, string $key ) {
		$this->key      = $key;
		$this->settings = $this->normalize_settings( $settings, $key );
	}

	/**
	 * Normalize and validate field settings.
	 *
	 * Processes the raw settings array to ensure proper structure and defaults:
	 * - Normalizes rate_field contexts (hidden, advanced, dummy)
	 * - Sets default field type to 'text' if not specified
	 * - Handles special 'tab_title' field type configuration
	 * - Ensures description field exists
	 * - Sets the field key if not already present
	 *
	 * For rate fields, this method converts boolean values to proper array structures
	 * and validates that all context configurations are properly formatted.
	 *
	 * @since 3.0
	 *
	 * @param array  $settings Rate field settings to normalize.
	 * @param string $key      Field key identifier.
	 * @return array Normalized and validated field settings.
	 */
	private function normalize_settings( array $settings, $key ) {
		// Normalize rate field contexts if rate_field is set to true or contains context arrays.
		if ( isset( $settings['rate_field'] ) ) {
			// If rate_field is simply true, initialize all contexts as empty arrays.
			if ( true === $settings['rate_field'] ) {
				foreach ( $this->contexts as $context ) {
					$settings['rate_field'][ $context ] = array();
				}
			}

			$context_normalized = array();

			// Process each context if rate_field is an array.
			if ( is_array( $settings['rate_field'] ) ) {
				foreach ( $this->contexts as $context ) {
					if ( ! isset( $settings['rate_field'][ $context ] ) ) {
						continue;
					}

					// Store context configuration if it's an array or convert true to empty array.
					if ( is_array( $settings['rate_field'][ $context ] ) ) {
						$context_normalized[ $context ] = $settings['rate_field'][ $context ];
					} elseif ( true === $settings['rate_field'][ $context ] ) {
						$context_normalized[ $context ] = array();
					}
				}
			}

			// Update rate_field with normalized contexts or remove if empty.
			if ( ! empty( $context_normalized ) ) {
				$settings['rate_field']   = $context_normalized;
				$settings['rate_context'] = array_keys( $context_normalized );
			} else {
				unset( $settings['rate_field'] );
			}
		}

		// Set default field type to 'text' if not specified.
		if ( ! isset( $settings['type'] ) ) {
			$settings['type'] = 'text';
		}

		// Handle special configuration for tab_title field type.
		if ( 'tab_title' === $settings['type'] ) {
			// Use tab_title if set, otherwise fall back to title.
			$tab_title = isset( $settings['tab_title'] ) ? $settings['tab_title'] : $settings['title'];

			// Add data-tab-title attribute for JavaScript tab functionality.
			if ( isset( $settings['custom_attributes'] ) ) {
				$settings['custom_attributes'] = array_merge( $settings['custom_attributes'], array( 'data-tab-title' => $tab_title ) );
			} else {
				$settings['custom_attributes'] = array( 'data-tab-title' => $tab_title );
			}

			// Add wcsdm-tab-title CSS class for styling.
			if ( isset( $settings['class'] ) ) {
				$settings['class'] = $settings['class'] . ' wcsdm-tab-title';
			} else {
				$settings['class'] = 'wcsdm-tab-title';
			}
		}

		// Ensure description field exists.
		if ( ! isset( $settings['description'] ) ) {
			$settings['description'] = '';
		}

		// Set the field key if not already present.
		if ( ! isset( $settings['key'] ) ) {
			$settings['key'] = $key;
		}

		return $settings;
	}

	/**
	 * Get the field's unique key identifier.
	 *
	 * Returns the key that uniquely identifies this field within the settings system.
	 * This key is used for form field names, database storage, and field references.
	 *
	 * @since 3.0
	 *
	 * @return string The field's unique key identifier.
	 */
	public function get_key():string {
		return $this->key;
	}

	/**
	 * Get base field settings.
	 *
	 * Returns the field settings array without any rate_field context configurations.
	 * This provides the base configuration that can be used directly for non-rate fields
	 * or as a foundation for building context-specific rate field settings.
	 *
	 * The returned array follows WooCommerce settings API structure and includes
	 * all standard field properties (type, title, description, default, etc.) but
	 * excludes any rate_field specific context overrides.
	 *
	 * @since 3.0
	 *
	 * @return array Base field settings without rate_field configuration.
	 */
	public function get_settings():array {
		$settings = $this->settings;

		// Remove rate_field configuration to return only base settings.
		if ( isset( $settings['rate_field'] ) ) {
			unset( $settings['rate_field'] );
		}

		return $settings;
	}

	/**
	 * Get rate field settings for a specific context.
	 *
	 * Constructs the complete field settings for a specific display context by:
	 * 1. Starting with base field settings
	 * 2. Merging in context-specific rate_field configuration
	 * 3. Applying context-specific overrides (e.g., desc_tip behavior)
	 *
	 * If the field is not configured as a rate field for the given context,
	 * returns the base settings unchanged.
	 *
	 * Special handling for 'dummy' context:
	 * - If 'options' is not set in dummy context but exists in advanced context,
	 *   the options from advanced context are inherited for select fields.
	 *   This allows dummy fields to display proper select options while maintaining
	 *   simplified configuration.
	 *
	 * @since 3.0
	 *
	 * @param string $context The display context ('hidden', 'advanced', or 'dummy').
	 * @return array Complete field settings merged with context-specific configuration.
	 */
	public function get_rate_field_settings( string $context ) {
		$settings = $this->get_settings();

		// Return base settings if this is not a rate field for the given context.
		if ( ! $this->is_rate_field( $context ) ) {
			return $settings;
		}

		// Get the rate field configuration for this context.
		$rate_field = $this->settings['rate_field'][ $context ];

		// For dummy context, inherit select options from advanced context if not explicitly set.
		if ( 'dummy' === $context && ! isset( $rate_field['options'] ) && $this->is_rate_field( 'advanced' ) ) {
			$field_type = $settings['type'] ?? null;

			if ( 'select' === $field_type && isset( $this->settings['rate_field']['advanced']['options'] ) ) {
				$rate_field['options'] = $this->settings['rate_field']['advanced']['options'];
			}
		}

		// Merge base settings with context-specific configuration and overrides.
		return array_merge(
			$settings,
			array_merge(
				$rate_field,
				$this->get_rate_field_settings_overrides( $context )
			)
		);
	}

	/**
	 * Check if this field is configured as a rate field for a specific context.
	 *
	 * Determines whether the field has rate_field configuration enabled for the
	 * specified context. Rate fields can behave differently across contexts,
	 * allowing for flexible display and functionality based on the current mode.
	 *
	 * A field can be a rate field in one context (e.g., 'advanced') but not in
	 * another (e.g., 'hidden'), providing granular control over field behavior.
	 *
	 * @since 3.0
	 *
	 * @param string $context The context to check ('hidden', 'advanced', or 'dummy').
	 * @return bool True if the field is configured as a rate field for the context, false otherwise.
	 */
	public function is_rate_field( string $context ) {
		// Check if rate_field configuration exists at all.
		if ( ! isset( $this->settings['rate_field'] ) ) {
			return false;
		}

		// Check if this specific context is configured.
		return isset( $this->settings['rate_field'][ $context ] );
	}

	/**
	 * Get context-specific setting overrides for rate fields.
	 *
	 * Returns default setting overrides that are automatically applied to rate fields
	 * based on their display context. These overrides ensure proper field behavior
	 * and user experience in different modes:
	 *
	 * - 'advanced' context: Disables desc_tip to show descriptions inline for better
	 *   visibility of detailed configuration information.
	 *
	 * - 'dummy' context: Enables desc_tip to show descriptions as tooltips, keeping
	 *   the interface clean and simple for preview/simplified modes.
	 *
	 * - Other contexts: No overrides applied, using field's base configuration.
	 *
	 * These overrides are applied last in the settings merge chain, ensuring they
	 * take precedence over both base settings and context-specific rate_field config.
	 *
	 * @since 3.0
	 *
	 * @param string $context The context for which to get overrides ('hidden', 'advanced', or 'dummy').
	 * @return array Context-specific setting overrides to be merged with field settings.
	 */
	private function get_rate_field_settings_overrides( string $context ) {
		// Advanced context: show descriptions inline for detailed information.
		if ( 'advanced' === $context ) {
			return array(
				'desc_tip' => false,
			);
		}

		// Dummy context: use tooltips to keep the interface clean.
		if ( 'dummy' === $context ) {
			return array(
				'desc_tip' => true,
			);
		}

		// No overrides for other contexts.
		return array();
	}
}
