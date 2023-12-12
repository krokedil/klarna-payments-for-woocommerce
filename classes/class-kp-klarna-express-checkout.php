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
	 * Set session data from KEC auth response.
	 *
	 * @param string $session_id The session ID.
	 * @param string $client_token The client token.
	 *
	 * @return void
	 */
	public static function set_session_data( $session_id, $client_token ) {
		// Get the Klarna session.
		$country        = kp_get_klarna_country();
		$klarna_session = KP_WC()->api->get_session( $session_id, $country );

		// Verify the response.
		if ( is_wp_error( $klarna_session ) ) {
			return;
		}

		KP_WC()->session->klarna_session = array(
			'session_id'                => $session_id,
			'client_token'              => $client_token,
			'payment_method_categories' => $klarna_session['payment_method_categories'] ?? array(),
		);

		KP_WC()->session->session_country = $country;

		KP_WC()->session->is_kec = true;

		WC()->session->set( 'kp_session_data', wp_json_encode( KP_WC()->session ) );
	}
}
