<?php

namespace Krokedil\Klarna\ExpressCheckout\Api\Notifications;

use Krokedil\Klarna\PluginFeatures;
use Krokedil\Klarna\ExpressCheckout\Interfaces\AcquiringPartnerIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for notification handlers.
 */
abstract class Handler {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type;

	/**
	 * The version for the notification.
	 *
	 * @var string
	 */
	protected $event_version;

	/**
	 * Check if the event type and version matches the notification handler.
	 *
	 * @param string $event_type    The event type to check.
	 * @param string $event_version The version to check.
	 *
	 * @return bool
	 */
	public function matches( $event_type, $event_version ) {
		return $this->event_type === $event_type && $this->event_version === $event_version;
	}

	/**
	 * Get the Acquiring Partner integration class to use for this notification.
	 *
	 * @return AcquiringPartnerIntegration|null
	 */
	public function get_acquiring_partner_integration() {
		$ap_key       = PluginFeatures::get_acquiring_partner_key();
		$integrations = apply_filters( 'kec_acquiring_partner_integrations', array() );

		foreach ( $integrations as $integration ) {
			if ( $integration->get_key() === $ap_key ) {
				return $integration;
			}
		}

		return null;
	}

	/**
	 * Handle the notification callback.
	 *
	 * @param array $payload The payload from the notification.
	 *
	 * @return \WP_REST_Response|null
	 * @throws \WP_Exception If the notification cannot be handled.
	 */
	abstract public function handle_notification( $payload );

	/**
	 * Retrieve a WooCommerce order by its associated Klarna payment request ID.
	 *
	 * @param string $payment_request_id The Klarna payment request ID.
	 *
	 * @throws \WP_Exception If no valid order is found.
	 * @return \WC_Order|null The WooCommerce order if found, null otherwise.
	 */
	protected function get_wc_order_by_payment_request_id( $payment_request_id ) {
		$args = array(
			'limit'        => 1,
			'meta_key'     => '_kec_payment_request_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => $payment_request_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '=',
			'created_via'  => 'klarna_express_checkout',
			'date_created' => '>' . ( time() - ( \DAY_IN_SECONDS * 2 ) ),
		);

		$orders = wc_get_orders( $args );
		$order  = ! empty( $orders ) && is_array( $orders ) ? $orders[0] : null;

		$this->ensure_valid_order( $order, $payment_request_id );

		return $order;
	}

	/**
	 * Ensure the order returned is valid for the payment request id.
	 *
	 * @param \WC_Order|null $order              The order to validate.
	 * @param string         $payment_request_id The payment request id to validate against.
	 *
	 * @throws \WP_Exception If the order is not valid.
	 * @return void
	 */
	protected function ensure_valid_order( $order, $payment_request_id ) {
		if ( empty( $order ) ) {
			/* translators: %s: Klarna payment request ID. */
			$message = sprintf( __( 'No draft order found for payment request ID: %s', 'klarna-payments-for-woocommerce' ), $payment_request_id );
			throw new \WP_Exception( esc_html( $message ) );
		}

		$stored_payment_request_id = $order->get_meta( '_kec_payment_request_id' );
		if ( $stored_payment_request_id !== $payment_request_id ) {
			/* translators: %s: WooCommerce order ID. */
			$message = sprintf( __( 'Mismatch in payment request ID for order ID: %s', 'klarna-payments-for-woocommerce' ), $order->get_id() );
			throw new \WP_Exception( esc_html( $message ) );
		}
	}
}
