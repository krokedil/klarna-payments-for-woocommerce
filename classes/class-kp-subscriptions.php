<?php //phpcs:ignore
/**
 * Class for handling WC subscriptions.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Subscription' ) ) {

	/**
	 * Class for handling subscriptions.
	 */
	class KP_Subscription {

		private const GATEWAY_ID = 'klarna_payments';

		/**
		 * Register hooks.
		 */
		public function __construct() {
			add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, array( $this, 'process_scheduled_payment' ), 10, 2 );
			add_action( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID, array( $this, 'cancel_scheduled_payment' ) );
		}

		/**
		 * Process subscription renewal.
		 *
		 * @param float    $amount_to_charge
		 * @param WC_Order $renewal_order
		 * @return void
		 */
		public function process_scheduled_payment( $amount_to_charge, $renewal_order ) {
			$recurring_token = $this->get_recurring_tokens( $renewal_order->get_id() );
			$create_order    = new KP_Create_Recurring(
				array(
					'country'         => kp_get_klarna_country( $renewal_order ),
					'order_id'        => $renewal_order->get_id(),
					'recurring_token' => $recurring_token,
				)
			);
			$response        = $create_order->request();
			if ( ! is_wp_error( $response ) ) {
				$klarna_order_id = $response['order_id'];
				$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Klarna. Klarna order id: %s', 'klarna-payments-for-woocommerce' ), $klarna_order_id ) );
				kp_process_auth_or_callback( $renewal_order, $response );
			} else {
				$error_message = $response->get_error_message();
				// Translators: Error message.
				$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Klarna. Message: %1$s', 'klarna-payments-for-woocommerce' ), $error_message ) );
			}

			$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
			foreach ( $subscriptions as $subscription ) {
				if ( isset( $klarna_order_id ) ) {
					$subscription->payment_complete( $klarna_order_id );
				} else {
					$subscription->payment_failed();
				}

				// Save to the subscription.
				self::save_recurring_token( $subscription->get_id(), $recurring_token );
			}

			// Save to the WC order.
			self::save_recurring_token( $renewal_order->get_id(), $recurring_token );

		}

		public function cancel_scheduled_payment( $subscription ) {
			$recurring_token = $this->get_recurring_tokens( $subscription->get_id() );
			$cancel_order    = new KP_Cancel_Recurring(
				array(
					'country'         => kp_get_klarna_country( $subscription ),
					'recurring_token' => $recurring_token,
				)
			);

			$response = $cancel_order->request();
			if ( ! is_wp_error( $response ) ) {
				$subscription->add_order_note( __( 'Subscription cancelled with Klarna Payments.', 'klarna-payments-for-woocommerce' ) );
			} else {
				$error_message = $response->get_error_message();
				// Translators: Error message.
				$subscription->add_order_note( sprintf( __( 'Subscription cancellation failed with Klarna Payments. Message: %1$s', 'klarna-payments-for-woocommerce' ), $error_message ) );
			}

		}

		/**
		 * Save the payment and recurring token to the order if it has a subscription.
		 *
		 * @param string $order_id The WooCommerce order id.
		 * @param string $recurring_token The recurring token ("customer token").
		 * @return void
		 */
		public static function save_recurring_token( $order_id, $recurring_token ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_kp_recurring_token', $recurring_token );
			$order->save();
		}

		/**
		 * Retrieve the necessary tokens required for subscriptions (unattended) payments.
		 *
		 * @param  int $order_id The WooCommerce order id.
		 * @return string The recurring token. If none is found, an empty string is returned.
		 */
		public static function get_recurring_tokens( $order_id ) {
			$order           = wc_get_order( $order_id );
			$recurring_token = $order->get_meta( '_kp_recurring_token' );

			if ( empty( $recurring_token ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
				foreach ( $subscriptions as $subscription ) {
					$parent_order    = $subscription->get_parent();
					$recurring_token = $parent_order->get_meta( '_kp_recurring_token' );

					if ( ! empty( $recurring_token ) ) {
						break;
					}
				}
			}

			return $recurring_token;
		}

		/**
		 * Get a subscription's parent order.
		 *
		 * @param int $order_id The WooCommerce order id.
		 * @return WC_Order|false The parent order or false if none is found.
		 */
		public static function get_parent_order( $order_id ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			foreach ( $subscriptions as $subscription ) {
				$parent_order = $subscription->get_parent();
				return $parent_order;
			}

			return false;
		}
	}

	new KP_Subscription();
}
