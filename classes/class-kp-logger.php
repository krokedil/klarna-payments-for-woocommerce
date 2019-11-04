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
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings' );
		if ( 'yes' === $kp_settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'klarna_payments', wp_json_encode( $message ) );
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
		// Unset the snippet to prevent issues in the response.
		if ( isset( $response['snippet'] ) ) {
			unset( $response['snippet'] );
		}
		// Unset the snippet to prevent issues in the request body.
		if ( isset( $request_args['body'] ) ) {
			$request_body = json_decode( $request_args['body'], true );
			if ( isset( $request_body['snippet'] ) && $request_body['snippet'] ) {
				unset( $request_body['snippet'] );
				$request_args['body'] = wp_json_encode( $request_body );
			}
		}
		return array(
			'id'             => $payment_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ),
			'plugin_version' => WC_KLARNA_PAYMENTS_VERSION,
		);
	}
}
