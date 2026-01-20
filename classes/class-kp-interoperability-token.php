<?php
/**
 * Class for managing the Klarna Interoperability token.
 *
 * @package KlarnaPayments/Classes
 */

defined( 'ABSPATH' ) || exit;

use Krokedil\Klarna\PluginFeatures;

/**
 * Class for managing the Klarna Interoperability token.
 */
class KP_Interoperability_Token {
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// Clear the token from the session when they place an order.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_token' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'clear_token' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'set_data' ) );
	}

	/**
	 * Get the token from the WooCommerce session.
	 *
	 * @return string|null
	 */
	public static function get_token() {
		// If we don't have a session, we dont have a token.
		if ( null === WC()->session || ! WC()->session->has_session() ) {
			return null;
		}

		return WC()->session->get( 'klarna_interoperability_token' );
	}

	/**
	 * Get the interoperability data from the WooCommerce session.
	 *
	 * @return array|null
	 */
	public static function get_data() {
		// If we don't have a session, we dont have a token.
		if ( null === WC()->session || ! WC()->session->has_session() ) {
			return null;
		}

		return WC()->session->get( 'klarna_interoperability_data' ) ?? null;
	}

	/**
	 * Set the token in the WooCommerce session.
	 *
	 * @param string $token The token to set.
	 *
	 * @return void
	 */
	public static function set_token( $token ) {
		// Maybe start the WooCommerce session if it's not already started for the current user.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		WC()->session->set( 'klarna_interoperability_token', $token );
	}

	/**
	 * Set the interoperability data in the WooCommerce session.
	 *
	 * @param string $data The data to set.
	 *
	 * @return void
	 */
	public static function set_data() {
		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

		if ( null === WC()->session ) {
			return;
		}

		// Clear any existing data.
		WC()->session->__unset( 'klarna_interoperability_data' );

		// Make sure we have a cart and should send data.
		if ( ! WC()->cart || ! self::should_send_data() ) {
			return;
		}

		$customer_type         = $settings['customer_type'] ?? 'b2c';
		$order_data            = new KP_Order_Data( $customer_type );
		$interoperability_data = $order_data->get_klarna_order_lines_interoperability();

		WC()->session->set( 'klarna_interoperability_data', $interoperability_data );
	}

	/**
	 * Clear the token from the WooCommerce session.
	 *
	 * @return void
	 */
	public function clear_token() {
		if ( null === WC()->session ) {
			return;
		}

		WC()->session->__unset( 'klarna_interoperability_token' );
		WC()->session->__unset( 'klarna_interoperability_data' );
	}

	/**
	 * Determine if we should send interoperability data.
	 *
	 * @return bool
	 */
	public static function should_send_data() {
		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );
		if ( 'no' === ( $settings['send_shopping_data'] ?? 'yes' ) ) {
			return false;
		}

		$is_partner = PluginFeatures::get_acquiring_partner_key();
		if ( ! $is_partner ) {
			return false;
		}

		return true;
	}
}
