<?php
/**
 * The field group class file.
 *
 * This file contains the Wcsdm_Fields_Group class which represents a group of
 * settings fields in the WooReer plugin. It handles the configuration and
 * collection of fields that belong to a specific group.
 *
 * @package    Wcsdm
 * @subpackage Classes
 * @since      3.0
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class representing a group of settings fields.
 *
 * This class encapsulates the data and behavior for a group of settings fields.
 * It manages the group's configuration (slug, title, description) and the
 * collection of fields that belong to it.
 *
 * @since 3.0
 */
class Wcsdm_Fields_Group {
	/**
	 * The unique identifier for the field group.
	 *
	 * This slug is used as the key for the field group in the settings array
	 * and must be unique across all field groups.
	 *
	 * @since 3.0
	 * @var string
	 */
	private $slug;

	/**
	 * The field group data/configuration.
	 *
	 * Contains the configuration data for the field group including type,
	 * title, description, and other attributes used for rendering the group
	 * in the WooReer settings interface.
	 *
	 * @since 3.0
	 * @var array
	 */
	private $data;

	/**
	 * Collection of fields within this group.
	 *
	 * An associative array of Wcsdm_Setting_Field objects indexed by their
	 * field keys. These fields will be rendered as part of this group in the
	 * WooCommerce shipping method settings.
	 *
	 * @since 3.0
	 * @var array<string, Wcsdm_Setting_Field>
	 */
	private $fields = array();

	/**
	 * Initialize the field group.
	 *
	 * Creates a new field group instance with the provided slug and configuration data.
	 * The slug must be unique and the data array should contain the field group configuration
	 * such as type, title, description, etc.
	 *
	 * @since 3.0
	 * @param string $slug The unique identifier for the field group.
	 * @param array  $data The field group configuration data.
	 */
	public function __construct( string $slug, array $data ) {
		$this->slug = $slug;
		$this->data = $data;
	}

	/**
	 * Get the field group slug.
	 *
	 * Returns the unique identifier for this field group.
	 *
	 * @since 3.0
	 * @return string The unique identifier for the field group.
	 */
	public function get_slug():string {
		return $this->slug;
	}

	/**
	 * Get the field group data.
	 *
	 * Returns the complete configuration data array for this field group.
	 *
	 * @since 3.0
	 * @return array The field group configuration data.
	 */
	public function get_data():array {
		return $this->data;
	}

	/**
	 * Get a specific item from the field group data.
	 *
	 * Retrieves a specific configuration value from the field group data array.
	 * If the key doesn't exist, returns the provided default value.
	 *
	 * @since 3.0
	 * @param string $key     The key of the data item to retrieve.
	 * @param mixed  $default The default value to return if the key doesn't exist. Default null.
	 * @return mixed The value of the data item or the default value if not found.
	 */
	public function get_data_item( string $key, $default = null ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : $default;
	}

	/**
	 * Add a field to the group.
	 *
	 * Adds a new field to this field group. The field is wrapped in a Wcsdm_Setting_Field
	 * object and associated with this group. Field keys must be unique and cannot match
	 * the group slug. Title and table_title field types are not allowed as they should
	 * be declared as groups instead.
	 *
	 * @since 3.0
	 * @param array  $field The field configuration data including type, title, description, etc.
	 * @param string $key   The unique key for the field.
	 * @throws Exception When the field key conflicts with the group slug.
	 * @throws Exception When attempting to add a title or table_title field type directly.
	 */
	public function add_field( array $field, string $key ) {
		// Validate that the field key doesn't conflict with the group slug.
		if ( $key === $this->slug ) {
			throw new Exception(
				sprintf(
					'Field key "%s" conflicts with the group slug. Field keys must be unique and different from the group slug.',
					$key
				)
			);
		}

		$field_type = $field['type'] ?? '';

		// Validate that title field types are not added as fields but as groups.
		if ( 'title' === $field_type || 'table_title' === $field_type ) {
			throw new Exception(
				sprintf(
					'Field type "%s" should be declared as group.',
					$field_type
				)
			);
		}

		// Create the field object and add it to the fields collection.
		$this->fields[ $key ] = new Wcsdm_Setting_Field(
			array_merge(
				$field,
				array(
					'group' => $this->slug,
				)
			),
			$key
		);
	}

	/**
	 * Get all fields in the group including the group field itself.
	 *
	 * Returns an array of all Wcsdm_Setting_Field objects in this group. The array
	 * includes the group field itself (using the group slug as key) followed by all
	 * the child fields. If the group has no child fields, an empty array is returned.
	 *
	 * @since 3.0
	 * @return array<string, Wcsdm_Setting_Field> Associative array of field objects indexed by field key.
	 */
	public function get_fields():array {
		// Return empty array if there are no fields.
		if ( empty( $this->fields ) ) {
			return array();
		}

		// Merge the group field with the child fields.
		return array_merge(
			array(
				$this->slug => new Wcsdm_Setting_Field( $this->data, $this->slug ),
			),
			$this->fields
		);
	}
}
