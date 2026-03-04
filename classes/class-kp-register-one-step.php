<?php
/**
 * Class for registering the Klarna Express Checkout one step integration.
 *
 * @package KlarnaPayments/Classes
 */
class KP_Register_One_Step implements \KrokedilKlarnaPaymentsDeps\Krokedil\KlarnaExpressCheckout\Interfaces\AcquiringPartnerIntegration {
	/**
	 * The WooCommerce gateway instance.
	 *
	 * @var WC_Gateway_Klarna_Payments|null
	 */
	protected $gateway = null;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->gateway = new WC_Gateway_Klarna_Payments();
	}

	/**
	 * Get the partner ID for this integration.
	 */
	public function get_partner_id(): string {
		return $this->gateway->get_option( 'partner_account_id' );
	}

	/**
	 * Process the order state based on the interoperability token and data.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param string    $interoperability_token The interoperability token.
	 * @param array     $interoperability_data The interoperability data.
	 * @param string    $state The payment state.
	 * @param array     $payload The payload data.
	 */
	public function process_order_state( \WC_Order $order, string $interoperability_token, array $interoperability_data, string $state, array $payload ) {
		// Store interoperability data in order meta for future reference.
		$order->update_meta_data( '_klarna_interoperability_token', $interoperability_token );
		$order->update_meta_data( '_klarna_interoperability_data', $interoperability_data );
		$order->update_meta_data( '_klarna_payment_state', $state );
		$order->set_payment_method( $this->gateway->id );
		$order->save();

		$redirect_url = '';
		switch ( $state ) {
			case 'COMPLETED':
				// 1-step flow - place the order and redirect to order received page.
				$request  = new KP_Place_Order(
					array(
						'country'    => $order->get_billing_country(),
						'auth_token' => $interoperability_token,
						'order_id'   => $order->get_id(),
						'session_id' => KP_WC()->session->get_klarna_session_id(),
					)
				);
				$response = $request->request();

				if ( ! $response ) {
					break;
				}

				if ( is_wp_error( $response ) ) {
					break;
				}

				// Redirect to order received page.
				$redirect_url = $order->get_checkout_order_received_url();
				break;
			case 'PREPARATION':
				// 2-step flow - redirect to checkout with Klarna preselected.
				$redirect_url = add_query_arg(
					array(
						'payment_method'  => 'klarna_payments',
						'klarna_prepared' => '1',
					),
					wc_get_checkout_url()
				);
				break;

			default:
				break;
		}

		return $redirect_url;
	}

	/**
	 * Get the key for this integration.
	 */
	public function get_key() {
		return '';
	}
}
