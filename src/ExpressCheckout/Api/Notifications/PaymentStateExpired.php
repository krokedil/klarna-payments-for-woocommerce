<?php

namespace Krokedil\Klarna\ExpressCheckout\Api\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Notification handler for the payment.request.state-change.expired event.
 */
class PaymentStateExpired extends Handler {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'payment.request.state-change.expired';

	/**
	 * The version for the notification.
	 *
	 * @var string
	 */
	protected $event_version = 'v2';

	/**
	 * Handle the notification for the payment expired event.
	 *
	 * @param array $payload The payload from the notification.
	 *
	 * @return \WP_REST_Response|null
	 * @throws \WP_Exception If the notification cannot be handled.
	 */
	public function handle_notification( $payload ) {
		$payment_request_id     = $payload['payment_request_id'] ?? null;
		$interoperability_token = $payload['klarna_network_session_token'] ?? '';

		if ( ! $payment_request_id ) {
			throw new \WP_Exception( 'Missing required fields in the payload.' );
		}

		$order = $this->get_wc_order_by_payment_request_id( $payment_request_id );

		$order->update_status( 'cancelled', __( 'Order cancelled due to expired payment request.', 'klarna-payments-for-woocommerce' ) );

		do_action( 'kec_cancel_order', $order, $interoperability_token, array(), $payload['state'], $payload );

		return null;
	}
}
