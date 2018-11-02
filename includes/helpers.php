<?php
/**
 * Helpers file
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.5.0
 *
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

/**
 * Check if plugin is active
 *
 * @param string $plugin_file Plugin file name.
 */
function wcsdm_is_plugin_active( $plugin_file ) {
	$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}

	return in_array( $plugin_file, $active_plugins, true ) || array_key_exists( $plugin_file, $active_plugins );
}

/**
 * Check if pro version plugin is installed and activated
 *
 * @since    1.5.0
 * @return bool
 */
function wcsdm_is_pro() {
	return wcsdm_is_plugin_active( 'woocommercex/woocommercex.php' );
}

/**
 * Get i18n strings
 *
 * @param string $key Strings key.
 * @param string $default Default value.
 * @return mixed
 */
function wcsdm_i18n( $key = '', $default = '' ) {
	$i18n = array(
		'drag_marker'         => __( 'Drag this marker or search your address at the input above.', 'wcsdm' ),
		'per_unit'            => __( 'Per %s', 'wcsdm' ),
		'map_is_error'        => __( 'Map is error', 'wcsdm' ),
		'latitude'            => __( 'Latitude', 'wcsdm' ),
		'longitude'           => __( 'Longitude', 'wcsdm' ),
		'cancel'              => __( 'Cancel', 'wcsdm' ),
		'add_rate'            => __( 'Add Rate', 'wcsdm' ),
		'delete_rate'         => __( 'Delete Selected Rates', 'wcsdm' ),
		'delete_rate_confirm' => __( 'Confirm Delete', 'wcsdm' ),
		'save_changes'        => __( 'Save Changes', 'wcsdm' ),
		'apply_changes'       => __( 'Apply Changes', 'wcsdm' ),
		'add'                 => __( 'Add', 'wcsdm' ),
		'save'                => __( 'Save', 'wcsdm' ),
		'apply'               => __( 'Apply', 'wcsdm' ),
		'close'               => __( 'Close', 'wcsdm' ),
		'back'                => __( 'Back', 'wcsdm' ),
		'delete'              => __( 'Delete', 'wcsdm' ),
		'confirm'             => __( 'Confirm', 'wcsdm' ),
		'get_api_key'         => __( 'Get API Key', 'wcsdm' ),
		'errors'              => array(
			// translators: %s = Field name.
			'field_required'        => __( '%s field is required', 'wcsdm' ),
			// translators: %1$s = Field name, %2$d = Minimum field value rule.
			'field_min_value'       => __( '%1$s field value cannot be lower than %2$d', 'wcsdm' ),
			// translators: %1$s = Field name, %2$d = Maximum field value rule.
			'field_max_value'       => __( '%1$s field value cannot be greater than %2$d', 'wcsdm' ),
			// translators: %s = Field name.
			'field_numeric'         => __( '%s field value must be numeric', 'wcsdm' ),
			// translators: %s = Field name.
			'field_numeric_decimal' => __( '%s field value must be numeric and decimal', 'wcsdm' ),
			// translators: %s = Field name.
			'field_select'          => __( '%s field value selected is not exists', 'wcsdm' ),
			// translators: %1$d = row number, %2$s = error message.
			'duplicate_rate'        => __( 'Duplicate shipping rules for rate row %1$d: %2$s', 'wcsdm' ),
			'need_upgrade'          => array(
				// translators: %s = Field name.
				'general'          => __( '%s field value only changeable in pro version. Please upgrade!', 'wcsdm' ),
				'total_cost_type'  => __( 'Total cost type "Match Formula" options only available in pro version. Please upgrade!', 'wcsdm' ),
			),
		),
	);

	if ( ! empty( $key ) && is_string( $key ) ) {
		$keys = explode( '.', $key );

		$temp = $i18n;
		foreach ( $keys as $path ) {
			$temp = &$temp[ $path ];
		}

		return is_null( $temp ) ? $default : $temp;
	}

	return $i18n;
}
