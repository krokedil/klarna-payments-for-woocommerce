<?php

namespace Krokedil\Klarna\ExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Writes Klarna Express Checkout log entries that share one reference per purchase.
 */
class Logger {
	/**
	 * The keys that make up the reference of an entry.
	 *
	 * @var string[]
	 */
	private const REFERENCE_KEYS = array( 'payment_request_id', 'kec_unique_id', 'order_id' );

	/**
	 * Log a KEC event.
	 *
	 * Never pass secrets, signatures, request bodies or customer details in the context.
	 *
	 * @param string         $title   What happened.
	 * @param array          $context Details of the event. Any reference key in it overrides the derived one.
	 * @param \WC_Order|null $order   The order the event concerns, if known.
	 * @param string         $level   The WC_Log_Levels level. Default info.
	 *
	 * @return void
	 */
	public static function log( $title, $context = array(), $order = null, $level = \WC_Log_Levels::INFO ) {
		$entry = array(
			'title'          => "KEC: {$title}",
			'reference'      => self::get_reference( $context, $order ),
			'context'        => array_diff_key( $context, array_flip( self::REFERENCE_KEYS ) ),
			'plugin_version' => WC_KLARNA_PAYMENTS_VERSION,
		);

		\KP_Logger::log( $entry, $level );
	}

	/**
	 * Log a KEC event at the warning level.
	 *
	 * @param string         $title   What happened.
	 * @param array          $context Details of the event.
	 * @param \WC_Order|null $order   The order the event concerns, if known.
	 *
	 * @return void
	 */
	public static function warning( $title, $context = array(), $order = null ) {
		self::log( $title, $context, $order, \WC_Log_Levels::WARNING );
	}

	/**
	 * Build the reference from the session, then the order, then the context, the later winning.
	 *
	 * @param array          $context The context passed to the log call.
	 * @param \WC_Order|null $order   The order the event concerns, if known.
	 *
	 * @return array
	 */
	private static function get_reference( $context, $order ) {
		$reference = array_fill_keys( self::REFERENCE_KEYS, '' );

		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( $session ) {
			$reference['kec_unique_id'] = (string) $session->get( 'kec_one_step_unique_id', '' );
			$reference['order_id']      = (string) $session->get( 'kec_one_step_order_id', '' );
		}

		if ( $order instanceof \WC_Order ) {
			$reference['payment_request_id'] = (string) $order->get_meta( '_kec_payment_request_id' );
			$reference['kec_unique_id']      = (string) $order->get_meta( '_kec_unique_id' );
			$reference['order_id']           = (string) $order->get_id();
		}

		foreach ( self::REFERENCE_KEYS as $key ) {
			// The context may come from a webhook payload, so only a scalar is usable as a reference.
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) && '' !== (string) $context[ $key ] ) {
				$reference[ $key ] = (string) $context[ $key ];
			}
		}

		return array_filter(
			$reference,
			function ( $value ) {
				return '' !== $value;
			}
		);
	}
}
