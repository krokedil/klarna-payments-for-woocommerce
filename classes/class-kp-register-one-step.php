<?php
/**
 * Class for registering the Klarna Express Checkout one step integration.
 *
 * @package KlarnaPayments/Classes
 */
class KP_Register_One_Step implements \KrokedilKlarnaPaymentsDeps\Krokedil\KlarnaExpressCheckout\Interfaces\AcquiringPartnerIntegration {

	/**
	 * Class constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get the partner ID for this integration.
	 */
	public function get_partner_id(): string {
		return get_option( 'klarna_acquiring_partner_key', null ) ?? '';
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
		$order->set_payment_method( 'klarna_payments' );
		$order->save();

		$redirect_url = '';
		switch ( $state ) {
			case 'COMPLETED':
				// 1-step flow - place the order and redirect to order received page.
				$response = KP_WC()->api->place_order( kp_get_klarna_country( $order ), $payload['payment_request_id'], $order->get_id() );

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
