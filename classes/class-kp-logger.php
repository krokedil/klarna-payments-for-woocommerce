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
}
