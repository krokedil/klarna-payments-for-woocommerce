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
	 * Log message string
	 *
	 * @var $log
	 */
	public static $log;

	/**
	 * Logs an event.
	 *
	 * @param string $data The data string.
	 */
	public static function log( $data ) {
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings', array() );
		if ( 'yes' === $kp_settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'klarna_payments', wp_json_encode( $message ) );
		}

		if ( isset( $data['response']['code'] ) && ( $data['response']['code'] < 200 || $data['response']['code'] > 299 ) ) {
			self::log_to_db( $data );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param string $data Json string of data.
	 * @return array
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$request_body            = json_decode( $data['request']['body'], true );
			$data['request']['body'] = $request_body;
		}
		return $data;
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
	 * @return array
	 */
	public static function format_log( $payment_id, $method, $title, $request_args, $response, $code ) {
		return array(
			'id'             => $payment_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'plugin_version' => WC_KLARNA_PAYMENTS_VERSION,
		);
	}

	/**
	 * Logs an event in the WP DB.
	 *
	 * @param array $data The data to be logged.
	 */
	public static function log_to_db( $data ) {
		$logs = get_option( 'krokedil_debuglog_kp', array() );

		if ( ! empty( $logs ) ) {
			$logs = json_decode( $logs );
		}

		$logs   = array_slice( $logs, -14 );
		$logs[] = $data;
		$logs   = wp_json_encode( $logs );
		update_option( 'krokedil_debuglog_kp', $logs );
	}
}
