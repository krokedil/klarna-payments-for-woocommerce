<?php

namespace Krokedil\Klarna\ExpressCheckout\Api\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Notification handler for the payment.request.state-change.completed event.
 */
class PaymentStateCompleted extends Handler {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'payment.request.state-change.completed';

	/**
	 * The version for the notification.
	 *
	 * @var string
	 */
	protected $event_version = 'v2';

	/**
	 * Handle the notification for the payment completed event.
	 *
	 * @param array $payload The payload from the notification.
	 *
	 * @return \WP_REST_Response|null
	 * @throws \WP_Exception If the notification cannot be handled.
	 */
	public function handle_notification( $payload ) {
		if ( 'COMPLETED' !== $payload['state'] ) {
			return;
		}

		$payment_request_id     = $payload['payment_request_id'] ?? null;
		$interoperability_token = $payload['klarna_network_session_token'] ?? '';

		if ( ! $payment_request_id ) {
			throw new \WP_Exception( 'Missing required fields in the payload.' );
		}

		$order = $this->get_wc_order_by_payment_request_id( $payment_request_id );

		$this->set_address( $order, $payload );

		$redirect_url           = $order->get_checkout_order_received_url();
		$ap_partner_integration = $this->get_acquiring_partner_integration();
		if ( $ap_partner_integration ) {
			$redirect_url = $ap_partner_integration->process_order_state(
				$order,
				$interoperability_token,
				array(),
				$payload['state'],
				$payload
			);
		} else {
			/**
			 * Triggers when a completed Klarna Express Checkout payment notification is processed and no acquiring partner integration handles it.
			 *
			 * @param \WC_Order $order                  The WooCommerce order object.
			 * @param string    $interoperability_token The Klarna interoperability token.
			 * @param array     $interoperability_data  The interoperability data. Empty for this event.
			 * @param string    $state                  The payment state reported by Klarna.
			 * @param array     $payload                The payload data from Klarna.
			 */
			do_action( 'kec_process_order', $order, $interoperability_token, array(), $payload['state'], $payload );
		}

		$order->update_meta_data( '_kec_redirect_url', $redirect_url );
		$order->save();

		return null;
	}

	/**
	 * Set the customer address to the order from the payload data.
	 *
	 * @param \WC_Order $order   The WooCommerce order to update. Passed by reference.
	 * @param array     $payload The payload from the notification.
	 *
	 * @return self The current instance for method chaining.
	 */
	protected function set_address( &$order, $payload ) {
		$billing_customer  = $payload['klarna_customer']['customer_profile'] ?? array();
		$billing_address   = $payload['klarna_customer']['customer_profile']['address'] ?? array();
		$shipping_customer = $payload['shipping']['recipient'] ?? array();
		$shipping_address  = $payload['shipping']['address'] ?? array();

		$this->set_address_field( $order, $billing_customer['given_name'] ?? '', 'first_name', 'billing' )
			->set_address_field( $order, $billing_customer['family_name'] ?? '', 'last_name', 'billing' )
			->set_address_field( $order, $billing_customer['email'] ?? '', 'email', 'billing' )
			->set_address_field( $order, $billing_customer['phone'] ?? '', 'phone', 'billing' )
			->set_address_field( $order, $billing_address['street_address'] ?? '', 'address_1', 'billing' )
			->set_address_field( $order, $billing_address['street_address2'] ?? '', 'address_2', 'billing' )
			->set_address_field( $order, $billing_address['postal_code'] ?? '', 'postcode', 'billing' )
			->set_address_field( $order, $billing_address['city'] ?? '', 'city', 'billing' )
			->set_address_field( $order, $billing_address['region'] ?? '', 'state', 'billing' )
			->set_address_field( $order, $billing_address['country'] ?? '', 'country', 'billing' )
			->set_address_field( $order, $shipping_customer['given_name'] ?? '', 'first_name', 'shipping' )
			->set_address_field( $order, $shipping_customer['family_name'] ?? '', 'last_name', 'shipping' )
			->set_address_field( $order, $shipping_customer['email'] ?? '', 'email', 'shipping' )
			->set_address_field( $order, $shipping_customer['phone'] ?? '', 'phone', 'shipping' )
			->set_address_field( $order, $shipping_address['street_address'] ?? '', 'address_1', 'shipping' )
			->set_address_field( $order, $shipping_address['street_address2'] ?? '', 'address_2', 'shipping' )
			->set_address_field( $order, $shipping_address['postal_code'] ?? '', 'postcode', 'shipping' )
			->set_address_field( $order, $shipping_address['city'] ?? '', 'city', 'shipping' )
			->set_address_field( $order, $shipping_address['region'] ?? '', 'state', 'shipping' )
			->set_address_field( $order, $shipping_address['country'] ?? '', 'country', 'shipping' );

		return $this;
	}

	/**
	 * Set a specific address field to the order.
	 *
	 * @param \WC_Order $order        The WooCommerce order to update. Passed by reference.
	 * @param mixed     $value        The value to set.
	 * @param string    $field        The order field to update.
	 * @param string    $address_type The type of address ('billing' or 'shipping').
	 *
	 * @return self
	 */
	protected function set_address_field( &$order, $value, $field, $address_type ) {
		if ( ! empty( $value ) ) {
			$method = "set_{$address_type}_{$field}";
			if ( method_exists( $order, $method ) ) {
				$order->$method( $value );
			} else {
				$order->update_meta_data( "{$address_type}_{$field}", $value );
			}
		}

		return $this;
	}
}
