<?php
namespace Krokedil\Klarna\ExpressCheckout;

/**
 * Class for registering the Klarna Express Checkout one step integration.
 */
class KECOneStepIntegration implements Interfaces\AcquiringPartnerIntegration {
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

		switch ( $state ) {
			case 'COMPLETED':
				$this->handle_completed_payment( $order, $payload );
				break;
			case 'EXPIRED':
				$this->handle_expired_payment( $order, $payload );
				break;
			default:
				break;
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

	/**
	 * Handle completed one step checkout payments.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param array     $payload The payload data from Klarna.
	 */
	private function handle_completed_payment( \WC_Order $order, array $payload ) {
		$response = KP_WC()->api->place_order( kp_get_klarna_country( $order ), $payload['payment_token'], $order->get_id() );
		if ( ! empty( $response ) && ! is_wp_error( $response ) ) {
			kp_process_accepted( $order, $response );
		} else {
			$error_message = is_wp_error( $response ) ? $response->get_error_message() : 'Unknown error';
			KP_WC()->logger()->error( "[KEC One Step] Error placing order for Order ID {$order->get_id()}: {$error_message}" );
			$order->update_status( 'on-hold', 'Klarna payment failed or was not completed during One Step Checkout.' );
			kp_save_order_meta_data( $order, $response );
			/**
			 * Triggers when a One Step Checkout payment could not be completed and the order is left pending review.
			 *
			 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review
			 * @param int   $order_id The WooCommerce order ID.
			 * @param array $response The place order response from Klarna.
			 */
			do_action( 'wc_klarna_payments_pending', $order->get_id(), $response );

			/**
			 * Triggers when a One Step Checkout payment is left pending review. Fires alongside klarna_payments_pending.
			 *
			 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review
			 * @param int   $order_id The WooCommerce order ID.
			 * @param array $response The place order response from Klarna.
			 */
			do_action( 'wc_klarna_pending', $order->get_id(), $response );
		}
	}

	/**
	 * Handle expired one step checkout payments.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param array     $payload The payload data from Klarna.
	 *
	 * @throws \WP_Exception If required fields are missing in the payload.
	 */
	private function handle_expired_payment( \WC_Order $order, array $payload ) {
		$state                  = $payload['state'] ?? null;
		$payment_request_id     = $payload['payment_request_id'] ?? null;
		$interoperability_token = $payload['interoperability_token'] ?? null;
		$interoperability_data  = array();

		if ( ! $payment_request_id || ! $interoperability_token ) {
			KP_WC()->logger()->error( '[KEC One Step] Missing required fields in payload for expired payment: ' . wp_json_encode( $payload ) );
			return;
		}

		/* translators: [merchant-facing]. */
		$order->update_status( 'cancelled', __( 'Order cancelled due to expired payment request.', 'klarna-payments-for-woocommerce' ) );

		/**
		 * Triggers when a Klarna Express Checkout order is cancelled due to an expired payment request.
		 *
		 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-a-kec-payment-expires-or-is-cancelled
		 * @param \WC_Order $order The WooCommerce order object.
		 * @param string    $interoperability_token The Klarna interoperability token.
		 * @param array     $interoperability_data The interoperability data. Empty when cancelling.
		 * @param string    $state The payment state reported by Klarna.
		 * @param array     $payload The payload data from Klarna.
		 */
		do_action( 'kec_cancel_order', $order, $interoperability_token, $interoperability_data, $state, $payload );
	}
}
