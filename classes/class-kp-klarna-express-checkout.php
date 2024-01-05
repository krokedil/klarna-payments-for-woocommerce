<?php
/**
 * Handle the integration with Klarna Express Checkout.
 *
 * @package WC_Klarna_Payments/Classes
 */

defined( 'ABSPATH' ) || exit;

use Krokedil\KlarnaExpressCheckout\KlarnaExpressCheckout;

/**
 * Class KP_Klarna_Express_Checkout
 */
class KP_Klarna_Express_Checkout {
	/**
	 * The Klarna Express Checkout class.
	 *
	 * @var KlarnaExpressCheckout
	 */
	private $klarna_express_checkout;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->klarna_express_checkout = new KlarnaExpressCheckout();

		$this->klarna_express_checkout->ajax()->set_get_payload( array( $this, 'get_payload' ) );
		$this->klarna_express_checkout->ajax()->set_finalize_callback( array( $this, 'finalize_callback' ) );
	}

	/**
	 * Get order data for the cart.
	 *
	 * @return KP_Order_Data
	 */
	public function get_order_data_helper() {
		$settings          = get_option( 'woocommerce_klarna_payments_settings', array() );
		$customer_type     = $settings['customer_type'] ?? 'b2c';
		$order_data_helper = new KP_Order_Data( $customer_type ); // Should always use the cart.

		return $order_data_helper;
	}

	/**
	 * Get the payload for the Klarna Express Checkout.
	 *
	 * @return array
	 */
	public function get_payload() {
		$order_data_helper = $this->get_order_data_helper();

		$payload = array(
			'purchase_country'  => kp_get_klarna_country(),
			'purchase_currency' => get_woocommerce_currency(),
			'locale'            => kp_get_locale(),
			'order_amount'      => $order_data_helper->order_data->get_total(),
			'order_tax_amount'  => $order_data_helper->order_data->get_total_tax(),
			'order_lines'       => $order_data_helper->get_klarna_order_lines_array(),
		);

		return $payload;
	}

	/**
	 * Finalize order callback handler.
	 *
	 * @param string $auth_token The auth token from Klarna.
	 * @param string $order_id The order ID.
	 * @param string $order_key The order key.
	 *
	 * @throws Exception If the order is invalid.
	 * @return array
	 */
	public function finalize_callback( $auth_token, $order_id, $order_key ) {
		// Get the order from the order ID.
		$order = wc_get_order( $order_id );

		// Verify the order.
		if ( ! $order ) {
			throw new Exception( __( 'Invalid order.', 'klarna-payments-for-woocommerce' ) ); // phpcs:ignore
		}

		// Verify the order key.
		if ( $order->get_order_key() !== $order_key ) {
			throw new Exception( __( 'Invalid order key.', 'klarna-payments-for-woocommerce' ) ); // phpcs:ignore
		}

		// Make a place order call to Klarna.
		$place_order_response = KP_WC()->api->place_order( kp_get_klarna_country(), $auth_token, $order_id );

		// Verify the response.
		if ( is_wp_error( $place_order_response ) ) {
			throw new Exception( $place_order_response->get_error_message() ); // phpcs:ignore
		}

		$fraud_status = $place_order_response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $place_order_response );
				kp_unset_session_values();
				return array(
					'result'   => 'success',
					'redirect' => $place_order_response['redirect_url'],
				);
			case 'PENDING':
				kp_process_pending( $order, $place_order_response );
				kp_unset_session_values();
				return array(
					'result'   => 'success',
					'redirect' => $place_order_response['redirect_url'],
				);
			case 'REJECTED':
				kp_process_rejected( $order, $place_order_response );
				kp_unset_session_values();
				return array(
					'result'   => 'error',
					'redirect' => $order->get_cancel_order_url_raw(),
				);
			default:
				kp_unset_session_values();
				return array(
					'result'   => 'error',
					'redirect' => $order->get_cancel_order_url_raw(),
				);
		}
	}
}
