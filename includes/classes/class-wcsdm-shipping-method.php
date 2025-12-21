<?php
/**
 * WooReer Shipping Method Class
 *
 * This file contains the main shipping method class for WooReer, a WordPress plugin
 * that calculates WooCommerce shipping costs based on distance using various mapping
 * APIs (Google Maps, Mapbox, etc.).
 *
 * The class handles:
 * - Shipping method configuration and settings management
 * - Distance calculation via API providers
 * - Rate calculation based on distance and custom rules
 * - Integration with WooCommerce shipping zones
 * - Table rates management with advanced rule engine
 * - Admin interface for configuring shipping rates
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
 * WooReer Core Shipping Method Class
 *
 * The main shipping method class that extends WooCommerce's shipping method base.
 * This class provides distance-based shipping rate calculations using various API providers
 * such as Google Maps Distance Matrix API and Mapbox Directions API.
 *
 * Key Features:
 * - Distance-based shipping calculations
 * - Multiple API provider support (Google Maps, Mapbox)
 * - Table rates with advanced rule engine
 * - Shipping class support with per-class pricing
 * - Progressive and flat rate calculation methods
 * - Min/max cost limits and surcharge/discount options
 * - WooCommerce shipping zones integration
 *
 * The class manages:
 * - Admin settings interface with tabbed sections
 * - Rate table management (add, edit, delete, reorder)
 * - API provider configuration and authentication
 * - Store location (origin point) configuration
 * - Shipping cost calculation logic
 * - Integration with WooCommerce checkout process
 *
 * @since 3.0
 */
class Wcsdm_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Rate fields configuration data.
	 *
	 * Stores the configuration for rate-related fields organized by context.
	 * Contains three contexts:
	 * - 'advanced': Fields shown in the rate editor modal dialog
	 * - 'dummy': Fields displayed in the rates table view (read-only display)
	 * - 'hidden': Fields stored as hidden form inputs for data persistence
	 *
	 * Each context contains an array of field definitions with settings like
	 * type, title, description, validation rules, and default values.
	 *
	 * @since 3.0
	 * @var array
	 */
	private $instance_rate_fields = array();

	/**
	 * Default field configuration template.
	 *
	 * Provides default values for all form field configurations to ensure
	 * consistent field structure across the plugin. Fields are merged with
	 * these defaults during initialization.
	 *
	 * Default properties:
	 * - title: Field label displayed to user
	 * - disabled: Whether field is disabled
	 * - class: CSS classes for styling
	 * - css: Inline CSS styles
	 * - placeholder: Input placeholder text
	 * - type: Field type (text, select, checkbox, etc.)
	 * - desc_tip: Whether to show description as tooltip
	 * - description: Help text for the field
	 * - default: Default field value
	 * - custom_attributes: Additional HTML attributes
	 * - is_required: Whether field must have a value
	 *
	 * @since 3.0
	 * @var array
	 */
	private $field_default = array(
		'title'             => '',
		'disabled'          => false,
		'class'             => '',
		'css'               => '',
		'placeholder'       => '',
		'type'              => 'text',
		'desc_tip'          => false,
		'description'       => '',
		'default'           => '',
		'custom_attributes' => array(),
		'is_required'       => false,
	);

	/**
	 * Fields group factory instance.
	 *
	 * Factory class responsible for creating and managing field groups.
	 * Field groups organize related settings into logical sections (tabs)
	 * in the admin interface. The factory provides methods to register,
	 * retrieve, and manage multiple field groups.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group_Factory
	 */
	private $fields_group_factory;

	/**
	 * General settings fields group.
	 *
	 * Contains general plugin configuration fields such as:
	 * - Tax status
	 * - Enable logging
	 * - Round up distance
	 * - Show distance info to customers
	 * - Shipping method label
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_general;

	/**
	 * API Provider settings fields group.
	 *
	 * Contains API provider configuration fields including:
	 * - API provider selection (Google Maps, Mapbox, etc.)
	 * - Provider-specific API keys
	 * - Provider-specific options and settings
	 *
	 * Fields are dynamically shown/hidden based on selected provider.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_api_provider;

	/**
	 * Store location settings fields group.
	 *
	 * Contains store location (origin) configuration fields:
	 * - Store latitude coordinate
	 * - Store longitude coordinate
	 *
	 * These coordinates are used as the origin point for all
	 * distance calculations to customer addresses.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_store_location;

	/**
	 * Total cost calculation settings fields group.
	 *
	 * Contains fields for configuring how shipping costs are calculated:
	 * - Total cost type (flat vs progressive)
	 * - Minimum cost threshold
	 * - Maximum cost cap
	 * - Surcharge type and amount
	 * - Discount type and amount
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_total_cost;

	/**
	 * Table rates settings fields group.
	 *
	 * Contains the table rates field which provides an interactive
	 * interface for managing multiple shipping rate rules.
	 * Includes add, edit, delete, and reorder functionality.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_table_rates;

	/**
	 * Shipping rules settings fields group.
	 *
	 * Contains fields for configuring shipping rate rules:
	 * - Maximum distance
	 * - Min/max order amount
	 * - Min/max order quantity
	 *
	 * These rules determine when rates apply and how they're calculated.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_shipping_rules;

	/**
	 * Rates per shipping class settings fields group.
	 *
	 * Contains fields for setting per distance unit.
	 *
	 * Dynamically generates fields based on configured shipping classes.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_rates;

	/**
	 * Rates per shipping class settings fields group.
	 *
	 * Dynamically generates fields based on configured shipping classes.
	 *
	 * @since 3.0
	 * @var Wcsdm_Fields_Group
	 */
	private $fields_group_per_shipping_class_rates;

	/**
	 * Cached rule callbacks for rate-row matching.
	 *
	 * Built on-demand from rate fields marked as rules (via `is_rule` + `rule_callback`).
	 * Used by {@see Wcsdm_Shipping_Method::is_rate_row_match_rules()} to avoid
	 * re-scanning and re-validating callbacks for every row check.
	 *
	 * @since 3.0
	 *
	 * @var array<string, callable>|null Map of rate field key to rule callback.
	 */
	private $rate_rules_fields;

	/**
	 * API providers manager instance.
	 *
	 * Manages all registered API providers (Google Maps, Mapbox, etc.)
	 * and provides methods to retrieve provider instances for distance
	 * calculations.
	 *
	 * @since 3.0
	 * @var Wcsdm_API_Providers
	 */
	private Wcsdm_API_Providers $providers;

	/**
	 * Constructor for WooReer shipping method.
	 *
	 * Initializes a new instance of the WooReer shipping method with the given instance ID.
	 * Sets up the method identifier, title, description, and supported features.
	 *
	 * The constructor configures:
	 * - Method ID and title for WooCommerce integration
	 * - Admin description displayed in shipping method settings
	 * - Instance ID for zone-specific configuration
	 * - Supported features (shipping zones, instance settings)
	 *
	 * After basic setup, calls init() to complete initialization.
	 *
	 * @since 3.0
	 * @param int $instance_id Optional. ID of the shipping method instance within a zone. Default 0.
	 */
	public function __construct( $instance_id = 0 ) {
		// ID for your shipping method. Should be unique.
		$this->id = WCSDM_METHOD_ID;

		// Title shown in admin.
		$this->method_title = WCSDM_METHOD_TITLE;

		// Title shown in admin.
		$this->title = $this->method_title;

		// Description shown in admin.
		$this->method_description = __( 'WooReer shipping calculator allows you to easily offer shipping rates based on the distance calculated using Google Maps Distance Matrix Service API and other mapping providers.', 'wcsdm' );

		$this->enabled = $this->get_option( 'enabled' );

		$this->instance_id = absint( $instance_id );

		$this->supports = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();
	}

	/**
	 * Initialize the shipping method settings and hooks.
	 *
	 * This method sets up all necessary components for the shipping method including:
	 * - Initializing the API providers
	 * - Registering WordPress/WooCommerce hooks
	 * - Setting up field group factories for settings organization
	 * - Initializing form and rate fields
	 *
	 * Called automatically during object construction.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function init() {
		$this->providers = Wcsdm_API_Providers::init();

		// Register hooks.
		$this->init_hooks();

		$this->init_fields_group_factory();

		// Load the settings API.
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
		$this->init_rate_fields(); // Init rate fields.
	}

	/**
	 * Get a specific value from the POST data for this shipping method instance.
	 *
	 * Retrieves a value from the posted form data using the proper field key format
	 * for this shipping method instance. Returns the default value if the key is not found.
	 *
	 * @since 3.0
	 *
	 * @param string $key     The field key (without the method prefix).
	 * @param mixed  $default Optional. The default value to return if key not found. Default null.
	 *
	 * @return mixed The value from POST data or the default value.
	 */
	public function get_post_data_value( string $key, $default = null ) {
		$post_data = $this->get_post_data();
		$field_key = $this->get_field_key( $key );

		return $post_data[ $field_key ] ?? $default;
	}

	/**
	 * Register WordPress actions and filters hooks for this shipping method.
	 *
	 * Sets up necessary WordPress hooks including:
	 * - Admin options processing hook for saving settings
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize the fields group factory and register all field groups.
	 *
	 * Creates a new fields group factory instance and registers all available field groups:
	 * - General settings group
	 * - API provider settings group
	 * - Store location settings group
	 * - Total cost calculation settings group
	 * - Table rates settings group
	 * - Rates per distance unit settings group
	 * - Shipping rules settings group
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function init_fields_group_factory() {
		$this->fields_group_factory = new Wcsdm_Fields_Group_Factory();

		$this->fields_group_factory->add( $this->get_fields_group_general() );
		$this->fields_group_factory->add( $this->get_fields_group_api_provider() );
		$this->fields_group_factory->add( $this->get_fields_group_store_location() );
		$this->fields_group_factory->add( $this->get_fields_group_total_cost() );
		$this->fields_group_factory->add( $this->get_fields_group_table_rates() );
		$this->fields_group_factory->add( $this->get_fields_group_rates() );
		$this->fields_group_factory->add( $this->get_fields_group_per_shipping_class_rates() );
		$this->fields_group_factory->add( $this->get_fields_group_shipping_rules() );
	}

	/**
	 * Initialize form fields for the shipping method settings.
	 *
	 * Builds the complete array of form fields that appear in the admin settings interface.
	 * Retrieves fields from multiple field groups and flattens them into a single array
	 * that WooCommerce can render.
	 *
	 * The method:
	 * 1. Gets specified field groups from the factory
	 * 2. Extracts all fields from each group
	 * 3. Converts field objects to settings arrays
	 * 4. Applies the 'wcsdm_form_fields' filter for customization
	 * 5. Stores result in instance_form_fields property
	 *
	 * Field groups included:
	 * - General settings (tax status, logging, etc.)
	 * - API provider configuration
	 * - Store location (origin coordinates)
	 * - Total cost calculation settings
	 * - Table rates management
	 *
	 * Developers can modify fields using the 'wcsdm_form_fields' filter hook.
	 *
	 * Example:
	 * ```php
	 * add_filter( 'wcsdm_form_fields', 'my_custom_fields', 10, 2 );
	 * function my_custom_fields( $fields, $instance_id ) {
	 *     $fields['my_field'] = array( 'type' => 'text', 'title' => 'My Field' );
	 *     return $fields;
	 * }
	 * ```
	 *
	 * @since 3.0
	 * @return void
	 */
	public function init_form_fields() {
		// Get the field groups that should be included in the main settings form.
		// These groups contain all the settings that appear in the admin interface.
		$fields_group = $this->fields_group_factory->get_items(
			array(
				'fields_group_general',
				'fields_group_api_provider',
				'fields_group_store_location',
				'fields_group_total_cost',
				'fields_group_table_rates',
			)
		);

		// Flatten the field groups into a single associative array.
		// This converts field objects to arrays that WooCommerce can render.
		$form_fields = array_reduce(
			$fields_group,
			function( $carry, $group ) {
				if ( $group instanceof Wcsdm_Fields_Group ) {
					foreach ( $group->get_fields() as $key => $field ) {
						if ( $field instanceof Wcsdm_Setting_Field ) {
							// Extract the field settings array from the field object.
							$carry[ $key ] = $field->get_settings();
						}
					}
				}

				return $carry;
			},
			array()
		);

		/**
		 * Filter the form fields before they are stored.
		 *
		 * Allows developers to modify, add, or remove form fields dynamically.
		 * This is useful for extending WooReer with custom settings.
		 *
		 * @since 3.0
		 *
		 * @param array $form_fields  The array of form field configurations.
		 * @param int   $instance_id  The shipping method instance ID.
		 *
		 * @return array Modified form fields array.
		 *
		 * Example usage:
		 * ```php
		 * add_filter( 'wcsdm_form_fields', 'my_wcsdm_form_fields', 10, 2 );
		 *
		 * function my_wcsdm_form_fields( $form_fields, $instance_id ) {
		 *     // Add a custom field
		 *     $form_fields['custom_field'] = array(
		 *         'title'       => __( 'Custom Field', 'text-domain' ),
		 *         'type'        => 'text',
		 *         'description' => __( 'Enter custom value', 'text-domain' ),
		 *         'default'     => '',
		 *     );
		 *     return $form_fields;
		 * }
		 * ```
		 */
		$this->instance_form_fields = apply_filters( 'wcsdm_form_fields', $form_fields, $this->get_instance_id() );
	}

	/**
	 * Initialize rate fields configuration.
	 *
	 * This method sets up all rate-related fields by merging field groups and organizing them
	 * into different contexts (advanced, dummy, hidden). Rate fields are used in the shipping
	 * rate table and allow for configurable settings per rate row.
	 *
	 * The rate fields are organized into three contexts:
	 * - advanced: Fields shown in the advanced rate editor modal
	 * - dummy: Fields displayed in the rate table display
	 * - hidden: Fields stored as hidden values in the form
	 *
	 * Developers can modify the rate fields via the 'wcsdm_rate_fields' filter hook.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function init_rate_fields() {
		// Get the field groups that contain rate-related fields.
		// These groups include settings that can be configured per-rate in the table rates.
		$fields_group = $this->fields_group_factory->get_items(
			array(
				'fields_group_general',
				'fields_group_rates',
				'fields_group_per_shipping_class_rates',
				'fields_group_shipping_rules',
				'fields_group_total_cost',
			)
		);

		// Merge all fields from the selected field groups into a single array.
		// This creates a flat array of all available rate fields.
		$rate_fields_merged = array_reduce(
			$fields_group,
			function( $carry, $group ) {
				return array_merge( $carry, $group->get_fields() );
			},
			array()
		);

		// Initialize the rate fields array with three contexts.
		// Each context represents a different usage of the field in the rate table.
		$rate_fields = array(
			'advanced' => array(), // Fields shown in the advanced rate editor modal.
			'dummy'    => array(), // Fields displayed in the rate table (read-only view).
			'hidden'   => array(), // Fields stored as hidden inputs for data persistence.
		);

		// Process each field and organize it into the appropriate contexts.
		foreach ( $rate_fields_merged as $key => $field ) {
			if ( ! $field instanceof Wcsdm_Setting_Field ) {
				continue;
			}

			// Add field to 'advanced' context if it should appear in the rate editor modal.
			if ( $field->is_rate_field( 'advanced' ) ) {
				$rate_fields['advanced'][ $key ] = $field->get_rate_field_settings( 'advanced' );
			}

			// Add field to 'dummy' context if it should display in the rate table.
			if ( $field->is_rate_field( 'dummy' ) ) {
				$rate_fields['dummy'][ $key ] = $field->get_rate_field_settings( 'dummy' );
			}

			// Add field to 'hidden' context if it should be stored as a hidden field.
			if ( $field->is_rate_field( 'hidden' ) ) {
				$rate_fields['hidden'][ $key ] = $field->get_rate_field_settings( 'hidden' );
			}
		}

		/**
		 * Filter the rate fields configuration.
		 *
		 * Allows developers to modify, add, or remove rate fields for the table rates interface.
		 * This is useful for extending WooReer with custom rate-specific settings.
		 *
		 * @since 3.0
		 *
		 * @param array $rate_fields  Array of rate fields organized by context (advanced, dummy, hidden).
		 * @param int   $instance_id  The shipping method instance ID.
		 *
		 * @return array Modified rate fields array.
		 */
		$this->instance_rate_fields = apply_filters( 'wcsdm_rate_fields', $rate_fields, $this->get_instance_id() );
	}

	/**
	 * Get rate fields by context.
	 *
	 * Returns rate fields filtered by context. Available contexts are:
	 * - 'advanced': Fields shown in the advanced rate editor modal
	 * - 'dummy': Fields displayed in the rate table display
	 * - 'hidden': Fields stored as hidden values in the form
	 *
	 * @since 3.0
	 *
	 * @param string $context The context filter for rate fields (advanced, dummy, or hidden).
	 *
	 * @return array Array of rate fields for the specified context, or empty array if context doesn't exist.
	 */
	public function get_rates_fields( $context ) {
		if ( isset( $this->instance_rate_fields[ $context ] ) ) {
			return $this->instance_rate_fields[ $context ];
		}

		return array();
	}

	/**
	 * Generate HTML for a WCSDM custom field.
	 *
	 * This method processes and generates HTML for WCSDM-specific field types.
	 * It populates the field data with defaults and then generates the settings HTML.
	 *
	 * @since 3.0
	 *
	 * @param string $key  The field key identifier.
	 * @param array  $data The field configuration data.
	 *
	 * @return string The generated HTML for the field.
	 */
	public function generate_wcsdm_html( $key, $data ) {
		$data = $this->populate_field( $key, $data );

		return $this->generate_settings_html( array( $key => $data ), false );
	}

	/**
	 * Generate HTML for a tab title field.
	 *
	 * Creates a tab title field that extends the standard title field with custom attributes.
	 * This field type is used to organize settings into tabbed sections in the admin interface.
	 *
	 * @since 3.0
	 *
	 * @param string $key  The field key identifier.
	 * @param array  $data The field configuration data including title, class, and custom_attributes.
	 *
	 * @return string The generated HTML for the tab title field with injected custom attributes.
	 */
	public function generate_tab_title_html( $key, $data ) {
		$output = $this->generate_title_html( $key, $data );

		$defaults = array(
			'title'             => '',
			'class'             => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		$custom_attributes = $this->get_custom_attribute_html( $data );

		if ( $custom_attributes ) {
			$output = preg_replace( '/(<h3[^>]*?)(>)/', '$1 ' . $custom_attributes . '$2', $output );
		}

		return $output;
	}

	/**
	 * Generate HTML for the table rates field.
	 *
	 * Creates a comprehensive table interface for managing shipping rates including:
	 * - Table header with column titles
	 * - Existing rate rows with edit/delete/reorder controls
	 * - Add rate button
	 * - JavaScript templates for rate modals (add, edit, delete)
	 * - Hidden field storage for rate data
	 *
	 * @since 3.0
	 *
	 * @param string $key  The field key identifier.
	 * @param array  $data The field configuration data.
	 *
	 * @return string The generated HTML for the complete table rates management interface.
	 */
	public function generate_table_rates_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$table_rates = $this->get_option( 'table_rates', array() );

		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top">
			<td colspan="2" class="wcsdm-no-padding wcsdm-no-border">
				<table id="wcsdm-table--table_rates--dummy" class="wcsdm-table wcsdm-table--table_rates--dummy widefat striped">
					<thead>
						<?php $this->generate_table_rates_thead(); ?>
					</thead>
					<tbody>
						<?php foreach ( $table_rates as $rate ) : ?>
							<?php $this->generate_table_rates_tbody( $field_key, $rate ); ?>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<?php $this->generate_table_rates_tfoot(); ?>
					</tfoot>
				</table>
				<script type="text/template" id="tmpl-wcsdm-dummy-row">
					<?php $this->generate_table_rates_tbody( $field_key, array(), true ); ?>
				</script>
				<?php $this->generate_js_template_rate_form(); ?>
				<script type="text/template" id="tmpl-wcsdm-modal-rate-delete">
				<div class="wcsdm-modal-overlay wcsdm-modal-show" id="wcsdm-modal-rate-delete">
					<div class="wcsdm-modal-container">
						<div class="wcsdm-modal-content">
							<!-- Header -->
							<div class="wcsdm-modal-header">
								<h3>{{data.title}}</h3>
							</div>

							<!-- Body -->
							<div class="wcsdm-modal-body">
								<p><?php esc_html_e( 'Are you sure you want to delete shipping rate #{{data.rowNumber}}? This action cannot be undone.', 'wcsdm' ); ?></p>
							</div>

							<!-- Footer with two buttons -->
							<div class="wcsdm-modal-footer">
								<div class="wcsdm-modal-footer-actions wcsdm-modal-footer--dual">
									<button type="button" class="wcsdm-modal-btn wcsdm-modal-btn--cancel wcsdm-modal-btn--secondary"><?php esc_html_e( 'Cancel', 'wcsdm' ); ?></button>
									<button type="button" class="wcsdm-modal-btn wcsdm-modal-btn--confirm wcsdm-modal-btn--primary"><?php esc_html_e( 'Confirm', 'wcsdm' ); ?></button>
								</div>
							</div>
						</div>
					</div>
				</div>
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate JavaScript template for the rate form modal.
	 *
	 * Outputs a JavaScript template (using WordPress tmpl) for the add/edit rate modal dialog.
	 * The template includes:
	 * - Modal structure with header, body, and footer
	 * - All advanced rate fields rendered as a form table
	 * - Cancel and Confirm buttons
	 * - Dynamic field population using template variables
	 *
	 * @since 3.0
	 *
	 * @return void Outputs the JavaScript template directly.
	 */
	private function generate_js_template_rate_form() {
		?>
		<script type="text/template" id="tmpl-wcsdm-modal-rate-add">
			<div class="wcsdm-modal-overlay wcsdm-modal-show" id="wcsdm-modal-rate-add">
				<form id="wcsdm-rate-form">
					<div class="wcsdm-modal-container">
						<div class="wcsdm-modal-content">
							<!-- Header -->
							<div class="wcsdm-modal-header">
								<h3>{{data.title}}</h3>
							</div>

							<!-- Body -->
							<div class="wcsdm-modal-body">
								<table class="form-table">
								<?php
								foreach ( $this->get_rates_fields( 'advanced' ) as $key => $data ) {
									$this->generate_settings_html( array( 'fake--field--' . $key => $this->populate_field( $key, $data ) ) );
								}
								?>
								</table>
							</div>

							<!-- Footer with two buttons -->
							<div class="wcsdm-modal-footer">
								<div class="wcsdm-modal-footer-actions wcsdm-modal-footer--dual">
									<button type="button" class="wcsdm-modal-btn wcsdm-modal-btn--cancel wcsdm-modal-btn--secondary"><?php esc_html_e( 'Cancel', 'wcsdm' ); ?></button>
									<button type="submit" class="wcsdm-modal-btn wcsdm-modal-btn--confirm wcsdm-modal-btn--primary"><?php esc_html_e( 'Confirm', 'wcsdm' ); ?></button>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		</script>
		<?php
	}

	/**
	 * Generate CSS class names for rate row columns.
	 *
	 * Creates appropriate CSS classes for table rate columns based on:
	 * - The field key
	 * - The row location (head, body, or foot)
	 * - The field type
	 * - Whether the field is persistent (always visible)
	 *
	 * @since 3.0
	 *
	 * @param string $key      The column/field key.
	 * @param array  $data     The field data configuration.
	 * @param string $location Optional. The row location: 'head', 'body', or 'foot'. Default ''.
	 *
	 * @return string Space-separated CSS class names for the column.
	 */
	private function generate_rate_row_col_class( $key, $data, $location = '' ) {
		$class = 'wcsdm-col wcsdm-col--' . $key;

		switch ( $location ) {
			case 'head':
				$class .= ' wcsdm-col--location--head';
				break;

			case 'foot':
				$class .= ' wcsdm-col--location--foot';
				break;

			default:
				$class .= ' wcsdm-col--location--body';
				break;
		}

		$type = isset( $data['type'] ) ? $data['type'] : '';

		if ( $type ) {
			$class .= ' wcsdm-col--type--' . $type;
		}

		if ( $data['is_persistent'] ?? false ) {
			$class .= ' wcsdm-col--persistent';
		}

		return $class;
	}

	/**
	 * Generate the table rates thead (header row).
	 *
	 * Outputs the header row for the rates table including:
	 * - Row number column
	 * - All dummy field columns with titles and tooltips
	 * - Action column (for edit/delete buttons)
	 *
	 * @since 3.0
	 *
	 * @return void Outputs HTML directly.
	 */
	private function generate_table_rates_thead() {
		$columns = $this->get_rates_fields( 'dummy' );
		?>
			<tr>
				<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( 'row_number', array( 'is_persistent' => true ), 'head' ) ); ?>">#</td>
				<?php foreach ( $columns as $key => $field ) : ?>
				<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( $key, $field, 'head' ) ); ?>">
					<div>
						<span class="label-text"><?php echo esc_html( $field['title'] ); ?></span>
						<?php echo $this->get_tooltip_html( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</td>
				<?php endforeach; ?>
				<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( 'action', array( 'is_persistent' => true ), 'head' ) ); ?>"></td>
			</tr>
		<?php
	}

	/**
	 * Generate the table rates tbody (body row).
	 *
	 * Creates a single data row for the rates table with:
	 * - Row number cell
	 * - Dummy field cells with actual or template values
	 * - Action buttons (edit, delete, move up/down)
	 * - Hidden input fields for rate data storage
	 *
	 * @since 3.0
	 *
	 * @param string $field_key   The field key prefix for naming form inputs.
	 * @param array  $rate        Optional. The rate data for this row. Default array().
	 * @param bool   $is_template Optional. Whether generating a JavaScript template. Default false.
	 *
	 * @return void Outputs HTML directly.
	 */
	private function generate_table_rates_tbody( $field_key, $rate = array(), $is_template = false ) {
		?>
		<tr>
			<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( 'row_number', array( 'is_persistent' => true ), 'head' ) ); ?>"></td>
			<?php foreach ( $this->get_rates_fields( 'dummy' ) as $key => $data ) : ?>
			<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( $key, $data ) ); ?>">
				<?php
				if ( $is_template ) {
					echo '{{data.dummy.' . esc_html( $key ) . '}}';
				} else {
					echo esc_html( $this->get_rate_row_dummy_value( $key, $data, $rate ) );
				}
				?>
			</td>
			<?php endforeach; ?>
			<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( 'action', array( 'is_persistent' => true ), 'head' ) ); ?>">
				<div class="wcsdm-rate-actions">
					<a href="#" class="wcsdm-rate-action wcsdm-rate-action--edit wcsdm-link" title="<?php esc_attr_e( 'Edit Rate', 'wcsdm' ); ?>">
						<span class="dashicons dashicons-admin-generic"></span>
					</a>
					<a href="#" class="wcsdm-rate-action wcsdm-rate-action--delete wcsdm-link" title="<?php esc_attr_e( 'Delete Rate', 'wcsdm' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</a>
					<a href="#" class="wcsdm-rate-action wcsdm-rate-action--move-up wcsdm-link" title="<?php esc_attr_e( 'Move Up', 'wcsdm' ); ?>">
						<span class="dashicons dashicons-arrow-up-alt"></span>
					</a>
					<a href="#" class="wcsdm-rate-action wcsdm-rate-action--move-down wcsdm-link" title="<?php esc_attr_e( 'Move Down', 'wcsdm' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt"></span>
					</a>
				</div>
			</td>
			<?php
			foreach ( $this->get_rates_fields( 'hidden' ) as $hidden_key => $hidden_field ) :
				$hidden_field = $this->populate_field( $hidden_key, $hidden_field );

				if ( $is_template ) {
					// Print template for hidden fields.
					?>
					<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>__<?php echo esc_attr( $hidden_key ); ?>[]" value="{{data.hidden.<?php echo esc_attr( $hidden_key ); ?>}}" data-key="<?php echo esc_attr( $hidden_key ); ?>" data-field="<?php echo esc_attr( wp_json_encode( $hidden_field ) ); ?>" />
					<?php
				} else {
					// Print actual rate fields as hidden fields.
					$hidden_value = $this->get_rate_row_value( $hidden_key, $rate, $hidden_field['default'] );

					switch ( $hidden_field['type'] ) {
						case 'price':
							$hidden_value = wc_format_localized_price( $hidden_value );
							break;

						case 'decimal':
							$hidden_value = wc_format_localized_decimal( $hidden_value );
							break;

						default:
							if ( is_array( $hidden_value ) ) {
								$hidden_value = implode( ', ', $hidden_value );
							}
							break;
					}
					?>
					<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>__<?php echo esc_attr( $hidden_key ); ?>[]" value="<?php echo esc_attr( $hidden_value ); ?>" data-key="<?php echo esc_attr( $hidden_key ); ?>" data-field="<?php echo esc_attr( wp_json_encode( $hidden_field ) ); ?>" />
					<?php
				}
			endforeach;
			?>
		</tr>
		<?php
	}

	/**
	 * Generate the table rates tfoot (footer row).
	 *
	 * Outputs the footer row for the rates table containing:
	 * - Add Rate button with data attributes for the rate form fields
	 *
	 * @since 3.0
	 *
	 * @return void Outputs HTML directly.
	 */
	private function generate_table_rates_tfoot() {
		$fields_dummy       = $this->get_rates_fields( 'dummy' );
		$fields_dummy_count = count( $fields_dummy );
		$colspan            = $fields_dummy_count + 2;

		$default_advanced = array();
		$fields_advanced  = $this->get_rates_fields( 'advanced' );

		foreach ( $fields_advanced as $key => $field ) {
			$default_advanced[ $key ] = array(
				'field' => $field,
				'value' => $field['default'] ?? '',
			);
		}
		?>
			<tr>
				<td class="<?php echo esc_attr( $this->generate_rate_row_col_class( 'add_rate', array( 'is_persistent' => true ), 'foot' ) ); ?>" colspan="<?php echo esc_attr( $colspan ); ?>">
					<button type="button" class="button button-primary wcsdm-rate-action wcsdm-rate-action--add" title="<?php esc_attr_e( 'Add Rate', 'wcsdm' ); ?>" data-fields="<?php echo esc_attr( wp_json_encode( $default_advanced ) ); ?>">
						<?php esc_html_e( 'Add Rate', 'wcsdm' ); ?>
					</button>
				</td>
			</tr>
		<?php
	}

	/**
	 * Format an error message with field group context.
	 *
	 * Prepends the field group title to the error message to provide better context
	 * about where the error occurred in the settings interface.
	 *
	 * @since 3.0
	 *
	 * @param string $error_message The original error message.
	 * @param array  $field         The field configuration containing optional 'group' key.
	 *
	 * @return string The formatted error message with group context, or original message if no group found.
	 */
	public function format_error_message( string $error_message, array $field ):string {
		$field_group = $this->fields_group_factory->get_item( $field['group'] ?? '' );

		if ( $field_group ) {
			$group_data  = $field_group->get_data();
			$group_title = $group_data['tab_title'] ?? $group_data['title'] ?? null;

			if ( $group_title ) {
				return $group_title . ' » ' . $error_message;
			}
		}

		return $error_message;
	}

	/**
	 * Validate a WCSDM custom field.
	 *
	 * Performs validation on WCSDM field types with special handling for:
	 * - API provider-specific fields (only validates when provider is selected)
	 * - Required fields
	 * - Custom validation via wcsdm_validate_option() helper
	 *
	 * Adds user-facing errors if validation fails.
	 *
	 * @since 3.0
	 *
	 * @param string $key   The field key.
	 * @param mixed  $value The posted value.
	 *
	 * @return mixed The validated value (may be sanitized/transformed).
	 */
	public function validate_wcsdm_field( $key, $value ) {
		$field        = isset( $this->instance_form_fields[ $key ] ) ? $this->instance_form_fields[ $key ] : false;
		$post_data    = $this->get_post_data();
		$api_provider = $post_data[ $this->get_field_key( 'api_provider' ) ] ?? '';

		if ( $field ) {
			$field              = $this->populate_field( $key, $field );
			$value              = $this->get_field_value( $key, $field, $post_data );
			$api_provider_field = $field['api_provider_field'] ?? null;

			if ( $api_provider_field && $api_provider_field !== $api_provider ) {
				// Skip validation if the field is not for the selected API provider.
				return $value;
			}

			$validation = wcsdm_validate_option( $value, $field, $key, $this );

			if ( is_wp_error( $validation ) ) {
				$this->add_error( $this->format_error_message( $validation->get_error_message(), $field ) );
			}

			return $value;
		}

		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Validate and format the table_rates field.
	 *
	 * Performs comprehensive validation on table rates data including:
	 * - Ensuring table rates are not empty
	 * - Validating each field in each rate row
	 * - Checking for duplicate rate rules
	 *
	 * Adds user-facing errors for any validation failures.
	 *
	 * Developers can modify the validated rates via the 'wcsdm_validate_table_rates' filter.
	 *
	 * @since 3.0
	 *
	 * @param string $key The field key for table_rates.
	 *
	 * @return array The validated and filtered table rates data.
	 */
	public function validate_table_rates_field( string $key ) {
		$is_error = false;
		$rates    = $this->get_table_rates_form_post_data( $this->get_post_data(), $key );
		$field    = isset( $this->instance_form_fields[ $key ] ) ? $this->instance_form_fields[ $key ] : array();

		if ( empty( $rates ) ) {
			$this->add_error( $this->format_error_message( __( 'Table rates settings cannot be empty', 'wcsdm' ), $field ) );
			$is_error = true;
		}

		$rates_rule_fields = array();
		$rate_fields       = $this->get_rates_fields( 'hidden' );

		if ( ! $is_error ) {
			foreach ( $rates as $index => $rate ) {
				$item_rule = array();

				foreach ( $rate_fields as $rate_field_key => $rate_field ) {
					$rate_field_value = $rate[ $rate_field_key ] ?? null;

					if ( isset( $rate_field['is_rule'] ) && $rate_field['is_rule'] ) {
						$item_rule[ $rate_field_key ] = $rate_field_value;
					}

					$field_error = wcsdm_validate_option( $rate_field_value, $rate_field, $rate_field_key, $this );

					if ( is_wp_error( $field_error ) ) {
						// translators: %1$d = row number, %2$s = error message.
						$this->add_error( $this->format_error_message( wp_sprintf( __( 'Row #%1$d » %2$s', 'wcsdm' ), ( $index + 1 ), $field_error->get_error_message() ), $field ) );
						$is_error = true;
					}
				}

				$rates_rule_fields[ $index ] = wp_json_encode( $item_rule );
			}
		}

		if ( ! $is_error ) {
			foreach ( $rates_rule_fields as $index => $rate_rules ) {
				if ( 0 === $index ) {
					continue;
				}

				$sliced = array_slice( $rates_rule_fields, 0, $index, true );

				$duplicate_index = array_search( $rate_rules, $sliced, true );

				if ( false !== $duplicate_index ) {
					// translators: %1$d = row number, %2$d = duplicate row number.
					$this->add_error( $this->format_error_message( wp_sprintf( __( 'Row #%1$d » Rules is duplicate of row #%2$d', 'wcsdm' ), ( $index + 1 ), ( $duplicate_index + 1 ) ), $field ) );
					$is_error = true;
				}
			}
		}

		/**
		 * Developers can modify the $filtered_values var via filter hooks.
		 *
		 * @since 3.0
		 *
		 * This example shows how you can modify the filtered table rates data via custom function:
		 *
		 *      add_filter( 'wcsdm_validate_table_rates', 'my_wcsdm_validate_table_rates', 10, 2 );
		 *
		 *      function my_wcsdm_validate_table_rates( $filtered_values, $instance_id ) {
		 *          return array();
		 *      }
		 */
		return apply_filters( 'wcsdm_validate_table_rates', $rates, $this->get_instance_id(), $is_error );
	}

	/**
	 * Validate number field value.
	 *
	 * Validates a number field by delegating to the parent's price field validation.
	 * This ensures numeric values are properly sanitized and validated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value to validate.
	 *
	 * @return string Validated field value.
	 */
	public function validate_number_field( $key, $value ) {
		return parent::validate_price_field( $key, $value );
	}

	/**
	 * Populate field data with defaults and metadata.
	 *
	 * Merges field data with defaults and adds metadata attributes including:
	 * - CSS classes for field types, contexts, and requirements
	 * - Data attributes for JavaScript access
	 * - Field configuration data
	 *
	 * @since 3.0
	 *
	 * @param string $key  The field key.
	 * @param array  $data The field configuration data.
	 *
	 * @return array The populated field data with all defaults and metadata.
	 */
	private function populate_field( $key, $data ) {
		// Merge field data with default field template to ensure all required properties exist.
		$data = wp_parse_args( $data, $this->field_default );

		// Extract existing CSS classes into an array for manipulation.
		$data_classes = isset( $data['class'] ) ? explode( ' ', $data['class'] ) : array();

		// Add base CSS classes for JavaScript targeting and styling.
		array_push( $data_classes, 'wcsdm-field', 'wcsdm-field-key--' . $key, 'wcsdm-field-type--' . $data['type'] );

		// Add rate-specific CSS classes if this field is used in rate configurations.
		if ( isset( $data['is_rate'] ) && $data['is_rate'] ) {
			array_push( $data_classes, 'wcsdm-field--rate' );
			array_push( $data_classes, 'wcsdm-field--rate--' . $data['type'] );
			array_push( $data_classes, 'wcsdm-field--rate--' . $key );
		}

		// Add rule-specific CSS classes if this field is part of shipping rules logic.
		if ( isset( $data['is_rule'] ) && $data['is_rule'] ) {
			array_push( $data_classes, 'wcsdm-field--rule' );
			array_push( $data_classes, 'wcsdm-field--rule--' . $data['type'] );
			array_push( $data_classes, 'wcsdm-field--rule--' . $key );
		}

		// Add context-specific CSS classes for different field contexts (advanced, dummy, hidden).
		if ( isset( $data['rate_context'] ) && $data['rate_context'] ) {
			foreach ( $data['rate_context'] as $context ) {
				array_push( $data_classes, 'wcsdm-field--context--' . $context );
				array_push( $data_classes, 'wcsdm-field--context--' . $context . '--' . $data['type'] );
				array_push( $data_classes, 'wcsdm-field--context--' . $context . '--' . $key );
			}
		}

		// Add required field CSS class for client-side validation styling.
		$data_is_required = isset( $data['is_required'] ) && $data['is_required'];

		if ( $data_is_required ) {
			array_push( $data_classes, 'wcsdm-field--is-required' );
		}

		// Clean up and reassemble the CSS classes string.
		// Remove duplicates, empty values, and trim whitespace.
		$data['class'] = implode( ' ', array_map( 'trim', array_unique( array_filter( $data_classes ) ) ) );

		// Build data attributes for JavaScript access to field metadata.
		$custom_attributes = array(
			'data-key'   => $key,
			'data-id'    => $this->get_field_key( $key ),
			'data-title' => isset( $data['title'] ) ? $data['title'] : $key,
		);

		// List of field properties that should be exposed as data attributes.
		$data_keys = array(
			'type',
			'is_rate',
			'is_required',
			'is_rule',
			'rate_context',
			'options',
		);

		// Add data attributes for each property, converting arrays and booleans appropriately.
		foreach ( $data_keys as $data_key ) {
			if ( ! isset( $data[ $data_key ] ) ) {
				continue;
			}

			if ( is_array( $data[ $data_key ] ) ) {
				// JSON encode arrays for JavaScript parsing.
				$custom_attributes[ 'data-' . $data_key ] = wp_json_encode( $data[ $data_key ] );
			} elseif ( is_bool( $data[ $data_key ] ) ) {
				// Convert boolean to 1/0 for data attributes.
				$custom_attributes[ 'data-' . $data_key ] = $data[ $data_key ] ? 1 : 0;
			} else {
				// Use value as-is for strings and numbers.
				$custom_attributes[ 'data-' . $data_key ] = $data[ $data_key ];
			}
		}

		// Merge custom attributes with existing ones, preserving any manually set attributes.
		$data['custom_attributes'] = array_merge( $data['custom_attributes'], $custom_attributes );

		return $data;
	}

	/**
	 * Extract table rates data from POST request.
	 *
	 * Parses the posted form data to reconstruct table rates array from individual
	 * field arrays. Each rate field is posted as an array, and this method combines
	 * them back into rate row objects.
	 *
	 * @since 3.0
	 *
	 * @param array  $post_data The complete POST data array.
	 * @param string $key       The field key for table_rates.
	 *
	 * @return array Array of rate objects, each containing all field values for that rate.
	 */
	public function get_table_rates_form_post_data( array $post_data, string $key ):array {
		$post_data_key = $this->get_field_key( $key );
		$rate_fields   = $this->get_rates_fields( 'hidden' );
		$rates         = array();

		foreach ( $rate_fields as $rate_field_key => $rate_field ) {
			$field_key = $post_data_key . '__' . $rate_field_key;

			$values = isset( $post_data[ $field_key ] ) ? (array) $post_data[ $field_key ] : array();

			foreach ( $values as $index => $value ) {
				$rates[ $index ][ $rate_field_key ] = $this->get_field_value(
					$rate_field_key,
					$rate_field,
					array(
						$this->get_field_key( $rate_field_key ) => $value,
					)
				);
			}
		}

		return $rates;
	}

	/**
	 * Processes and saves shipping method instance options in the admin area.
	 *
	 * Overrides the parent method to provide custom handling for instance settings:
	 * - Validates the correct instance is being processed
	 * - Applies custom field validation via validate_wcsdm_field()
	 * - Prevents success notice display if errors occur
	 * - Adds data version to saved settings
	 *
	 * On validation errors, filters are added to restore original values and hide success notices.
	 *
	 * @since 3.0
	 *
	 * @return bool True if settings were saved successfully, false if validation errors occurred.
	 */
	public function process_admin_options() {
		if ( ! $this->instance_id ) {
			return parent::process_admin_options();
		}

		// Check we are processing the correct form for this instance.
		if ( ! isset( $_REQUEST['instance_id'] ) || absint( $_REQUEST['instance_id'] ) !== $this->instance_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$this->init_instance_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_instance_form_fields() as $key => $field ) {
			$field_type = $this->get_field_type( $field );

			if ( ! in_array( $field_type, array( 'title', 'tab_title' ), true ) ) {
				$field['type'] = 'wcsdm';

				try {
					$this->instance_settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
				} catch ( Exception $e ) {
					$this->add_error( $this->format_error_message( $e->getMessage(), $field ) );
				}
			}
		}

		if ( $this->get_errors() ) {
			add_filter( 'woocommerce_shipping_' . $this->id . '_instance_option', array( $this, 'instance_option_on_saving' ), 999, 3 );
			add_action( 'admin_footer', array( $this, 'hide_success_notice' ), 10 );

			return false;
		}

		$instance_settings = apply_filters(
			'wcsdm_instance_settings_values',
			array_merge(
				$this->instance_settings,
				array(
					'data_version' => WCSDM_DATA_VERSION,
				)
			),
			$this
		);

		return update_option( $this->get_instance_option_key(), $instance_settings, 'yes' );
	}

	/**
	 * Filter callback to restore instance option values when save fails.
	 *
	 * Used as a filter on 'woocommerce_shipping_{id}_instance_option' to restore
	 * the original POST values when validation errors prevent saving. This ensures
	 * the user's input is preserved in the form for correction.
	 *
	 * @since 3.0
	 *
	 * @param mixed                 $option   The current option value.
	 * @param string                $key      The option key.
	 * @param Wcsdm_Shipping_Method $instance The shipping method instance.
	 *
	 * @return mixed The value from POST data or the original option value.
	 */
	public function instance_option_on_saving( $option, string $key, Wcsdm_Shipping_Method $instance ) {
		$post_data = $instance->get_post_data();

		switch ( $key ) {
			case 'table_rates':
				return $this->get_table_rates_form_post_data( $post_data, $key );

			default:
				$field = $this->instance_form_fields[ $key ] ?? null;

				if ( $field ) {
					return $this->get_field_value( $key, $field, $post_data ) ?? $option;
				}

				return $post_data[ $this->get_field_key( $key ) ] ?? $option;
		}
	}

	/**
	 * Get the currently selected API provider instance.
	 *
	 * Retrieves the API provider object that is currently configured for this shipping
	 * method instance. The provider is determined by the 'api_provider' option setting.
	 *
	 * @since 3.0.2
	 *
	 * @param string $context Optional. The context for retrieving the provider: 'calculation' or 'settings'. Default 'calculation'.
	 *
	 * @return Wcsdm_API_Provider_Base|null The selected provider instance, or null if no valid provider is configured.
	 */
	public function get_selected_provider( string $context = 'calculation' ):?Wcsdm_API_Provider_Base {
		if ( 'settings' === $context ) {
			$api_provider = $this->get_post_data_value( 'api_provider' );
		} else {
			$api_provider = $this->get_option( 'api_provider', '' );
		}

		return $this->providers->get_provider( $api_provider );
	}

	/**
	 * Gets the data version of the shipping method instance.
	 *
	 * Retrieves the stored data version for this shipping method instance, which is used
	 * to determine if data migrations are needed. Temporarily adds the data_version field
	 * to instance_form_fields to leverage WooCommerce's get_option method,
	 * then removes it to prevent it from appearing in the admin settings.
	 *
	 * The data version tracks the structure and format of stored settings data and is
	 * incremented when breaking changes are introduced that require migrations.
	 *
	 * @since 3.0
	 * @return string|null The data version string if set, null otherwise.
	 */
	private function get_data_version():?string {
		$unset = false;

		if ( ! isset( $this->instance_form_fields['data_version'] ) ) {
			$this->instance_form_fields['data_version'] = array();
			$unset                                      = true;
		}

		$data_version = $this->get_option( 'data_version', null );

		if ( $unset ) {
			unset( $this->instance_form_fields['data_version'] );
		}

		if ( ! $data_version ) {
			$data_version = get_option( 'wcsdm_data_version_' . $this->get_instance_id(), null );
		}

		return $data_version;
	}

	/**
	 * Get the destination location for distance calculation.
	 *
	 * Constructs a Wcsdm_Location object from the package's destination address array.
	 * This location is used as the endpoint for distance calculations.
	 *
	 * Developers can modify the destination via the 'wcsdm_calculate_distance_destination' filter.
	 *
	 * @since 3.0
	 *
	 * @param array $package The WooCommerce shipping package containing destination information.
	 *
	 * @return Wcsdm_Location The destination location object.
	 */
	private function get_calculate_distance_destination( $package ):Wcsdm_Location {
		$pre = apply_filters(
			'wcsdm_calculate_distance_destination_pre',
			null,
			$package,
			$this
		);

		if ( $pre instanceof Wcsdm_Location ) {
			return $pre;
		}

		$calculate_distance_destination = Wcsdm_Location::from_address_array( $package['destination'] ?? array() );

		return apply_filters(
			'wcsdm_calculate_distance_destination',
			$calculate_distance_destination,
			$package,
			$this
		);
	}

	/**
	 * Get the origin location for distance calculation.
	 *
	 * Constructs a Wcsdm_Location object from the configured store coordinates.
	 * This location is used as the starting point for distance calculations.
	 *
	 * Developers can modify the origin via the 'wcsdm_calculate_distance_origin' filter.
	 *
	 * @since 3.0
	 *
	 * @param array $package The WooCommerce shipping package (for filter context).
	 *
	 * @return Wcsdm_Location The origin location object.
	 */
	private function get_calculate_distance_origin( $package ):Wcsdm_Location {
		$calculate_distance_origin = Wcsdm_Location::from_coordinates(
			(float) $this->get_option( 'origin_lat' ),
			(float) $this->get_option( 'origin_lng' )
		);

		return apply_filters(
			'wcsdm_calculate_distance_origin',
			$calculate_distance_origin,
			$this,
			$package
		);
	}

	/**
	 * Calculate shipping rates for the given package.
	 *
	 * This is the main method called by WooCommerce to calculate shipping costs.
	 * It performs distance calculation using the configured API provider and adds
	 * the calculated rate to WooCommerce's available shipping methods.
	 *
	 * Process flow:
	 * 1. Get the API provider instance
	 * 2. Build destination and origin location objects
	 * 3. Call the API to calculate distance
	 * 4. Calculate shipping cost based on distance and rate configuration
	 * 5. Add the rate to WooCommerce
	 *
	 * If no provider is configured, falls back to legacy shipping calculation method.
	 * All API calls and calculations are wrapped in try-catch to prevent checkout errors.
	 *
	 * @since 3.0
	 *
	 * @param array $package Optional. The WooCommerce shipping package. Default array().
	 *
	 * @return void Adds rates to WooCommerce via add_rate() or logs errors.
	 */
	public function calculate_shipping( $package = array() ) {
		$data_version = $this->get_data_version();

		// Bail early when no data version is found.
		// It is likely the instance is not properly set up yet.
		if ( ! $data_version ) {
			return;
		}

		// Handle legacy shipping calculation for instances with data version < 3.0.0.0.
		if ( version_compare( $data_version, '3.0.0.', '<' ) ) {
			// Wrap API calls and rate calculations in try-catch to gracefully handle:
			// 1. Network errors and API failures when making distance calculation requests.
			// 2. Invalid response data that could cause runtime errors.
			// 3. Any other exceptions that may occur during rate calculation.
			// This ensures shipping calculation can fail gracefully without breaking checkout.
			try {
				$legacy = new Wcsdm_Legacy_Shipping_Method( $this->instance_id );

				$legacy->calculate_shipping( $package );
			} catch ( \Throwable $th ) {
				$this->maybe_write_log(
					'error',
					$th->getMessage(),
					array(
						'package' => $package,
					)
				);
			}

			return;
		}

		$provider = $this->get_selected_provider();

		// If provider is not available, do not proceed with distance calculation.
		// It is likely the API provider is not configured yet.
		if ( ! $provider ) {
			return;
		}

		$calculate_distance_destination = $this->get_calculate_distance_destination( $package );

		if ( $calculate_distance_destination->is_error() ) {
			return;
		}

		$calculate_distance_origin = $this->get_calculate_distance_origin( $package );

		if ( $calculate_distance_origin->is_error() ) {
			return;
		}

		// Wrap API calls and rate calculations in try-catch to gracefully handle:
		// 1. Network errors and API failures when making distance calculation requests.
		// 2. Invalid response data that could cause runtime errors.
		// 3. Any other exceptions that may occur during rate calculation.
		// This ensures shipping calculation can fail gracefully without breaking checkout.
		try {
			/**
			 * Filters the distance calculation result before API call.
			 *
			 * This filter allows developers to bypass the default API-based distance calculation
			 * by providing a pre-calculated result. If a valid Wcsdm_Calculate_Distance_Result
			 * instance is returned, the API call will be skipped.
			 *
			 * @since 3.0
			 *
			 * @param Wcsdm_Calculate_Distance_Result|null $result      Pre-calculated distance result or null to proceed with API call.
			 * @param Wcsdm_Location                       $destination The destination location for distance calculation.
			 * @param Wcsdm_Location                       $origin      The origin location for distance calculation.
			 * @param Wcsdm_Shipping_Method                $instance    The shipping method instance.
			 */
			$result_pre = apply_filters(
				'wcsdm_calculate_distance_pre',
				null,
				$calculate_distance_destination,
				$calculate_distance_origin,
				$this
			);

			// If no pre-calculated result is provided, proceed with API call.
			if ( $result_pre instanceof Wcsdm_Calculate_Distance_Result ) {
				$result = $result_pre;
			} else {
				$result = $provider->calculate_distance(
					$calculate_distance_destination,
					$calculate_distance_origin,
					$this,
				);

				do_action(
					'wcsdm_calculate_distance_post',
					$result,
					$calculate_distance_destination,
					$calculate_distance_origin,
					$this
				);
			}

			if ( $result->is_error() ) {
				$this->maybe_write_log(
					'error',
					'API Error: ' . $result->get_error(),
					$result->get_dispatcher()->to_array()
				);
				return;
			}

			$distance = $result->get_distance();

			if ( 'yes' === $this->get_option( 'round_up_distance' ) ) {
				$distance->set_ceiling( true );
			}

			$rate_row_matched = $this->get_rate_row_match_rules( $distance, $package );

			if ( ! $rate_row_matched ) {
				$this->maybe_write_log(
					'info',
					'No rate row matched for the calculated distance.',
					array(
						'distance'    => $distance->in_unit( $this->get_option( 'distance_unit', 'km' ) ),
						'table_rates' => $this->get_option( 'table_rates', array() ),
						'package'     => $package,
					)
				);
				return;
			}

			$rate = array(
				'id'        => $this->get_rate_id(),
				'cost'      => $this->get_rate_cost_by_rate_row( $rate_row_matched, $distance, $package ),
				'label'     => $this->get_rate_label_by_rate_row( $rate_row_matched, $distance ),
				'package'   => $package,
				'meta_data' => $result->get_dispatcher()->to_array(),
			);

			$this->add_rate( $rate );
		} catch ( \Throwable $th ) {
			$this->maybe_write_log(
				'error',
				$th->getMessage(),
				array(
					'package' => $package,
				)
			);
		}
	}

	/**
	 * Hide WooCommerce success notice on validation errors.
	 *
	 * When shipping method settings fail validation, WooCommerce still displays
	 * a success notice which is misleading. This method outputs CSS to hide that
	 * notice, ensuring only error messages are visible to the admin.
	 *
	 * Called via admin_footer action hook when validation errors are present.
	 *
	 * @since 3.0
	 * @return void
	 */
	public static function hide_success_notice() {
		?>
		<style>
			.updated.inline {
				display: none;
			}
		</style>
		<?php
	}

	/**
	 * Get or create the general settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group containing general plugin settings:
	 * - Tax status
	 * - Enable log
	 * - Round up distance
	 * - Show distance info
	 * - Shipping label
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The general settings fields group instance.
	 */
	public function get_fields_group_general():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_general ) ) {
			return $this->fields_group_general;
		}

		$this->fields_group_general = new Wcsdm_Fields_Group(
			'fields_group_general',
			array(
				// Field settings.
				'type'       => 'tab_title',
				'title'      => __( 'General', 'wcsdm' ),
				'tab_title'  => __( 'General', 'wcsdm' ),

				// Rate field settings.
				'rate_field' => array(
					'advanced' => true,
				),
			)
		);

		$fields = array(
			'tax_status'        => array(
				// Field settings.
				'type'        => 'select',
				'title'       => __( 'Tax Status', 'wcsdm' ),
				'description' => __( 'Tax status of fee.', 'wcsdm' ),
				'default'     => 'taxable',
				'options'     => array(
					'taxable' => __( 'Taxable', 'wcsdm' ),
					'none'    => __( 'None', 'wcsdm' ),
				),
			),
			'enable_log'        => array(
				// Field settings.
				'type'        => 'checkbox',
				'title'       => __( 'Enable Log', 'wcsdm' ),
				'description' => __( 'Write data to WooCommerce System Status Report Log for importance event such API response error and shipping calculation failures. <a href="admin.php?page=wc-status&tab=logs" target="_blank">Click here</a> to view the log data.', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
			),
			'distance_unit'     => array(
				'title'       => __( 'Distance Units', 'wcsdm' ),
				'description' => __( 'Sets whether distance is measured in miles or kilometers.', 'wcsdm' ),
				'type'        => 'select',
				'default'     => 'km',
				'options'     => array(
					'km' => __( 'Kilometer', 'wcsdm' ),
					'mi' => __( 'Mile', 'wcsdm' ),
				),
			),
			'round_up_distance' => array(
				// Field settings.
				'type'        => 'checkbox',
				'title'       => __( 'Round Up Distance', 'wcsdm' ),
				'description' => __( 'When enabled, the calculated shipping distance is always rounded up to the next whole number (for example, 3.1 becomes 4).', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
			),
			'show_distance'     => array(
				// Field settings.
				'type'        => 'checkbox',
				'title'       => __( 'Show Distance Info', 'wcsdm' ),
				'description' => __( 'Display the calculated shipping distance to the customer on the checkout page.', 'wcsdm' ),
				'label'       => __( 'Yes', 'wcsdm' ),
			),
			'title'             => array(
				// Field settings.
				'type'        => 'text',
				'title'       => __( 'Label', 'wcsdm' ),
				'description' => __( 'This controls the label which the user sees during checkout.', 'wcsdm' ),
				'default'     => $this->method_title,

				// Rate field settings.
				'rate_field'  => array(
					'hidden'   => array(
						'default' => '',
					),
					'advanced' => array(
						'description' => __( 'Leave empty to inherit the global label setting.', 'wcsdm' ),
						'placeholder' => __( 'Inherit', 'wcsdm' ),
						'default'     => '',
					),
				),
			),
		);

		foreach ( $fields as $key => $field ) {
			$this->fields_group_general->add_field( $field, $key );
		}

		return $this->fields_group_general;
	}

	/**
	 * Get or create the API provider settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group containing API provider configuration:
	 * - API provider selection dropdown
	 * - Provider-specific settings fields (API keys, options, etc.)
	 *
	 * Dynamically generates fields based on registered providers and their requirements.
	 * Provider-specific fields are shown/hidden via JavaScript based on the selected provider.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The API provider settings fields group instance.
	 */
	public function get_fields_group_api_provider():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_api_provider ) ) {
			return $this->fields_group_api_provider;
		}

		$this->fields_group_api_provider = new Wcsdm_Fields_Group(
			'fields_group_api_provider',
			array(
				// Field settings.
				'type'      => 'tab_title',
				'title'     => __( 'Distance Calculator API Settings', 'wcsdm' ),

				// Cutom settings.
				'tab_title' => __( 'Distance Calculator API', 'wcsdm' ),
			)
		);

		$fields = array(
			'api_provider' => array(
				// Field settings.
				'title'       => __( 'API Provider', 'wcsdm' ),
				'type'        => 'select',
				'description' => __( 'Choose the distance calculation API provider that will be used to calculate distances between your store and customer locations. Each provider offers different features, pricing, and accuracy levels.', 'wcsdm' ),
				'options'     => array(
					'' => __( 'Select API Provider', 'wcsdm' ),
				),

				// Cutom settings.
				'is_required' => true,
			),
		);

		$providers = $this->providers->get_all_providers();

		foreach ( $providers as $provider ) {
			$provider_slug            = $provider->get_slug();
			$provider_name            = $provider->get_name();
			$provider_settings_fields = $provider->get_settings_fields( 'settings' );

			if ( ! isset( $fields['api_provider']['options'][ $provider_slug ] ) ) {
				$fields['api_provider']['options'][ $provider_slug ] = $provider_name;
			}

			foreach ( $provider_settings_fields as $field_key => $field ) {
				$field_key = $provider->get_field_key( $field_key );

				if ( ! empty( $field['documentation'] ) ) {
					if ( ! empty( $field['description'] ) ) {
						$field['description'] = esc_html( $field['description'] ) . ' <a href="' . esc_url( $field['documentation'] ) . '" target="_blank">' . esc_html__( 'Read documentation »', 'wcsdm' ) . '</a>';
					} else {
						$field['description'] = '<a href="' . esc_url( $field['documentation'] ) . '" target="_blank">' . esc_html__( 'Read documentation »', 'wcsdm' ) . '</a>';
					}
				}

				if ( ! empty( $field['class'] ) ) {
					$field['class'] = $field['class'] . ' api-provider-field';
				} else {
					$field['class'] = 'api-provider-field';
				}

				if ( isset( $field['custom_attributes'] ) ) {
					$field['custom_attributes'] = array_merge(
						$field['custom_attributes'],
						array(
							'data-api-provider' => $provider_slug,
						)
					);
				} else {
					$field['custom_attributes'] = array(
						'data-api-provider' => $provider_slug,
					);
				}

				$fields[ $field_key ] = array_merge(
					$field,
					array(
						'api_provider_field' => $provider_slug,
					)
				);
			}
		}

		foreach ( $fields as $key => $field ) {
			$this->fields_group_api_provider->add_field( $field, $key );
		}

		return $this->fields_group_api_provider;
	}

	/**
	 * Get or create the store location settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group for configuring the store's physical location:
	 * - Store latitude coordinate
	 * - Store longitude coordinate
	 *
	 * These coordinates serve as the origin point for all distance calculations.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The store location settings fields group instance.
	 */
	public function get_fields_group_store_location():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_store_location ) ) {
			return $this->fields_group_store_location;
		}

		$this->fields_group_store_location = new Wcsdm_Fields_Group(
			'fields_group_store_location',
			array(
				// Field settings.
				'type'        => 'tab_title',
				'title'       => __( 'Store Location Settings', 'wcsdm' ),
				'description' => __( 'Configure your store\'s physical location coordinates. These coordinates are used as the starting point for distance calculations to customer addresses. You can find your coordinates using Google Maps or other mapping services.', 'wcsdm' ),

				// Custom settings.
				'tab_title'   => __( 'Store Location', 'wcsdm' ),
			)
		);

		$fields = array(
			'origin_lat' => array(
				// Field settings.
				'title'             => __( 'Store Location Latitude', 'wcsdm' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '-90',
					'max'  => '90',
				),
				'description'       => __( 'Enter your store\'s latitude coordinate. You can find your coordinates using Google Maps or other mapping services.', 'wcsdm' ),
				'is_required'       => true,
			),
			'origin_lng' => array(
				// Field settings.
				'title'             => __( 'Store Location Longitude', 'wcsdm' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '-180',
					'max'  => '180',
				),
				'description'       => __( 'Enter your store\'s longitude coordinate. You can find your coordinates using Google Maps or other mapping services.', 'wcsdm' ),
				'is_required'       => true,
			),
		);

		foreach ( $fields as $key => $field ) {
			$this->fields_group_store_location->add_field( $field, $key );
		}

		return $this->fields_group_store_location;
	}

	/**
	 * Get or create the total cost calculation settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group for configuring how shipping costs are calculated:
	 * - Total cost type (flat vs progressive calculation methods)
	 * - Minimum cost threshold
	 * - Maximum cost cap
	 * - Surcharge type and amount
	 * - Discount type and amount
	 *
	 * These settings affect how individual rate costs are aggregated and adjusted.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The total cost calculation settings fields group instance.
	 */
	public function get_fields_group_total_cost():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_total_cost ) ) {
			return $this->fields_group_total_cost;
		}

		$this->fields_group_total_cost = new Wcsdm_Fields_Group(
			'fields_group_total_cost',
			array(
				// Field settings.
				'type'       => 'tab_title',
				'title'      => __( 'Total Cost', 'wcsdm' ),
				'tab_title'  => __( 'Total Cost', 'wcsdm' ),

				// Rate field settings.
				'rate_field' => array(
					'advanced' => true,
				),
			)
		);

		$total_cost_type_mapping = array(
			'flat__highest'                   => array(
				'label'       => __( 'Flat: Maximum', 'wcsdm' ),
				'description' => __( 'Set highest item cost as total.', 'wcsdm' ),
			),
			'flat__average'                   => array(
				'label'       => __( 'Flat: Average', 'wcsdm' ),
				'description' => __( 'Set average item cost as total.', 'wcsdm' ),
			),
			'flat__lowest'                    => array(
				'label'       => __( 'Flat: Minimum', 'wcsdm' ),
				'description' => __( 'Set lowest item cost as total.', 'wcsdm' ),
			),
			'progressive__per_shipping_class' => array(
				'label'       => __( 'Progressive: Per Class', 'wcsdm' ),
				'description' => __( 'Accumulate total by grouping the product shipping class.', 'wcsdm' ),
			),
			'progressive__per_product'        => array(
				'label'       => __( 'Progressive: Per Product', 'wcsdm' ),
				'description' => __( 'Accumulate total by grouping the product ID.', 'wcsdm' ),
			),
			'progressive__per_piece'          => array(
				'label'       => __( 'Progressive: Per Piece', 'wcsdm' ),
				'description' => __( 'Accumulate total by multiplying the quantity.', 'wcsdm' ),
			),
		);

		$total_cost_type_options = array();

		$total_cost_type_descriptions = array(
			'flat__highest'                   => __( 'Set highest item cost as total.', 'wcsdm' ),
			'flat__average'                   => __( 'Set average item cost as total.', 'wcsdm' ),
			'flat__lowest'                    => __( 'Set lowest item cost as total.', 'wcsdm' ),
			'progressive__per_shipping_class' => __( 'Accumulate total by grouping the product shipping class.', 'wcsdm' ),
			'progressive__per_product'        => __( 'Accumulate total by grouping the product ID.', 'wcsdm' ),
			'progressive__per_piece'          => __( 'Accumulate total by multiplying the quantity.', 'wcsdm' ),
		);

		$total_cost_type_descriptions = array();

		foreach ( $total_cost_type_mapping as $key => $item ) {
			$total_cost_type_options[ $key ] = $item['label'];
			$total_cost_type_descriptions[]  = '- <strong>' . $item['label'] . '</strong> - ' . $item['description'];
		}

		$fields = array(
			'total_cost_type' => array(
				// Field settings.
				'type'          => 'select',
				'title'         => __( 'Total Cost Type', 'wcsdm' ),
				'description'   => implode( '<br />', $total_cost_type_descriptions ),
				'default'       => 'flat__highest',
				'options'       => $total_cost_type_options,

				// Rate field settings.
				'is_persistent' => true,
				'rate_field'    => array(
					'dummy'    => array(
						'description' => __( 'Determine how is the total shipping cost will be calculated.', 'wcsdm' ),
					),
					'hidden'   => array(
						'default' => 'inherit',
						'options' => array_merge( array( 'inherit' => __( 'Inherit', 'wcsdm' ) ), $total_cost_type_options ),
					),
					'advanced' => array(
						'default'     => 'inherit',
						'options'     => array_merge( array( 'inherit' => __( 'Inherit', 'wcsdm' ) ), $total_cost_type_options ),
						'description' => '',
					),
				),
			),
			'min_cost'        => array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Minimum Cost', 'wcsdm' ),
				'default'           => '',
				'description'       => __( 'Minimum cost that will be applied. The calculated shipping cost will never be lower than whatever amount set into this field. Set as zero value to disable.', 'wcsdm' ),
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'rate_field'        => array(
					'hidden'   => true,
					'advanced' => array(
						'description' => __( 'Leave empty to inherit the global minimum cost setting.', 'wcsdm' ),
						'placeholder' => __( 'Inherit', 'wcsdm' ),
					),
				),

			),
			'max_cost'        => array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Maximum Cost', 'wcsdm' ),
				'description'       => __( 'Maximum cost that will be applied. The calculated shipping cost will never be greater than whatever amount set into this field. Set as zero value to disable.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'rate_field'        => array(
					'hidden'   => true,
					'advanced' => array(
						'description' => __( 'Leave empty to inherit the global minimum cost setting.', 'wcsdm' ),
						'placeholder' => __( 'Inherit', 'wcsdm' ),
					),
				),
			),
			'surcharge_type'  => array(
				// Field settings.
				'type'        => 'select',
				'title'       => __( 'Surcharge Type', 'wcsdm' ),
				'description' => __( 'Surcharge type that will be added to the total shipping cost.', 'wcsdm' ),
				'default'     => 'none',
				'options'     => array(
					'none'       => __( 'None', 'wcsdm' ),
					'fixed'      => __( 'Fixed', 'wcsdm' ),
					'percentage' => __( 'Percentage', 'wcsdm' ),
				),

				// Rate field settings.
				'rate_field'  => array(
					'hidden'   => array(
						'default' => 'inherit',
						'options' => array(
							'inherit'    => __( 'Inherit', 'wcsdm' ),
							'none'       => __( 'None', 'wcsdm' ),
							'fixed'      => __( 'Fixed', 'wcsdm' ),
							'percentage' => __( 'Percentage', 'wcsdm' ),
						),
					),
					'advanced' => array(
						'default'     => 'inherit',
						'options'     => array(
							'inherit'    => __( 'Inherit', 'wcsdm' ),
							'none'       => __( 'None', 'wcsdm' ),
							'fixed'      => __( 'Fixed', 'wcsdm' ),
							'percentage' => __( 'Percentage', 'wcsdm' ),
						),
						'description' => '',
					),
				),
			),
			'surcharge'       => array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Surcharge Amount', 'wcsdm' ),
				'description'       => __( 'Surcharge amount that will be added to the total shipping cost.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'rate_field'        => array(
					'hidden'   => true,
					'advanced' => array(
						'description' => __( 'Leave empty to inherit the global surcharge amount.', 'wcsdm' ),
						'default'     => '',
						'placeholder' => __( 'Inherit', 'wcsdm' ),
					),
				),
			),
			'discount_type'   => array(
				// Field settings.
				'type'        => 'select',
				'title'       => __( 'Discount Type', 'wcsdm' ),
				'default'     => 'none',
				'description' => __( 'Discount type that will be deducted to the total shipping cost.', 'wcsdm' ),
				'options'     => array(
					'none'       => __( 'None', 'wcsdm' ),
					'fixed'      => __( 'Fixed', 'wcsdm' ),
					'percentage' => __( 'Percentage', 'wcsdm' ),
				),

				// Rate field settings.
				'rate_field'  => array(
					'hidden'   => array(
						'default' => 'inherit',
						'options' => array(
							'inherit'    => __( 'Inherit', 'wcsdm' ),
							'none'       => __( 'None', 'wcsdm' ),
							'fixed'      => __( 'Fixed', 'wcsdm' ),
							'percentage' => __( 'Percentage', 'wcsdm' ),
						),

					),
					'advanced' => array(
						'default'     => 'inherit',
						'options'     => array(
							'inherit'    => __( 'Inherit', 'wcsdm' ),
							'none'       => __( 'None', 'wcsdm' ),
							'fixed'      => __( 'Fixed', 'wcsdm' ),
							'percentage' => __( 'Percentage', 'wcsdm' ),
						),
						'description' => '',
					),
				),
			),
			'discount'        => array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Discount Amount', 'wcsdm' ),
				'description'       => __( 'Discount amount that will be deducted to the total shipping cost.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'rate_field'        => array(
					'hidden'   => true,
					'advanced' => array(
						'description' => __( 'Leave empty to inherit the global discount amount setting.', 'wcsdm' ),
						'placeholder' => __( 'Inherit', 'wcsdm' ),
					),
				),
			),
		);

		foreach ( $fields as $key => $field ) {
			$this->fields_group_total_cost->add_field( $field, $key );
		}

		return $this->fields_group_total_cost;
	}

	/**
	 * Get or create the table rates settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group containing the table rates configuration field.
	 * The table rates field displays an interactive table interface for managing multiple
	 * shipping rate rules based on distance and other conditions.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The table rates settings fields group instance.
	 */
	public function get_fields_group_table_rates():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_table_rates ) ) {
			return $this->fields_group_table_rates;
		}

		$this->fields_group_table_rates = new Wcsdm_Fields_Group(
			'fields_group_table_rates',
			array(
				'type'        => 'tab_title',
				'title'       => __( 'Table Rates Settings', 'wcsdm' ),
				'tab_title'   => __( 'Table Rates', 'wcsdm' ),
				'description' => __( 'Calculates shipping costs based on the distance to the shipping address and any configured advanced rules. During checkout, the applicable rate is selected from the first row that matches both the maximum-distance condition and any advanced criteria. You can manually adjust the row order using the Move Up and Move Down buttons, which become available for rows that share the same maximum-distance value.', 'wcsdm' ),
			)
		);

		$fields = array(
			'table_rates' => array(
				'type'  => 'table_rates',
				'title' => __( 'Table Rates Settings', 'wcsdm' ),

			),
		);

		foreach ( $fields as $key => $field ) {
			$this->fields_group_table_rates->add_field( $field, $key );
		}

		return $this->fields_group_table_rates;
	}

	/**
	 * Get or create the shipping rules settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group for configuring shipping rate rules:
	 * - Maximum distance threshold
	 * - Minimum order amount requirement
	 * - Maximum order amount limit
	 * - Minimum order quantity
	 * - Maximum order quantity
	 *
	 * These fields define the conditions and pricing for individual shipping rates.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The shipping rules settings fields group instance.
	 */
	public function get_fields_group_shipping_rules():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_shipping_rules ) ) {
			return $this->fields_group_shipping_rules;
		}

		$this->fields_group_shipping_rules = new Wcsdm_Fields_Group(
			'fields_group_shipping_rules',
			array(
				'type'       => 'title',
				'title'      => __( 'Shipping Rules', 'wcsdm' ),

				// Rate field settings.
				'rate_field' => array(
					'advanced' => true,
				),
			)
		);

		$fields = array(
			'max_distance'       => array(
				// Field settings.
				'type'              => 'number',
				'title'             => __( 'Max Distance', 'wcsdm' ),
				'description'       => __( 'The maximum distances rule for the shipping rate. This input is required.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),

				// Rate field settings.
				'is_persistent'     => true,
				'is_required'       => true,
				'is_rule'           => true,
				'rule_callback'     => function( array $rate_row, array $package, Wcsdm_Distance $distance ):bool {
					$distance_in_unit = $distance->in_unit( $this->get_option( 'distance_unit', 'km' ) );
					$max_distance     = $rate_row['max_distance'] ?? 0;

					return $distance_in_unit <= $max_distance;
				},
				'rate_field'        => array(
					'hidden'   => true,
					'dummy'    => true,
					'advanced' => true,
				),
			),
			'min_order_amount'   => array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Min Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order amount. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'is_rule'           => true,
				'rule_callback'     => function( array $rate_row, array $package ):bool {
					$min_order_amount = $rate_row['min_order_amount'] ?? 0;

					if ( $min_order_amount ) {
						return $min_order_amount <= $package['cart_subtotal'];
					}

					return true;
				},
				'rate_field'        => array(
					'hidden'   => true,
					'dummy'    => true,
					'advanced' => true,
				),
			),
			'max_order_amount'   => array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Max Order Amount', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order amount. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'is_rule'           => true,
				'rule_callback'     => function( array $rate_row, array $package ):bool {
					$max_order_amount = $rate_row['max_order_amount'] ?? 0;

					if ( $max_order_amount ) {
						return $max_order_amount >= $package['cart_subtotal'];
					}

					return true;
				},
				'rate_field'        => array(
					'hidden'   => true,
					'dummy'    => true,
					'advanced' => true,
				),
			),
			'min_order_quantity' => array(
				// Field settings.
				'type'              => 'number',
				'title'             => __( 'Min Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for minimum order quantity. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),

				// Rate field settings.
				'is_rule'           => true,
				'rule_callback'     => function( array $rate_row, array $package ):bool {
					$min_order_quantity = $rate_row['min_order_quantity'] ?? 0;

					if ( $min_order_quantity ) {
						return $min_order_quantity <= count( $package['contents'] );
					}

					return true;
				},
				'rate_field'        => array(
					'hidden'   => true,
					'advanced' => true,
				),
			),
			'max_order_quantity' => array(
				// Field settings.
				'type'              => 'number',
				'title'             => __( 'Max Order Quantity', 'wcsdm' ),
				'description'       => __( 'The shipping rule for maximum order quantity. Leave blank or fill with zero value to disable this rule.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),

				// Rate field settings.
				'is_rule'           => true,
				'rule_callback'     => function( array $rate_row, array $package ):bool {
					$max_order_quantity = $rate_row['max_order_quantity'] ?? 0;

					if ( $max_order_quantity ) {
						return $max_order_quantity >= count( $package['contents'] );
					}

					return true;
				},
				'rate_field'        => array(
					'hidden'   => true,
					'advanced' => true,
				),
			),
		);

		foreach ( $fields as $key => $field ) {
			$this->fields_group_shipping_rules->add_field( $field, $key );
		}

		return $this->fields_group_shipping_rules;
	}

	/**
	 * Get or create the rates per distance unit settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group containing the base rate field.
	 * This field defines the cost per distance unit (kilometer or mile) for shipping
	 * calculations. The base rate applies to all products unless overridden by
	 * shipping class-specific rates.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The rates per distance unit settings fields group instance.
	 */
	public function get_fields_group_rates():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_rates ) ) {
			return $this->fields_group_rates;
		}

		$this->fields_group_rates = new Wcsdm_Fields_Group(
			'fields_group_rates',
			array(
				// Field settings.
				'type'       => 'title',
				'title'      => __( 'Shipping Rates', 'wcsdm' ),

				// Rate field settings.
				'rate_field' => array(
					'advanced' => true,
				),
			)
		);

		$this->fields_group_rates->add_field(
			array(
				// Field settings.
				'type'              => 'price',
				'title'             => __( 'Distance Rate', 'wcsdm' ),
				'description'       => __( 'The rate charged per kilometer/mile traveled. Set to 0 to set as free shipping.', 'wcsdm' ),
				'default'           => '',
				'custom_attributes' => array(
					'min' => '0',
				),

				// Rate field settings.
				'is_persistent'     => true,
				'is_required'       => true,
				'is_rate'           => true,
				'rate_field'        => array(
					'hidden'   => true,
					'dummy'    => true,
					'advanced' => true,
				),
			),
			'rate_class_0'
		);

		return $this->fields_group_rates;
	}

	/**
	 * Get or create the rates per shipping class settings fields group.
	 *
	 * Creates and returns a Wcsdm_Fields_Group containing per-shipping-class rate fields.
	 * Dynamically generates fields based on WooCommerce shipping classes configured
	 * in the system. Each shipping class gets its own rate field that overrides the
	 * base rate when specified.
	 *
	 * If no shipping classes are defined in WooCommerce, only the group header is created
	 * without any rate fields.
	 *
	 * The fields group is cached after first creation for performance.
	 *
	 * @since 3.0
	 *
	 * @return Wcsdm_Fields_Group The rates per shipping class settings fields group instance.
	 */
	public function get_fields_group_per_shipping_class_rates():Wcsdm_Fields_Group {
		if ( ! empty( $this->fields_group_per_shipping_class_rates ) ) {
			return $this->fields_group_per_shipping_class_rates;
		}

		$this->fields_group_per_shipping_class_rates = new Wcsdm_Fields_Group(
			'fields_group_per_shipping_class_rates',
			array(
				// Field settings.
				'type'        => 'title',
				'title'       => __( 'Per Shipping Class Rates', 'wcsdm' ),
				'description' => __( 'Set rate charged per kilometer/mile traveled for selected shipping classes.', 'wcsdm' ),

				// Rate field settings.
				'rate_field'  => array(
					'advanced' => true,
				),
			)
		);

		$shipping_classes = WC()->shipping->get_shipping_classes();

		if ( $shipping_classes ) {
			foreach ( $shipping_classes as $shipping_class ) {
				$this->fields_group_per_shipping_class_rates->add_field(
					array(
						// Field settings.
						'type'              => 'price',
						// translators: %s is the shipping class name.
						'title'             => sprintf( __( 'Shipping Class: %s', 'wcsdm' ), $shipping_class->name ),
						'description'       => __( 'Set to 0 to set as free shipping. Leave blank to disable the class-specific override.', 'wcsdm' ),
						'default'           => '',
						'custom_attributes' => array(
							'min' => '0',
						),

						// Rate field settings.
						'is_required'       => false,
						'is_rate'           => true,
						'rate_field'        => array(
							'hidden'   => true,
							'advanced' => true,
						),
					),
					'rate_class_' . $shipping_class->term_id
				);
			}
		}

		return $this->fields_group_per_shipping_class_rates;
	}

	/**
	 * Get the display value for a rate row field in the table.
	 *
	 * Retrieves and formats the value for a specific field in the rate table display.
	 * Handles different field types (select, price, decimal) and applies appropriate formatting.
	 * Falls back to the field's default value if no value is set in the rate row.
	 *
	 * @since 3.0
	 *
	 * @param string $key  The field key.
	 * @param array  $data The field configuration data.
	 * @param array  $rate Optional. The rate row data. Default empty array.
	 *
	 * @return string The formatted display value for the field.
	 */
	private function get_rate_row_dummy_value( $key, $data, $rate = array() ):string {
		$data = wp_parse_args(
			$this->populate_field( $key, $data ),
			array(
				'type'    => 'text',
				'default' => '',
				'options' => array(),
			)
		);

		$value = $this->get_rate_row_value( $key, $rate, $data['default'] );

		switch ( $data['type'] ) {
			case 'select':
				if ( isset( $data['options'][ $value ] ) ) {
					$value = $data['options'][ $value ];
				}
				break;

			case 'price':
				$value = wc_format_localized_price( $value );
				break;

			case 'decimal':
				$value = wc_format_localized_decimal( $value );
				break;

			default:
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				break;
		}

		return $value;
	}

	/**
	 * Get a value from a rate row with fallback handling.
	 *
	 * Retrieves a field value from a rate row with a multi-level fallback strategy:
	 * 1. Use the value from the rate row if present
	 * 2. Fall back to the field's default value from rate fields configuration
	 * 3. Fall back to the field's default value from form fields configuration
	 * 4. Fall back to the provided empty_value parameter
	 *
	 * This method ensures consistent value retrieval even when fields are not explicitly
	 * set in the rate row data.
	 *
	 * @since 3.0
	 *
	 * @param string $key         The field key to retrieve.
	 * @param array  $rate_row    The rate row data array.
	 * @param mixed  $empty_value Optional. Value to return if field is empty or not found. Default null.
	 *
	 * @return mixed The field value with fallback handling applied.
	 */
	private function get_rate_row_value( string $key, array $rate_row, $empty_value = null ) {
		$field = null;

		$rate_fields = $this->get_rates_fields( 'hidden' );

		if ( isset( $rate_fields[ $key ] ) ) {
			$field = $rate_fields[ $key ];
		}

		if ( ! $field ) {
			$form_fields = $this->get_instance_form_fields();

			if ( isset( $form_fields[ $key ] ) ) {
				$field = $form_fields[ $key ];
			}
		}

		if ( ! $field ) {
			$field = array(
				'type'    => 'text',
				'default' => '',
			);
		}

		$value = $rate_row[ $key ] ?? $field['default'] ?? '';

		if ( ! is_null( $empty_value ) && '' === $value ) {
			$value = $empty_value;
		}

		return $value;
	}

	/**
	 * Check if a rate row matches all shipping rules for the given package and distance.
	 *
	 * Validates whether a rate row should apply to a shipping calculation by checking
	 * all configured rules (distance, order amount, order quantity, etc.). Each rule
	 * field with a rule_callback is evaluated, and all must return true for the rate
	 * row to be considered a match.
	 *
	 * Rule callbacks are cached in $this->rate_rules_fields on first execution for
	 * performance optimization across multiple rate row checks.
	 *
	 * @since 3.0
	 *
	 * @param array          $rate_row The rate row configuration to validate.
	 * @param array          $package  The WooCommerce shipping package being calculated.
	 * @param Wcsdm_Distance $distance The calculated distance object.
	 *
	 * @return bool True if all rules match, false if any rule fails or no rules are defined.
	 */
	private function is_rate_row_match_rules( array $rate_row, array $package, Wcsdm_Distance $distance ):bool {
		if ( ! $this->rate_rules_fields ) {
			$this->rate_rules_fields = array();

			$rate_fields = $this->get_rates_fields( 'hidden' );

			foreach ( $rate_fields as $field_key => $field ) {
				$is_rule = ! ! ( $field['is_rule'] ?? false );

				if ( ! $is_rule ) {
					continue;
				}

				$rule_callback = $field['rule_callback'] ?? null;

				if ( ! $rule_callback || ! is_callable( $rule_callback ) ) {
					continue;
				}

				$this->rate_rules_fields[ $field_key ] = $field['rule_callback'];
			}
		}

		if ( empty( $this->rate_rules_fields ) ) {
			return false;
		}

		foreach ( $this->rate_rules_fields as $field_key => $rule_callback ) {
			if ( ! call_user_func( $rule_callback, $rate_row, $package, $distance ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find the first rate row that matches all rules for the given distance and package.
	 *
	 * Iterates through all configured table rates and returns the first row that matches
	 * all shipping rules. The matching logic checks conditions like maximum distance,
	 * order amount ranges, and order quantity limits via is_rate_row_match_rules().
	 *
	 * Provides filter hooks for developers to bypass or modify the matching logic:
	 * - 'wcsdm_rate_row_match_rules_pre': Pre-filter to provide custom rate row before matching
	 * - 'wcsdm_rate_row_match_rules': Post-filter to modify or replace the matched rate row
	 *
	 * @since 3.0
	 *
	 * @param Wcsdm_Distance $distance The calculated shipping distance.
	 * @param array          $package  The WooCommerce shipping package being calculated.
	 *
	 * @return array|null The matched rate row configuration array, or null if no match found.
	 */
	private function get_rate_row_match_rules( Wcsdm_Distance $distance, array $package ):?array {
		$pre = apply_filters( 'wcsdm_rate_row_match_rules_pre', null, $distance, $package, $this );

		if ( is_array( $pre ) ) {
			return $pre;
		}

		$rate_row_match = null;
		$table_rates    = $this->get_option( 'table_rates', array() );

		if ( $table_rates ) {
			foreach ( $table_rates as $table_rate_row ) {
				if ( $this->is_rate_row_match_rules( $table_rate_row, $package, $distance ) ) {
					$rate_row_match = $table_rate_row;
					break;
				}
			}
		}

		return apply_filters( 'wcsdm_rate_row_match_rules', $rate_row_match, $distance, $package, $this );
	}

	/**
	 * Calculate the shipping cost for a matched rate row.
	 *
	 * Computes the total shipping cost based on the matched rate row configuration, distance,
	 * and package contents. The calculation process includes:
	 * 1. Calculate per-item costs based on shipping class rates and distance
	 * 2. Aggregate costs using the configured total_cost_type (flat or progressive)
	 * 3. Apply surcharges (fixed or percentage)
	 * 4. Apply discounts (fixed or percentage)
	 * 5. Enforce minimum and maximum cost limits
	 *
	 * Supports multiple cost aggregation methods:
	 * - Flat: Uses highest, lowest, or average item cost
	 * - Progressive: Accumulates costs per shipping class, per product, or per piece
	 *
	 * Inherits global settings when rate-specific values are not defined.
	 *
	 * @since 3.0
	 *
	 * @param array          $rate_row_match The matched rate row configuration.
	 * @param Wcsdm_Distance $distance       The calculated shipping distance.
	 * @param array          $package        The WooCommerce shipping package.
	 *
	 * @return string The calculated shipping cost after all adjustments and limits applied.
	 */
	private function get_rate_cost_by_rate_row( array $rate_row_match, Wcsdm_Distance $distance, array $package ):string {
		// Hold costs data for flat total_cost_type.
		$flat = array();

		// Hold costs data for progressive total_cost_type.
		$progressive = array();

		$distance_unit = $this->get_option( 'distance_unit', 'km' );

		foreach ( $package['contents'] as $item ) {
			if ( ! $item['data']->needs_shipping() ) {
				continue;
			}

			$class_id   = $item['data']->get_shipping_class_id();
			$product_id = $item['data']->get_id();

			$item_cost = $this->get_rate_row_value( 'rate_class_0', $rate_row_match, 0 );

			if ( $class_id ) {
				$class_cost = $this->get_rate_row_value( 'rate_class_' . $class_id, $rate_row_match );

				if ( strlen( $class_cost ) ) {
					$item_cost = $class_cost;
				}
			}

			// Multiply shipping cost with distance unit.
			$item_cost *= $distance->in_unit( $distance_unit );

			// Add cost data for flat total_cost_type.
			$flat[] = $item_cost;

			// Add cost data for progressive total_cost_type.
			$progressive[] = array(
				'item_cost'  => $item_cost,
				'class_id'   => $class_id,
				'product_id' => $product_id,
				'quantity'   => $item['quantity'],
			);
		}

		$cost = 0;

		$total_cost_type = $this->get_rate_row_value( 'total_cost_type', $rate_row_match, 'inherit' );

		if ( 'inherit' === $total_cost_type ) {
			$total_cost_type = $this->get_option( 'total_cost_type', 'fixed' );
		}

		if ( strpos( $total_cost_type, 'flat__' ) === 0 ) {
			switch ( $total_cost_type ) {
				case 'flat__lowest':
					$cost = min( $flat );
					break;

				case 'flat__average':
					$cost = ( array_sum( $flat ) / count( $flat ) );
					break;

				default:
					$cost = max( $flat );
					break;
			}
		} elseif ( strpos( $total_cost_type, 'progressive__' ) === 0 ) {
			switch ( $total_cost_type ) {
				case 'progressive__per_shipping_class':
					$costs = array();

					foreach ( $progressive as $value ) {
						$costs[ $value['class_id'] ] = $value['item_cost'];
					}

					$cost = array_sum( $costs );
					break;

				case 'progressive__per_product':
					$costs = array();

					foreach ( $progressive as $value ) {
						$costs[ $value['product_id'] ] = $value['item_cost'];
					}

					$cost = array_sum( $costs );
					break;

				default:
					$costs = array();

					foreach ( $progressive as $value ) {
						$costs[ $value['product_id'] ] = $value['item_cost'] * $value['quantity'];
					}

					$cost = array_sum( $costs );
					break;
			}
		}

		$surcharge = $this->get_rate_row_value( 'surcharge', $rate_row_match, '' );

		if ( ! strlen( $surcharge ) ) {
			$surcharge = $this->get_option( 'surcharge', '' );
		}

		if ( $surcharge ) {
			$surcharge_type = $this->get_rate_row_value( 'surcharge_type', $rate_row_match, 'inherit' );

			if ( ! $surcharge_type || 'inherit' === $surcharge_type ) {
				$surcharge_type = $this->get_option( 'surcharge_type', 'fixed' );
			}

			if ( 'fixed' === $surcharge_type ) {
				$cost += $surcharge;
			} elseif ( 'percent' === $surcharge_type ) {
				$cost += ( ( $cost * $surcharge ) / 100 );
			}
		}

		$discount = $this->get_rate_row_value( 'discount', $rate_row_match, $this->get_option( 'discount' ) );

		if ( $discount ) {
			$discount_type = $this->get_rate_row_value( 'discount_type', $rate_row_match, 'inherit' );

			if ( ! $discount_type || 'inherit' === $discount_type ) {
				$discount_type = $this->get_option( 'discount_type', 'fixed' );
			}

			if ( ! $discount_type ) {
				$discount_type = 'fixed';
			}

			if ( 'fixed' === $discount_type ) {
				$cost -= $discount;
			} elseif ( 'percent' === $discount_type ) {
				$cost -= ( ( $cost * $discount ) / 100 );
			}
		}

		$min_cost = $this->get_rate_row_value( 'min_cost', $rate_row_match, '' );

		if ( ! strlen( $min_cost ) ) {
			$min_cost = $this->get_option( 'min_cost', '' );
		}

		if ( $min_cost && $min_cost > $cost ) {
			$cost = $min_cost;
		}

		$max_cost = $this->get_rate_row_value( 'max_cost', $rate_row_match, '' );

		if ( ! strlen( $max_cost ) ) {
			$max_cost = $this->get_option( 'max_cost', '' );
		}

		if ( $max_cost && $max_cost < $cost ) {
			$cost = $max_cost;
		}

		return wc_format_decimal( $cost, '' );
	}

	/**
	 * Get the shipping rate label to display to the customer.
	 *
	 * Constructs the shipping method label shown during checkout. Uses the rate-specific
	 * title if defined, otherwise falls back to the instance title or method title.
	 * Optionally appends the calculated distance if the 'show_distance' option is enabled.
	 *
	 * @since 3.0
	 *
	 * @param array          $rate_row_match The matched rate row configuration.
	 * @param Wcsdm_Distance $distance       The calculated shipping distance.
	 *
	 * @return string The formatted shipping rate label, optionally including distance information.
	 */
	private function get_rate_label_by_rate_row( array $rate_row_match, Wcsdm_Distance $distance ):string {
		$label = $this->get_rate_row_value( 'title', $rate_row_match, '' );

		if ( ! strlen( $label ) ) {
			$label = $this->get_option( 'title', $this->get_method_title() );
		}

		if ( 'yes' === $this->get_option( 'show_distance', 'no' ) ) {
			$distance_unit = $this->get_option( 'distance_unit', 'km' );

			return sprintf(
				'%s (%s: %s %s)',
				$label,
				__( 'Distance', 'wcsdm' ),
				wc_format_localized_decimal( $distance->in_unit( $distance_unit ) ),
				$distance_unit
			);
		}

		return $label;
	}

	/**
	 * Check if logging is enabled.
	 *
	 * Checks the shipping method settings to determine if logging is enabled.
	 * The result can be filtered via 'wcsdm_log_enabled'.
	 *
	 * @since 3.0
	 *
	 * @return bool True if logging is enabled, false otherwise.
	 */
	public function is_log_enabled():bool {
		/**
		 * Filters whether to write log entries for this shipping method instance.
		 *
		 * This filter allows developers to conditionally enable or disable logging
		 * based on custom criteria, overriding the shipping method's enable_log setting.
		 *
		 * @since 3.0
		 *
		 * @param bool                  $log_enabled Whether to write the log entry. Default is based on enable_log option.
		 * @param WCSDM_Shipping_Method $instance    The shipping method instance.
		 */
		$log_enabled = apply_filters(
			'wcsdm_log_enabled',
			'yes' === $this->get_option( 'enable_log' ),
			$this
		);

		return $log_enabled;
	}

	/**
	 * Conditionally write a log message using WooCommerce's logging system.
	 *
	 * This method provides intelligent logging that respects both global and instance-level
	 * debug settings. It only writes log entries when either WooCommerce shipping debug mode
	 * is enabled globally OR when logging is specifically enabled for this shipping method
	 * instance via the 'enable_log' instance option. This dual-level approach prevents
	 * excessive logging in production environments while allowing targeted debugging when needed.
	 *
	 * The method integrates with WooCommerce's built-in PSR-3 compatible logging system,
	 * which provides standardized log levels (emergency, alert, critical, error, warning,
	 * notice, info, debug) and supports context-based message interpolation.
	 *
	 * Log files are stored in the WooCommerce logs directory (typically wp-content/uploads/wc-logs/)
	 * and can be viewed and managed through the WooCommerce admin panel under
	 * WooCommerce > Status > Logs. Each log entry includes a timestamp and can include
	 * additional context data for debugging purposes.
	 *
	 * Usage example:
	 * ```php
	 * $this->maybe_write_log( 'info', 'Distance calculated: {distance} km', array( 'distance' => 15.5 ) );
	 * $this->maybe_write_log( 'error', 'API request failed: {error}', array( 'error' => $error_message ) );
	 * ```
	 *
	 * @since 3.0
	 * @param  string      $level   The PSR-3 log level. Accepted values: 'emergency', 'alert',
	 *                              'critical', 'error', 'warning', 'notice', 'info', or 'debug'.
	 * @param  string      $message The message to log. Can include placeholders in {braces} that
	 *                              will be replaced with corresponding values from $data array.
	 * @param  array       $data    Optional. Associative array of additional context data to include with the log entry.
	 * @param  string|null $group Optional. Log group name for categorizing log entries. Default null.
	 * @return void
	 */
	public function maybe_write_log( string $level, string $message, array $data = array(), ?string $group = null ):void {
		if ( ! $this->is_log_enabled() ) {
			return;
		}

		$source = $this->id . '-' . $this->get_instance_id();

		if ( $group ) {
			$source = $group . '-' . $source;
		}

		$context = array(
			'source' => $source,
			'data'   => $data,
		);

		switch ( $level ) {
			case 'error':
				// Write the log entry using WooCommerce's logger.
				wc_get_logger()->error(
					$message,
					$context
				);
				break;

			case 'info':
				// Write the log entry using WooCommerce's logger.
				wc_get_logger()->info(
					$message,
					$context
				);
				break;

			default:
				// Write the log entry using WooCommerce's logger.
				wc_get_logger()->log(
					$level,
					$message,
					$context
				);
				break;
		}
	}
}
