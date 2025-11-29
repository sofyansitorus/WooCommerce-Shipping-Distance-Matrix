<?php
/**
 * The field group factory class file.
 *
 * This file contains the Wcsdm_Fields_Group_Factory class which provides
 * a centralized factory pattern implementation for creating and managing
 * field group instances throughout the WooReer plugin.
 *
 * @link       https://github.com/sofyansitorus/WooCommerce-Shipping-Distance-Matrix
 * @since      3.0
 * @package    Wcsdm
 * @subpackage Classes
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory class for managing field groups.
 *
 * This class implements the factory pattern to handle the creation, registration,
 * and retrieval of field group instances. It provides a centralized registry that
 * allows field groups to be stored, accessed, and filtered efficiently throughout
 * the WooReer plugin lifecycle.
 *
 * The factory ensures that only field groups with valid fields are stored and
 * retrieved, maintaining data integrity and preventing empty or invalid field
 * groups from being processed.
 *
 * @since 3.0
 */
class Wcsdm_Fields_Group_Factory {

	/**
	 * Collection of field group instances.
	 *
	 * Stores all registered field group instances indexed by their unique slug identifiers.
	 * This registry allows for efficient lookup and retrieval of field groups throughout
	 * the plugin's execution.
	 *
	 * @since 3.0
	 * @var array<string, Wcsdm_Fields_Group> Associative array of field group objects keyed by slug.
	 */
	private $items = array();

	/**
	 * Add a field group to the factory.
	 *
	 * Registers a new field group instance in the factory's internal registry.
	 * Only field groups that contain at least one field will be added. Empty
	 * field groups are silently ignored to maintain data integrity.
	 *
	 * The field group is stored using its unique slug as the array key, which
	 * allows for efficient retrieval and prevents duplicate registrations.
	 *
	 * @since 3.0
	 * @param Wcsdm_Fields_Group $fields_group The field group instance to register.
	 * @return void
	 */
	public function add( Wcsdm_Fields_Group $fields_group ) {
		// Skip registration if the field group has no fields.
		if ( ! $fields_group->get_fields() ) {
			return;
		}

		// Register the field group using its slug as the key.
		$this->items[ $fields_group->get_slug() ] = $fields_group;
	}

	/**
	 * Get field groups from the factory.
	 *
	 * Retrieves field group instances from the factory registry. Can return either
	 * all registered field groups or a filtered subset based on the provided slugs.
	 *
	 * When slugs are provided, only matching field groups will be returned. Field
	 * groups that don't exist or have no fields will be automatically excluded from
	 * the results to ensure data integrity.
	 *
	 * When no slugs are provided, all registered field groups with valid fields will
	 * be returned.
	 *
	 * @since 3.0
	 * @param array|null $slugs Optional. Array of field group slugs to retrieve.
	 *                          If provided, only these field groups will be returned.
	 *                          If null, all field groups will be returned. Default null.
	 * @return array<string, Wcsdm_Fields_Group> Associative array of field group instances
	 *                                           keyed by their slugs. Only groups with
	 *                                           fields are included.
	 */
	public function get_items( ?array $slugs = null ):array {
		// Filter by specific slugs if provided.
		if ( $slugs ) {
			$items = array();

			foreach ( $slugs as $slug ) {
				$item = $this->get_item( $slug );

				// Skip if item not found or has no fields.
				if ( ! $item || ! $item->get_fields() ) {
					continue;
				}

				// Add the valid field group to the results.
				$items[ $slug ] = $item;
			}

			return $items;
		}

		// Return all field groups with valid fields.
		$items = array();

		foreach ( $this->items as $slug => $fields_group ) {
			// Skip field groups with no fields.
			if ( ! $fields_group->get_fields() ) {
				continue;
			}

			$items[ $slug ] = $fields_group;
		}

		return $items;
	}

	/**
	 * Get a specific field group by its slug.
	 *
	 * Retrieves a single field group instance from the factory registry using its
	 * unique slug identifier. This method provides direct access to a specific field
	 * group without iterating through all registered groups.
	 *
	 * @since 3.0
	 * @param string $slug The unique slug identifier of the field group to retrieve.
	 * @return Wcsdm_Fields_Group|null The field group instance if found in the registry,
	 *                                 or null if no field group exists with the given slug.
	 */
	public function get_item( $slug ):?Wcsdm_Fields_Group {
		// Return the field group if it exists, or null if not found.
		return $this->items[ $slug ] ?? null;
	}
}
