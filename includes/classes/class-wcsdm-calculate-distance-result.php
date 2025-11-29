<?php
/**
 * Class for handling distance calculation results.
 *
 * This class encapsulates the results of distance calculations performed by the
 * distance calculator API providers (Google Maps, Mapbox, etc.) in WooReer.
 * It works in conjunction with Wcsdm_Request_Dispatcher to provide a standardized
 * way to handle both successful distance measurements and error conditions.
 *
 * The class implements a factory pattern with static methods for creating instances,
 * ensuring proper initialization of success and error states. This design prevents
 * invalid state combinations and provides a clear, type-safe API for working with
 * distance calculation results.
 *
 * Key Features:
 * - Stores calculated distances in meters for consistency across providers
 * - Handles error conditions with descriptive error messages
 * - Maintains reference to the request dispatcher for debugging and logging
 * - Provides factory methods (meters/error) for creating success/error results
 * - Offers consistent interface for accessing result data
 * - Supports debugging through dispatcher access and variable inspection
 *
 * Usage Pattern:
 * 1. API providers create results using static factory methods
 * 2. Calling code checks is_error() to determine result type
 * 3. Extract data using get_meters() for success or get_error() for failures
 * 4. Access dispatcher for debugging if needed
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
 * Distance calculation result handler class.
 *
 * Acts as a wrapper around distance calculation results from WooReer API providers,
 * providing a consistent interface regardless of which distance calculation API
 * (Google Maps, Mapbox, etc.) was used. This class ensures type safety and proper
 * error handling through its factory pattern implementation.
 *
 * The class stores both successful results (distance in meters) and error conditions,
 * along with the request dispatcher instance for comprehensive debugging capabilities.
 * By maintaining a reference to the dispatcher, developers can access raw request/response
 * data, HTTP headers, status codes, and other diagnostic information.
 *
 * Design Principles:
 * - Immutable result objects (properties cannot be changed after creation)
 * - Factory pattern for controlled instantiation
 * - Clear separation between success and error states
 * - Consistent units (meters) across all API providers
 *
 * @since 3.0
 */
class Wcsdm_Calculate_Distance_Result {

	/**
	 * The calculated distance object.
	 *
	 * Stores a Wcsdm_Distance instance representing the successfully calculated
	 * distance between origin and destination. This property is only populated
	 * when is_error is false and contains the distance measurement from the API provider.
	 *
	 * The Wcsdm_Distance object provides distance values in multiple units (meters,
	 * kilometers, miles) for flexibility in shipping calculations and display.
	 *
	 * @since 3.0
	 * @var Wcsdm_Distance Distance object containing the calculated distance, only valid when is_error is false
	 */
	private $distance;

	/**
	 * The error message from a failed calculation.
	 *
	 * Stores a human-readable description of what went wrong during the distance
	 * calculation process. This property is only populated when is_error is true
	 * and should only be accessed in error conditions.
	 *
	 * Error messages may originate from various sources:
	 * - API provider error responses (e.g., "Invalid API key", "ZERO_RESULTS")
	 * - Network communication errors (e.g., timeout, connection refused)
	 * - Internal validation failures (e.g., invalid coordinates, missing parameters)
	 * - HTTP errors (e.g., 404, 500 status codes)
	 *
	 * The messages are intended to be informative for debugging and may be displayed
	 * to administrators, but should be sanitized before showing to end users.
	 *
	 * @since 3.0
	 * @var string Human-readable error description, only populated when is_error is true
	 */
	private $error;

	/**
	 * Flag indicating if the calculation failed.
	 *
	 * Boolean flag that determines whether this result represents a successful
	 * calculation (false) or an error condition (true). This is the primary
	 * indicator that should be checked before accessing other properties.
	 *
	 * State implications:
	 * - When true: error property contains the error message, meters is not valid
	 * - When false: meters property contains the calculated distance, error is not valid
	 *
	 * Always check this flag using is_error() method before accessing result data
	 * to ensure you're reading the correct property for the current state.
	 *
	 * @since 3.0
	 * @var bool True for error condition, false for successful calculation
	 */
	private $is_error = false;

	/**
	 * The request dispatcher instance that performed the calculation.
	 *
	 * Stores a reference to the Wcsdm_Request_Dispatcher instance that made the
	 * HTTP API request to calculate the distance. This allows access to comprehensive
	 * request/response details for debugging, logging, and troubleshooting purposes.
	 *
	 * The dispatcher contains valuable diagnostic information including:
	 * - Complete HTTP request details (URL, method, headers, body)
	 * - Full HTTP response (status code, headers, body)
	 * - Request timing and performance metrics
	 * - Raw API response data for manual inspection
	 *
	 * This is particularly useful when investigating API issues, optimizing
	 * performance, or implementing custom logging and monitoring solutions.
	 *
	 * @since 3.0
	 * @var Wcsdm_Request_Dispatcher Request dispatcher instance with full request/response context
	 */
	private $dispatcher;

	/**
	 * Constructor for the result object.
	 *
	 * Private constructor that enforces the use of factory methods meters() and error()
	 * for creating instances. This design pattern ensures proper initialization of success
	 * and error states, preventing invalid object configurations where both distance and
	 * error might be set simultaneously.
	 *
	 * The constructor only accepts and stores the request dispatcher, while the factory
	 * methods handle setting either the distance (for success) or error message (for failure).
	 * This guarantees that all result objects are created in a valid, consistent state.
	 *
	 * Why private?
	 * - Prevents direct instantiation with 'new' keyword
	 * - Forces use of semantic factory methods (meters/error)
	 * - Ensures object is always in a valid state
	 * - Makes code more readable and self-documenting
	 *
	 * @since 3.0
	 * @param Wcsdm_Request_Dispatcher $dispatcher The request dispatcher that performed the API calculation.
	 */
	private function __construct( Wcsdm_Request_Dispatcher $dispatcher ) {
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Factory method to create a successful distance calculation result.
	 *
	 * Creates a new result instance representing a successful distance calculation in WooReer.
	 * This method should be used by API providers after successfully calculating the distance
	 * between origin and destination coordinates.
	 *
	 * The method instantiates a new result object, stores the distance value, and preserves
	 * the dispatcher instance for debugging. The is_error flag remains false (default) to
	 * indicate successful completion.
	 *
	 * Example usage in API provider:
	 * ```php
	 * // After successful API response
	 * $distance = Wcsdm_Distance::from_m($meters);
	 * return Wcsdm_Calculate_Distance_Result::distance(
	 *     $distance,
	 *     $dispatcher
	 * );
	 * ```
	 *
	 * @since 3.0
	 * @param Wcsdm_Distance           $distance   The calculated distance object containing the measurement.
	 * @param Wcsdm_Request_Dispatcher $dispatcher Request dispatcher with API context for debugging.
	 * @return Wcsdm_Calculate_Distance_Result A new result instance representing the successful calculation.
	 */
	public static function distance( Wcsdm_Distance $distance, Wcsdm_Request_Dispatcher $dispatcher ):Wcsdm_Calculate_Distance_Result {
		// Create new instance using private constructor.
		$result = new self( $dispatcher );

		// Set the successful distance value.
		$result->distance = $distance;

		return $result;
	}

	/**
	 * Factory method to create an error result for a failed calculation.
	 *
	 * Creates a new result instance representing a failed distance calculation in WooReer.
	 * This method should be used whenever an API request fails, returns invalid data, or
	 * encounters any error condition that prevents successful distance calculation.
	 *
	 * The method instantiates a new result object, sets is_error to true, stores the
	 * error message, and preserves the dispatcher instance for debugging. The meters
	 * property remains unset as it's not valid for error conditions.
	 *
	 * Common Error Scenarios:
	 * - Network errors: Connection timeout, DNS failure, connection refused
	 * - API errors: Invalid API key, quota exceeded, service unavailable
	 * - Invalid responses: Malformed JSON, unexpected response structure
	 * - Geocoding failures: Address not found, ambiguous location
	 * - Route calculation issues: No route available, ZERO_RESULTS
	 * - Validation errors: Invalid coordinates, missing required parameters
	 * - HTTP errors: 400 Bad Request, 401 Unauthorized, 500 Internal Server Error
	 *
	 * Error Message Guidelines:
	 * - Should be descriptive and helpful for debugging
	 * - May contain technical details for administrators
	 * - Should be sanitized before displaying to end users
	 * - Include relevant context (API name, error code if available)
	 *
	 * Example usage in API provider:
	 * ```php
	 * // Network error
	 * if (is_wp_error($response)) {
	 *     return Wcsdm_Calculate_Distance_Result::error(
	 *         'API request failed: ' . $response->get_error_message(),
	 *         $dispatcher
	 *     );
	 * }
	 *
	 * // API error response
	 * if (isset($response->error)) {
	 *     return Wcsdm_Calculate_Distance_Result::error(
	 *         $response->error->message,
	 *         $dispatcher
	 *     );
	 * }
	 * ```
	 *
	 * @since 3.0
	 * @param string                   $error_message Human-readable error description for debugging/logging.
	 * @param Wcsdm_Request_Dispatcher $dispatcher    Request dispatcher with API context for debugging.
	 * @return Wcsdm_Calculate_Distance_Result A new result instance representing the error condition.
	 */
	public static function error( string $error_message, Wcsdm_Request_Dispatcher $dispatcher ):Wcsdm_Calculate_Distance_Result {
		// Create new instance using private constructor.
		$result = new self( $dispatcher );

		// Set error flag and message.
		$result->is_error = true;
		$result->error    = $error_message;

		return $result;
	}

	/**
	 * Check if the calculation resulted in an error.
	 *
	 * Determines whether this result instance represents a successful calculation
	 * or an error condition. This is the primary method that should always be checked
	 * before attempting to access any result data to ensure you're handling the
	 * correct state.
	 *
	 * Return Values:
	 * - true: The calculation failed, use get_error() to retrieve error message
	 * - false: The calculation succeeded, use get_meters() to retrieve distance
	 *
	 * This check is critical for proper error handling in WooReer shipping calculations.
	 * Accessing get_meters() when is_error() returns true, or accessing get_error()
	 * when it returns false, will result in invalid/undefined data.
	 *
	 * Best Practices:
	 * - Always call this method before accessing result data
	 * - Use early returns for error conditions to simplify code flow
	 * - Log errors appropriately for debugging
	 * - Provide user-friendly messages based on error conditions
	 *
	 * Example usage patterns:
	 * ```php
	 * // Pattern 1: Early return
	 * if ($result->is_error()) {
	 *     $error = $result->get_error();
	 *     wc_add_notice($error, 'error');
	 *     return;
	 * }
	 * $distance = $result->get_meters();
	 *
	 * // Pattern 2: Conditional processing
	 * if ($result->is_error()) {
	 *     // Handle error case
	 *     $this->log_error($result->get_error());
	 *     $shipping_cost = $this->get_default_cost();
	 * } else {
	 *     // Process successful calculation
	 *     $distance = $result->get_meters();
	 *     $shipping_cost = $this->calculate_cost($distance);
	 * }
	 * ```
	 *
	 * @since  3.0
	 * @return bool True if this represents an error condition, false if calculation succeeded.
	 */
	public function is_error():bool {
		return $this->is_error;
	}

	/**
	 * Get the error message from a failed calculation.
	 *
	 * Retrieves the human-readable error message describing what went wrong during
	 * the distance calculation process. This method should only be called after
	 * verifying that is_error() returns true to ensure the error message is valid.
	 *
	 * Error Message Sources:
	 * - API provider error responses (e.g., "ZERO_RESULTS", "INVALID_REQUEST")
	 * - Network/HTTP errors (e.g., "Connection timeout", "HTTP 500 error")
	 * - Internal validation errors (e.g., "Invalid coordinates format")
	 * - WordPress HTTP API errors (e.g., "cURL error 28: Connection timed out")
	 *
	 * The error messages are primarily intended for:
	 * - Administrator debugging and troubleshooting
	 * - Error logging and monitoring systems
	 * - Development and testing diagnostics
	 *
	 * When Displaying to Users:
	 * - Sanitize the message to remove sensitive information
	 * - Consider translating technical errors to user-friendly messages
	 * - May want to provide generic message and log details separately
	 * - Avoid exposing API keys, internal paths, or system details
	 *
	 * Example usage:
	 * ```php
	 * if ($result->is_error()) {
	 *     // For administrators/debugging
	 *     error_log('WooReer distance calculation failed: ' . $result->get_error());
	 *
	 *     // For end users (sanitized)
	 *     wc_add_notice(
	 *         __('Unable to calculate shipping distance. Please try again.', 'wcsdm'),
	 *         'error'
	 *     );
	 *     return;
	 * }
	 * ```
	 *
	 * @since  3.0
	 * @return string Human-readable error message describing the calculation failure.
	 */
	public function get_error():string {
		return $this->error;
	}

	/**
	 * Get the calculated distance from a successful calculation.
	 *
	 * Retrieves the Wcsdm_Distance object containing the calculated distance between
	 * origin and destination. This method should only be called after verifying that
	 * is_error() returns false to ensure the distance data is valid.
	 *
	 * The returned Wcsdm_Distance object provides distance values in multiple units:
	 * - Meters: Base unit used by all API providers
	 * - Kilometers: For metric system display
	 * - Miles: For imperial system display
	 *
	 * Example usage:
	 * ```php
	 * if (!$result->is_error()) {
	 *     $distance = $result->get_distance();
	 *     $meters = $distance->get_meters();
	 *     $kilometers = $distance->get_kilometers();
	 *
	 *     // Use distance for shipping cost calculation
	 *     $shipping_cost = $this->calculate_cost($distance);
	 * }
	 * ```
	 *
	 * @since  3.0
	 * @return Wcsdm_Distance Distance object containing the calculated distance in various units.
	 */
	public function get_distance():Wcsdm_Distance {
		return $this->distance;
	}

	/**
	 * Get the request dispatcher instance associated with this result.
	 *
	 * Returns the Wcsdm_Request_Dispatcher instance that was used to perform the
	 * distance calculation API request. This provides access to comprehensive request
	 * and response details, making it invaluable for debugging, logging, monitoring,
	 * and troubleshooting WooReer distance calculation operations.
	 *
	 * Available Dispatcher Information:
	 * - Complete HTTP request details (URL, method, headers, request body)
	 * - Full HTTP response data (status code, response headers, response body)
	 * - Raw API response for manual inspection and parsing
	 * - Request timing and performance metrics
	 * - Error details for failed requests
	 *
	 * Common Use Cases:
	 * - Debugging API integration issues
	 * - Logging request/response data for audit trails
	 * - Monitoring API performance and response times
	 * - Troubleshooting unexpected behavior or errors
	 * - Implementing custom logging solutions
	 * - Analyzing API usage patterns
	 * - Verifying request parameters sent to APIs
	 * - Examining raw response data for edge cases
	 *
	 * Example usage:
	 * ```php
	 * // Access dispatcher for debugging
	 * $dispatcher = $result->get_dispatcher();
	 *
	 * // Log request details
	 * error_log('API URL: ' . $dispatcher->get_request_url());
	 * error_log('Response Code: ' . $dispatcher->get_response_code());
	 *
	 * // Get raw response for inspection
	 * $raw_response = $dispatcher->get_raw_response();
	 * error_log('Raw API Response: ' . print_r($raw_response, true));
	 *
	 * // Check request timing
	 * if ($dispatcher->get_request_time() > 3000) {
	 *     error_log('Slow API response detected: ' . $dispatcher->get_request_time() . 'ms');
	 * }
	 * ```
	 *
	 * @since 3.0
	 * @return Wcsdm_Request_Dispatcher The request dispatcher instance with full request/response context.
	 */
	public function get_dispatcher():Wcsdm_Request_Dispatcher {
		return $this->dispatcher;
	}

	/**
	 * Get all object properties as an associative array.
	 *
	 * Returns an associative array containing all properties of this result instance,
	 * including their current values. This method is primarily useful for debugging,
	 * logging, and inspection purposes during development and troubleshooting.
	 *
	 * The returned array includes:
	 * - distance: The Wcsdm_Distance object (if successful calculation)
	 * - error: The error message string (if failed calculation)
	 * - is_error: Boolean flag indicating success/failure state
	 * - dispatcher: The Wcsdm_Request_Dispatcher instance
	 *
	 * Use Cases:
	 * - Debugging: Inspect all properties at once
	 * - Logging: Capture complete state for error logs
	 * - Testing: Verify object state in unit tests
	 * - Serialization: Convert object to array for storage/transmission
	 *
	 * Example usage:
	 * ```php
	 * // Debug output
	 * error_log('Result state: ' . print_r($result->vars(), true));
	 *
	 * // Conditional inspection
	 * $vars = $result->vars();
	 * if ($vars['is_error']) {
	 *     error_log('Error occurred: ' . $vars['error']);
	 * }
	 * ```
	 *
	 * @since  3.0
	 * @return array Associative array of all object properties with their current values.
	 */
	public function vars():array {
		return get_object_vars( $this );
	}

}
