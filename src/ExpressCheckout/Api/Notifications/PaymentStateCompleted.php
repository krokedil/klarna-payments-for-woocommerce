<?php

namespace Krokedil\Klarna\ExpressCheckout\Api\Notifications;

use Krokedil\Klarna\ExpressCheckout\Logger;
use Krokedil\Klarna\PluginFeatures;

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
		$payment_request_id = $payload['payment_request_id'] ?? null;

		if ( 'COMPLETED' !== ( $payload['state'] ?? '' ) ) {
			Logger::log(
				'Ignored a completed notification whose state is not COMPLETED',
				array(
					'payment_request_id' => $payment_request_id,
					'state'              => $payload['state'] ?? '',
				)
			);
			return;
		}

		$interoperability_token = $payload['klarna_network_session_token'] ?? '';

		if ( ! $payment_request_id ) {
			throw new \WP_Exception( 'Missing required fields in the payload.' );
		}

		$order = $this->get_wc_order_by_payment_request_id( $payment_request_id );

		$order->add_order_note(
			sprintf(
				/* translators: [merchant-facing]. %s: Klarna payment request ID. */
				__( 'Klarna Express Checkout: payment completed notification received (payment request %s).', 'klarna-payments-for-woocommerce' ),
				$payment_request_id
			)
		);

		$this->set_address( $order, $payload );
		// The interoperability data is built from the stored order, so the address has to be saved first.
		$order->save();

		$redirect_url           = $order->get_checkout_order_received_url();
		$interoperability_data  = $this->get_interoperability_data( $order );
		$ap_partner_integration = $this->get_acquiring_partner_integration();
		if ( $ap_partner_integration ) {
			$redirect_url = $ap_partner_integration->process_order_state(
				$order,
				$interoperability_token,
				$interoperability_data,
				$payload['state'],
				$payload
			);
		} else {
			$ap_key = PluginFeatures::get_acquiring_partner_key();

			Logger::warning( 'No acquiring partner integration handled the completed payment, left to kec_process_order', array( 'acquiring_partner_key' => $ap_key ), $order );
			$order->add_order_note(
				/* translators: [merchant-facing]. */
				__( 'Klarna Express Checkout: the customer completed the payment, but no active payment provider integration could update this order automatically. If the order stays pending, verify the payment in your Klarna or payment provider portal before processing it.', 'klarna-payments-for-woocommerce' )
			);

			/**
			 * Triggers when a completed Klarna Express Checkout payment notification is processed and no acquiring partner integration handles it.
			 *
			 * @param \WC_Order $order                  The WooCommerce order object.
			 * @param string    $interoperability_token The Klarna interoperability token.
			 * @param array     $interoperability_data  The order lines and shipping for the acquiring partner. Empty when shopping data is not sent.
			 * @param string    $state                  The payment state reported by Klarna.
			 * @param array     $payload                The payload data from Klarna.
			 */
			do_action( 'kec_process_order', $order, $interoperability_token, $interoperability_data, $payload['state'], $payload );
		}

		$order->update_meta_data( '_kec_redirect_url', $redirect_url );
		$order->save();

		Logger::log(
			'Completed payment processed',
			array(
				'order_status' => $order->get_status(),
				'has_redirect' => ! empty( $redirect_url ),
			),
			$order
		);

		return null;
	}

	/**
	 * Get the interoperability data for the order, the same order lines and shipping the
	 * checkout keeps in the customer's session, which a webhook does not have.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return array
	 */
	protected function get_interoperability_data( $order ) {
		if ( ! \KP_Interoperability_Token::should_send_data() ) {
			return array();
		}

		$settings      = get_option( 'woocommerce_klarna_payments_settings', array() );
		$customer_type = $settings['customer_type'] ?? 'b2c';

		return ( new \KP_Order_Data( $customer_type, $order->get_id() ) )->get_klarna_order_lines_interoperability();
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
