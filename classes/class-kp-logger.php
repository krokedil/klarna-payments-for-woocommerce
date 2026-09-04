<?php
/**
 * Logger class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Logger class.
 */
class KP_Logger {
	/**
	 * The value written to the log in place of data that is masked.
	 *
	 * @var string
	 */
	const REDACTED = '[REDACTED]';

	/**
	 * The value written to the log in place of a credential that was expected but absent.
	 *
	 * @var string
	 */
	const MISSING = '[MISSING]';

	/**
	 * Log message string
	 *
	 * @var string $log
	 */
	public static $log;

	/**
	 * Logs an event.
	 *
	 * @param array|string $data The data string.
	 */
	public static function log( $data ) {
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings', array() );
		if ( 'no' !== $kp_settings['logging'] ) {
			$message = self::format_data( $data );
			KP_WC()->logger()->info( wp_json_encode( $message ) );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param array $data Json string of data.
	 * @return array
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$request_body            = json_decode( $data['request']['body'], true );
			$data['request']['body'] = $request_body;
		}
		return self::redact( $data );
	}

	/**
	 * Returns the payload keys whose values are masked before a log entry is written.
	 *
	 * @return array
	 */
	public static function get_redacted_fields() {
		/**
		 * Filters the payload keys that are masked before a log entry is written.
		 *
		 * @param array $fields The payload keys to mask.
		 */
		return apply_filters(
			'wc_kp_log_redacted_fields',
			array(
				'given_name',
				'family_name',
				'organization_name',
				'attention',
				'email',
				'phone',
				'street_address',
				'street_address2',
				'date_of_birth',
				'national_identification_number',
				'klarna_access_token',
			)
		);
	}

	/**
	 * Masks personal data in a log payload.
	 *
	 * @param mixed      $data The log payload, or a branch of it.
	 * @param array|null $fields The keys to mask. Resolved once at the top of the recursion.
	 * @return mixed
	 */
	public static function redact( $data, $fields = null ) {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return $data;
		}

		$fields = null === $fields ? self::get_redacted_fields() : $fields;

		// Clone, so redacting a decoded response body cannot mutate an object the caller still uses.
		$redacted = is_object( $data ) ? clone $data : $data;

		foreach ( $data as $key => $value ) {
			$value = in_array( $key, $fields, true ) && ! empty( $value ) ? self::REDACTED : self::redact( $value, $fields );

			if ( is_object( $redacted ) ) {
				$redacted->{$key} = $value;
			} else {
				$redacted[ $key ] = $value;
			}
		}

		return $redacted;
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $payment_id The "Klarna Payments" Payment ID.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @param string $request_url The request URL for the request.
	 * @return array
	 */
	public static function format_log( $payment_id, $method, $title, $request_args, $response, $code, $request_url = null ) {
		return array(
			'id'             => $payment_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'request_url'    => $request_url,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'stack'          => self::get_stack(),
			'plugin_version' => WC_KLARNA_PAYMENTS_VERSION,
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return string
	 */
	public static function get_stack() {
		return wp_debug_backtrace_summary( __CLASS__, 3, false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Used for logging, not display.
	}

	/**
	 * The number of days this plugin's log files are kept.
	 *
	 * @return int Zero if the merchant has opted out, leaving the files to WooCommerce's own cleanup.
	 */
	public static function get_retention_days() {
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings', array() );

		/**
		 * Filters how many days the Klarna log files are kept before they are deleted.
		 *
		 * @param int $days The retention period in days. Zero disables this plugin's own cleanup.
		 */
		return max( 0, (int) apply_filters( 'wc_kp_log_retention_days', $kp_settings['log_retention_days'] ?? 30 ) );
	}

	/**
	 * Makes sure the log files are cleaned up daily.
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( ! is_admin() && ! wp_doing_cron() && ! defined( 'WP_CLI' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( 'kp_cleanup_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'kp_cleanup_logs' );
		}
	}

	/**
	 * Deletes this plugin's expired log files.
	 *
	 * @return void
	 */
	public static function cleanup_logs() {
		$days = self::get_retention_days();
		if ( 0 === $days || ! defined( 'WC_LOG_DIR' ) ) {
			return;
		}

		$expired_before = time() - ( $days * DAY_IN_SECONDS );

		foreach ( (array) glob( trailingslashit( WC_LOG_DIR ) . 'klarna_payments-*.log' ) as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			// A file whose age cannot be read is kept, rather than assumed to be expired.
			$modified_at = filemtime( $file );
			if ( false !== $modified_at && $modified_at < $expired_before ) {
				wp_delete_file( $file );
			}
		}
	}
}
