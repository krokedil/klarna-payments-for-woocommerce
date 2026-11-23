<?php
namespace Krokedil\Klarna\ExpressCheckout;

defined( 'ABSPATH' ) || exit;

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
		if ( ! empty( $order->get_date_paid() ) ) {
			KP_WC()->logger()->info( "[KEC One Step] Order {$order->get_id()} is already paid, the completed payment was not placed again." );
			return;
		}

		$response = KP_WC()->api->place_order( kp_get_klarna_country( $order ), $payload['payment_token'], $order->get_id() );
		if ( ! empty( $response ) && ! is_wp_error( $response ) ) {
			$this->process_fraud_status( $order, $response );
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
	 * Act on Klarna's fraud assessment of a placed order, as every other place order path does.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param array     $response The place order response from Klarna.
	 */
	private function process_fraud_status( \WC_Order $order, array $response ) {
		$fraud_status = $response['fraud_status'] ?? $this->get_klarna_order_fraud_status( $order, $response['order_id'] ?? '' );

		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $response );
				break;
			case 'PENDING':
				kp_process_pending( $order, $response );
				break;
			case 'REJECTED':
				kp_process_rejected( $order, $response );
				break;
			default:
				// Klarna created the order, so keep its ID for the merchant, but hold it until the status is known.
				kp_save_order_meta_data( $order, $response );
				$order->update_status(
					'on-hold',
					sprintf(
						/* translators: [merchant-facing]. %1$s: Klarna fraud status. %2$s: Klarna order ID. */
						__( 'Klarna returned an unknown fraud status (%1$s) for order %2$s. Verify the order in the Klarna portal before processing it.', 'klarna-payments-for-woocommerce' ),
						$fraud_status,
						$response['order_id'] ?? ''
					)
				);
				break;
		}

		KP_WC()->logger()->info( "[KEC One Step] Placed order {$order->get_id()} with fraud status {$fraud_status}." );
	}

	/**
	 * Get the fraud status from the Klarna order, since placing a one step payment token does not return it.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 * @param string    $klarna_order_id The Klarna order ID.
	 *
	 * @return string The fraud status, or an empty string if it could not be retrieved.
	 */
	private function get_klarna_order_fraud_status( \WC_Order $order, $klarna_order_id ) {
		if ( empty( $klarna_order_id ) ) {
			return '';
		}

		$klarna_order = KP_WC()->api->get_klarna_om_order( kp_get_klarna_country( $order ), $klarna_order_id, kp_get_order_credentials_country( $order ) );
		if ( is_wp_error( $klarna_order ) || ! is_array( $klarna_order ) ) {
			KP_WC()->logger()->error( "[KEC One Step] Could not retrieve the Klarna order for order {$order->get_id()} to read its fraud status." );
			return '';
		}

		$fraud_status = $klarna_order['fraud_status'] ?? '';

		// Order management reports a stopped order, which must not be fulfilled either.
		return 'STOPPED' === $fraud_status ? 'REJECTED' : $fraud_status;
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
			$missing = $payment_request_id ? 'interoperability_token' : 'payment_request_id';
			KP_WC()->logger()->error( "[KEC One Step] Missing {$missing} in the payload for expired payment on order {$order->get_id()}." );
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
