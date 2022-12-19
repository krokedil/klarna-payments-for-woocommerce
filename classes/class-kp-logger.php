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
		update_option( 'krokedil_debuglog_kp', $logs, false );
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();

		// Skip the first 4 items in the stack trace to skip to the actual caller.
		$count = count( $debug_data );
		for ( $i = 5; $i < $count; $i++ ) {
			self::process_debug_line( $stack, $debug_data[ $i ] );
		}

		return $stack;
	}

	/**
	 * Processes a debug line, and adds it to the stack trace.
	 *
	 * @param array $stack The stack trace passed by reference.
	 * @param array $debug_line The debug info from the raw stack trace.
	 * @return void
	 */
	private static function process_debug_line( &$stack, $debug_line ) {
		$class    = $debug_line['class'] ?? '';
		$type     = $debug_line['type'] ?? '';
		$function = $debug_line['function'] ?? '';
		$args     = $debug_line['args'] ?? array();

		self::handle_wp_hook( $class, $function, $args, $debug_line );

		// Construct a caller string.
		$caller = self::get_caller_string( $class, $type, $function, $args );

		$row = array(
			'file'     => $debug_line['file'] ?? '',
			'line'     => $debug_line['line'] ?? '',
			'function' => $caller,
		);

		$stack[] = $row;
	}

	/**
	 * Get the caller string from the stack trace line.
	 *
	 * @param string $class The class name.
	 * @param string $type The type, :: or -> depending on if its a static or non static class.
	 * @param string $function The function name.
	 * @param array  $args The arguments passed to the caller.
	 * @return string
	 */
	private static function get_caller_string( $class, $type, $function, $args ) {
		$log_extra_data = apply_filters( 'wc_kp_extra_debug', false );

		// Construct a caller string.
		$caller  = $class . $type . $function;
		$caller .= '(';
		$caller .= $log_extra_data ? implode(
			', ',
			array_map(
				function( $value ) {
					// Json encode all values so that we can see what objects and arrays are passed. Dont escape anything, partial output on errors, and ignore slashes and line terminators.
					return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_LINE_TERMINATORS | JSON_UNESCAPED_SLASHES );
				},
				$args
			)
		) : '';
		$caller .= ')';

		return $caller;
	}

	/**
	 * Handles any WP hooks that are called.
	 *
	 * @param string $class The class name.
	 * @param string $function The function name.
	 * @param array  $args The arguments. Passed by reference to allow modifications.
	 * @param array  $debug_line The debug line.
	 * @return void
	 */
	private static function handle_wp_hook( $class, $function, &$args, $debug_line ) {
		if ( 'WP_Hook' === $class && in_array( $function, array( 'apply_filters', 'do_action' ), true ) ) {
			$wp_hook = $debug_line['object'] ?? null;
			if ( $wp_hook instanceof WP_Hook ) {
				$priority = $wp_hook->current_priority();
				$current  = current( $wp_hook->current() );
				$name     = '';

				foreach ( $current['function'] ?? array() as $function ) {
					$name .= self::get_name_of_hook_function( $function );
				}

				array_unshift( $args, $name . ' (' . $priority . ')' );
			}
		}
	}

	/**
	 * Gets a string back from the object passed to match the name of any class that it is an instance off.
	 *
	 * @param mixed $object The potential class object.
	 * @return string
	 */
	private static function get_name_of_hook_function( $object ) {
		// If the object is null, reutrn an empty string.
		if ( null === $object ) {
			return '';
		}

		// If its not an object, check if class exists, else return as function name.
		if ( ! is_object( $object ) ) {
			return $object . ( class_exists( $object ) ? '::' : '()' );
		}

		// Get the class name and return it with appended static divider.
		return get_class( $object ) . '::';
	}
}
