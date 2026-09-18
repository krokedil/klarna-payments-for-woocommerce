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

		status_header( $this->handle_authorization_payload( is_array( $data ) ? $data : array() ) );
	}

	/**
	 * Decide what to do with an authorization callback payload.
	 *
	 * @param array $data The decoded callback payload.
	 * @return int The HTTP status code to respond with.
	 */
	public function handle_authorization_payload( $data ) {
		$session_id = isset( $data['session_id'] ) && is_scalar( $data['session_id'] ) ? sanitize_text_field( (string) $data['session_id'] ) : '';
		$auth_token = isset( $data['authorization_token'] ) && is_scalar( $data['authorization_token'] ) ? sanitize_text_field( (string) $data['authorization_token'] ) : '';

		if ( empty( $session_id ) || empty( $auth_token ) ) {
			self::log_authorization( 'Declined a callback that carried no session id or no authorization token.' );
			return 400;
		}

		$rate_limit_key = 'kp_authorization_' . md5( $session_id );
		if ( WC_Rate_Limiter::retried_too_soon( $rate_limit_key ) ) {
			self::log_authorization( sprintf( 'Throttled a repeated callback for session %s.', $session_id ) );
			return 429;
		}

		$order = self::get_order_by_session_id( $session_id );
		if ( empty( $order ) ) {
			// Answered as a success so that Klarna does not retry a message this store can never act on.
			self::log_authorization( sprintf( 'Declined a callback for session %s: no order in this store carries that session.', $session_id ) );
			return 200;
		}

		// The purchase already completed through the normal checkout flow, so there is nothing to place.
		if ( ! empty( $order->get_date_paid() ) ) {
			self::log_authorization( sprintf( 'Declined a callback for session %s: order %s is already paid.', $session_id, $order->get_id() ) );
			return 200;
		}

		/**
		 * Filters how long, in seconds, further authorization callbacks for a Klarna session are throttled for.
		 *
		 * @param int    $seconds    The throttle window. Defaults to 120, matching the delay on the queued job.
		 * @param string $session_id The Klarna session the callback refers to.
		 */
		$rate_limit_window = apply_filters( 'kp_authorization_callback_rate_limit', 120, $session_id );

		WC_Rate_Limiter::set_rate_limit( $rate_limit_key, $rate_limit_window );

		as_schedule_single_action(
			time() + 120,
			'kp_wc_authorization',
			array(
				array(
					'session_id'          => $session_id,
					'authorization_token' => $auth_token,
				),
			),
			'klarna_authorization'
		);

		return 200;
	}

	/**
	 * Get the order that carries a Klarna session id.
	 *
	 * @param string $session_id The Klarna session id.
	 * @return WC_Order|null The order, or null if no order in this store carries the session id.
	 */
	public static function get_order_by_session_id( $session_id ) {
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_kp_session_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $session_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$order = reset( $orders );

		// Verify that the meta data is correct with what we just searched for.
		if ( empty( $order ) || $order->get_meta( '_kp_session_id', true ) !== $session_id ) {
			return null;
		}

		return $order;
	}

	/**
	 * Log a decision made about an authorization callback.
	 *
	 * @param string $message The message to log.
	 * @return void
	 */
	private static function log_authorization( $message ) {
		KP_Logger::log( sprintf( '[AUTHORIZATION CALLBACK]: %s', $message ) );
	}

	/**
	 * Handle the authorization callback from Klarna. Maybe complete a order.
	 *
	 * @param array $data The data for the auth callback.
	 * @return void
	 */
	public function kp_wc_authorization( $data ) {
		$session_id = isset( $data['session_id'] ) && is_scalar( $data['session_id'] ) ? (string) $data['session_id'] : '';
		$auth_token = isset( $data['authorization_token'] ) && is_scalar( $data['authorization_token'] ) ? (string) $data['authorization_token'] : '';

		if ( empty( $session_id ) || empty( $auth_token ) ) {
			return;
		}

		$order = self::get_order_by_session_id( $session_id );

		if ( empty( $order ) ) {
			return;
		}

		$country = kp_get_klarna_country( $order );

		// Check if the PURCHASE has already been completed by the customer.
		if ( ! empty( $order->get_date_paid() ) ) {
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
			/* translators: [merchant-facing]. */
			$order->add_order_note( __( 'Failed to complete the order during the authentication callback.', 'klarna-payments-for-woocommerce' ) . $response->get_error_message() );
			return;
		}

		$fraud_status = $response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $response );
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'The Klarna order was successfully completed by the authorization callback', 'klarna-payments-for-woocommerce' ) );
				break;
			case 'PENDING':
				kp_process_pending( $order, $response );
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'The Klarna order is pending approval by Klarna', 'klarna-payments-for-woocommerce' ) );
				break;
			case 'REJECTED':
				kp_process_rejected( $order, $response );
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'The Klarna order was rejected during the authorization by Klarna', 'klarna-payments-for-woocommerce' ) );
				break;
			default:
				/* translators: [merchant-facing]. */
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
		if ( ! isset( $session_id, $auth_token, $order_key ) ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		$order    = wc_get_order( $order_id );
		$country  = $order->get_billing_country();

		// Check if the order has already been processed.
		if ( ! empty( $order->get_date_paid() ) ) {
			return;
		}

		$recurring_token = false;
		if ( KP_Subscription::order_has_subscription( $order ) ) {
			$recurring_token = KP_WC()->subscription->create_customer_token( $order, $auth_token );
			if ( is_wp_error( $recurring_token ) ) {
				if ( 'FREE_TRIAL' === $recurring_token->get_error_code() ) {
					kp_unset_session_values();
				} else {
					/* translators: [merchant-facing]. */
					$order->add_order_note( __( 'Failed to create a recurring token when returning from the hosted payment page.', 'klarna-payments-for-woocommerce' ) . $recurring_token->get_error_message() );

					KP_Logger::log( sprintf( '[AJAX]: Order ID: %s. Auth token: %s. %s', $order->get_id(), $auth_token, $recurring_token->get_error_message() ) );
				}

				// If the intent is only 'tokenize', we should not proceed further as 'place_order' only allows 'buy_and_tokenize' intent.
				// We may also return here since a call to 'place_order' will fail if the customer token fails to be created or if the intent is only 'tokenize'.
				return;
			}
		}

		// Trigger place order on the auth token with KP.
		$response = KP_WC()->api->place_order( $country, $auth_token, $order_id );
		if ( is_wp_error( $response ) ) {
			/**
			 * WordPress error handling.
			 *
			 * @var WP_Error $response The error response.
			 */
			/* translators: [merchant-facing]. */
			$order->add_order_note( __( 'Failed to complete the order when returning from the hosted payment page.', 'klarna-payments-for-woocommerce' ) . $response->get_error_message() );
			return;
		}

		$fraud_status = $response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $response );
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'The Klarna order was successfully completed', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
			case 'PENDING':
				kp_process_pending( $order, $response );
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'The Klarna order is pending approval by Klarna', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
			case 'REJECTED':
				kp_process_rejected( $order, $response );
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'The Klarna order was rejected by Klarna', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
			default:
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'Failed to complete the order when returning from the hosted payment page.', 'klarna-payments-for-woocommerce' ) );
				kp_unset_session_values();
				break;
		}
	}
}
new KP_Callbacks();
