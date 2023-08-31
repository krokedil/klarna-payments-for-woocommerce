<?php
/**
 * Class for handling callbacks.
 *
 * @package WC_Klarna_Payments/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handling callbacks.
 */
class KP_Callbacks {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_kp_wc_authorization', array( $this, 'authorization_cb' ) );
		add_action( 'kp_wc_authorization', array( $this, 'kp_wc_authorization' ), 10, 2 );
		add_action( 'init', array( $this, 'process_hpp_redirect' ), 9999 );
	}

	/**
	 * Process the authorization callback from Klarna.
	 *
	 * @return void
	 */
	public function authorization_cb() {
		$data = json_decode( file_get_contents( 'php://input' ), true );

		// If the data is empty, return.
		if ( empty( $data ) ) {
			return;
		}

		as_schedule_single_action( time() + 120, 'kp_wc_authorization', array( $data ) );
	}

	/**
	 * Handle the authorization callback from Klarna. Maybe complete a order.
	 *
	 * @param array $data The data for the auth callback.
	 * @return void
	 */
	public function kp_wc_authorization( $data ) {
		$order = wc_get_orders(
			array(
				'meta_key'   => '_kp_session_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $data['session_id'],
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$order = reset( $order );

		// Verify that the meta data is correct with what we just searched for.
		if ( $order && $order->get_meta( '_kp_session_id', true ) !== $data['session_id'] ) {
			$order = null;
		}

		if ( empty( $order ) ) {
			return;
		}

		$auth_token = $data['authorization_token'];
		$country    = $order->get_billing_country();

		// Dont do anything if the order has been processed.
		if ( $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
			return;
		}

		// For free or trial subscriptions, authorization can be safely ignored as we do not need to act upon it
		// since no Klarna order is associated with the purchase, only a Klarna customer token.
		if ( KP_Subscription::order_has_subscription( $order ) && 0.0 === floatval( $order->get_total() ) ) {
			$order->payment_complete();
			return;
		}

		$response = KP_WC()->api->place_order( $country, $auth_token, $order->get_id() );
		if ( is_wp_error( $response ) ) {
			/**
			 * WordPress error handling.
			 *
			 * @var WP_Error $response The error response.
			 */
			$order->add_order_note( __( 'Failed to complete the order during the authentication callback.', 'klarna-payments-for-woocommerce' ) . $response->get_error_message() );
			return;
		}

		$fraud_status = $response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $response );
				$order->add_order_note( __( 'The Klarna order was successfully completed by the authorization callback', 'klarna-payments-for-woocommerce' ) );
				break;
			case 'PENDING':
				kp_process_pending( $order, $response );
				$order->add_order_note( __( 'The Klarna order is pending approval by Klarna', 'klarna-payments-for-woocommerce' ) );
				break;
			case 'REJECTED':
				kp_process_rejected( $order, $response );
				$order->add_order_note( __( 'The Klarna order was rejected during the authorization by Klarna', 'klarna-payments-for-woocommerce' ) );
				break;
			default:
				$order->add_order_note( __( 'Failed to complete the order during the authentication callback.', 'klarna-payments-for-woocommerce' ) );
				break;
		}

	}

	/**
	 * Handle the hpp redirect from Klarna.
	 *
	 * @return void
	 */
	public function process_hpp_redirect() {
		$session_id = filter_input( INPUT_GET, 'sid', FILTER_SANITIZE_SPECIAL_CHARS );
		$auth_token = filter_input( INPUT_GET, 'authorization_token', FILTER_SANITIZE_SPECIAL_CHARS );
		$order_key  = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS );

		// Return if anything is null.
		if ( null === $session_id || null === $auth_token || null === $order_key ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order    = wc_get_order( $order_id );
		$country  = $order->get_billing_country();

		// Trigger place order on the auth token with KP.
		$response = KP_WC()->api->place_order( $country, $auth_token, $order_id );
		if ( is_wp_error( $response ) ) {
			/**
			 * WordPress error handling.
			 *
			 * @var WP_Error $response The error response.
			 */
			$order->add_order_note( __( 'Failed to complete the order when returning from the hosted payment page.', 'klarna-payments-for-woocommerce' ) . $response->get_error_message() );
			return;
		}

		$fraud_status = $response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $response );
				$order->add_order_note( __( 'The Klarna order was successfully completed', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
			case 'PENDING':
				kp_process_pending( $order, $response );
				$order->add_order_note( __( 'The Klarna order is pending approval by Klarna', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
			case 'REJECTED':
				kp_process_rejected( $order, $response );
				$order->add_order_note( __( 'The Klarna order was rejected by Klarna', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
			default:
				$order->add_order_note( __( 'Failed to complete the order when returning from the hosted payment page.', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
		}
	}
}
new KP_Callbacks();
