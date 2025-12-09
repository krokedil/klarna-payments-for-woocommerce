<?php
/**
 * Class for managing the Klarna Interoperability token.
 *
 * @package KlarnaPayments/Classes
 */

defined( 'ABSPATH' ) || exit;

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
	 * Set the token and other interoperability data in the WooCommerce session.
	 *
	 * @param string $token The token to set.
	 *
	 * @return void
	 */
	public static function set_token( $token ) {
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

		// Send shopping data as default, if not set.
		if ( WC()->cart && ( ! isset( $settings['send_shopping_data'] ) || 'yes' === $settings['send_shopping_data'] ) ) {
			$customer_type         = $settings['customer_type'] ?? 'b2c';
			$order_data            = new KP_Order_Data( $customer_type );
			$interoperability_data = $order_data->get_klarna_order_lines_array();
		}

		WC()->session->set(
			'klarna_interoperability_token',
			array(
				'interoperability_token' => $token,
				'interoperability_data'  => array(
					'line_items' => $interoperability_data ?? array(),
				),
			)
		);
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
	}
}
