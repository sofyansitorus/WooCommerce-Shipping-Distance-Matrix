<?php
/**
 * The file that defines the api request class
 *
 * @link       https://github.com/sofyansitorus
 * @since      2.0.8
 *
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The API request class.
 *
 * @since      2.0.8
 * @package    Wcsdm
 * @subpackage Wcsdm/includes
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
abstract class Wcsdm_API {

	/**
	 * URL of Google Maps Distance Matrix API
	 *
	 * @since    2.0.8
	 * @var string
	 */
	private static $api_url = 'https://maps.googleapis.com/maps/api/distancematrix/json';

	/**
	 * Making HTTP request to Google Maps Distance Matrix API
	 *
	 * @param array $args          Custom arguments for $settings and $package data.
	 * @param bool  $is_validation Is the request for validation purpose or not.
	 *
	 * @return (array[]|WP_Error) WP_Error on failure.
	 */
	public static function calculate_distance( $args = array(), $is_validation = false ) {
		$request_data = wp_parse_args(
			$args,
			array(
				'origins'      => $is_validation ? array( WCSDM_DEFAULT_LAT, WCSDM_DEFAULT_LNG ) : '',
				'destinations' => $is_validation ? array( WCSDM_TEST_LAT, WCSDM_TEST_LNG ) : '',
				'key'          => '',
				'avoid'        => '',
				'language'     => get_locale(),
				'units'        => 'metric',
				'mode'         => 'driving',
			)
		);

		foreach ( $request_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$request_data[ $key ] = implode( ',', array_map( 'rawurlencode', $value ) );
			} else {
				$request_data[ $key ] = rawurlencode( $value );
			}
		}

		$request_data_masked = array_merge(
			$request_data,
			array(
				'key' => '***',
			)
		);

		$api_response = wp_remote_get( add_query_arg( $request_data, self::$api_url ) );

		// Check if HTTP request is error.
		if ( is_wp_error( $api_response ) ) {
			$api_response->add_data(
				array(
					'request_data' => $request_data_masked,
				)
			);

			return $api_response;
		}

		if ( empty( $api_response['body'] ) ) {
			return new WP_Error(
				'api_response_body_empty',
				__( 'API response is empty.', 'wcsdm' ),
				array(
					'request_data' => $request_data_masked,
					'api_response' => $api_response,
				)
			);
		}

		// Decode API response body.
		$response_data = json_decode( $api_response['body'], true );

		if ( is_null( $response_data ) ) {
			return new WP_Error(
				'json_last_error',
				json_last_error_msg(),
				array(
					'request_data' => $request_data_masked,
					'api_response' => $api_response,
				)
			);
		}

		// Check API response is OK.
		$status = isset( $response_data['status'] ) ? $response_data['status'] : 'UNKNOWN_ERROR';

		if ( 'OK' !== $status ) {
			if ( isset( $response_data['error_message'] ) ) {
				return new WP_Error(
					$status,
					$response_data['error_message'],
					array(
						'request_data'  => $request_data_masked,
						'response_data' => $response_data,
					)
				);
			}

			return new WP_Error(
				$status,
				$status,
				array(
					'request_data'  => $request_data_masked,
					'response_data' => $response_data,
				)
			);
		}

		$element_errors = array(
			'NOT_FOUND'                 => __( 'Origin and/or destination of this pairing could not be geocoded.', 'wcsdm' ),
			'ZERO_RESULTS'              => __( 'No route could be found between the origin and destination.', 'wcsdm' ),
			'MAX_ROUTE_LENGTH_EXCEEDED' => __( 'Requested route is too long and cannot be processed.', 'wcsdm' ),
			'UNKNOWN'                   => __( 'Unknown error.', 'wcsdm' ),
		);

		$errors  = new WP_Error();
		$results = array();

		// Get the shipping distance.
		foreach ( $response_data['rows'] as $row ) {
			foreach ( $row['elements'] as $element ) {
				$element_status = isset( $element['status'] ) ? $element['status'] : 'UNKNOWN';

				if ( 'OK' === $element_status ) {
					$results[] = array(
						'distance' => $element['distance']['value'], // Distance in meters unit.
						'duration' => $element['duration']['value'], // Duration in seconds unit.
					);

					continue;
				}

				if ( ! isset( $element_errors[ $element_status ] ) ) {
					$element_status = 'UNKNOWN';
				}

				$errors->add(
					$element_status,
					$element_errors[ $element_status ],
					array(
						'request_data'  => $request_data_masked,
						'response_data' => $response_data,
					)
				);
			}
		}

		if ( ! empty( $results ) ) {
			return $results;
		}

		return $errors;
	}
}
