<?php
namespace Krokedil\Klarna;

/**
 * Class for registering the Klarna Express Checkout one step integration.
 */
class KECOneStepIntegration implements \KrokedilKlarnaPaymentsDeps\Krokedil\KlarnaExpressCheckout\Interfaces\AcquiringPartnerIntegration {
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
		$order->set_payment_method( 'klarna_payments' );

		if ( 'COMPLETED' === $state ) {
			$response = KP_WC()->api->place_order( kp_get_klarna_country( $order ), $payload['payment_token'], $order->get_id() );
		}

		if ( $response && ! is_wp_error( $response ) ) {
			kp_process_accepted( $order, $response );
		} else {
			$error_message = is_wp_error( $response ) ? $response->get_error_message() : 'Unknown error';
			KP_WC()->logger()->error( "[KEC One Step] Error placing order for Order ID {$order->get_id()}: {$error_message}" );
			kp_process_pending( $order, $response );
		}

		$order->save();
		kp_unset_session_values();

		return $order->get_checkout_order_received_url();
	}

	/**
	 * Get the key for this integration.
	 */
	public function get_key() {
		return 'klarna_payments';
	}
}
