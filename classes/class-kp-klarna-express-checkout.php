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
		$this->klarna_express_checkout = new KlarnaExpressCheckout( 'woocommerce_klarna_payments_settings', kp_get_locale() );

		$this->klarna_express_checkout->ajax()->set_get_payload( array( $this, 'get_payload' ) );
		$this->klarna_express_checkout->ajax()->set_finalize_callback( array( $this, 'finalize_callback' ) );

		add_filter( 'wc_klarna_payments_supports', array( $this, 'maybe_add_pay_button_support' ) );
	}

	/**
	 * Maybe add pay button support.
	 *
	 * @param array $supports The supports array.
	 *
	 * @return array
	 */
	public function maybe_add_pay_button_support( $supports ) {
		if ( $this->is_enabled() ) {
			$supports[] = 'pay_button';
		}

		return $supports;
	}

	/**
	 * Returns if KEC is enabled or not.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->klarna_express_checkout->settings()->is_enabled();
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

		add_filter( 'http_headers_useragent', array( $this, 'add_to_useragent' ) );

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

	/**
	 * Add to user agent.
	 *
	 * @param string $user_agent The user agent.
	 *
	 * @return string
	 */
	public function add_to_useragent( $user_agent ) {
		// Only if the useragent contains KP.
		if ( strpos( $user_agent, 'KP' ) !== false ) {
			$user_agent .= ' KEC: ' . KlarnaExpressCheckout::VERSION;
		}

		return $user_agent;
	}
}
