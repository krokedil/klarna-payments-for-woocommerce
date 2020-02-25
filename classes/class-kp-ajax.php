<?php
/**
 * Klarna Payments AJAX class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KP_AJAX' ) ) {
	/**
	 * Klarna Payments AJAX class
	 */
	class KP_AJAX extends WC_AJAX {
		/**
		 * Hook in ajax handlers.
		 */
		public static function init() {
			self::add_ajax_events();
		}
		/**
		 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
		 */
		public static function add_ajax_events() {
			$ajax_events = array(
				'kp_wc_place_order'    => true,
				'kp_wc_auth_failed'    => true,
				'kp_wc_update_session' => true,
			);
			foreach ( $ajax_events as $ajax_event => $nopriv ) {
				add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				if ( $nopriv ) {
					add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
					// WC AJAX can be used for frontend ajax requests.
					add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				}
			}
		}

		/**
		 * Places the order with Klarna.
		 *
		 * @return void
		 */
		public static function kp_wc_place_order() {
			if ( ! isset( $_POST['order_id'] ) && ! isset( $_POST['auth_token'] ) ) { // phpcs:ignore
				wp_send_json_error( 'missing_data' );
				exit;
			}

			$order_id   = sanitize_key( $_POST['order_id'] ); // phpcs:ignore
			$auth_token = sanitize_key( $_POST['auth_token'] ); // phpcs:ignore

			$order    = wc_get_order( $order_id );
			$request  = new KP_Place_Order( $order_id );
			$response = $request->request( $auth_token );
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( kp_extract_error_message( $response ) );
				wp_die();
			}
			$fraud_status = $response['fraud_status'];
			switch ( $fraud_status ) {
				case 'ACCEPTED':
					$return_val = kp_process_accepted( $order, $response );
					kp_unset_session_values();
					wp_send_json_success( $response['redirect_url'] );
					wp_die();
					break;
				case 'PENDING':
					$return_val = kp_process_pending( $order, $response );
					kp_unset_session_values();
					wp_send_json_success( $response['redirect_url'] );
					wp_die();
					break;
				case 'REJECTED':
					$return_val = kp_process_rejected( $order, $response );
					kp_unset_session_values();
					wp_send_json_error( $order->get_cancel_order_url_raw() );
					kp_unset_session_values();
					wp_die();
					break;
				default:
					kp_unset_session_values();
					wp_send_json_error( $order->get_cancel_order_url_raw() );
					wp_die();
					break;
			}
			wp_send_json_success();
			wp_die();
		}

		/**
		 * Adds a order note on a failed auth call to KP.
		 *
		 * @return void
		 */
		public static function kp_wc_auth_failed() {
			if ( ! wp_verify_nonce( $_POST['nonce'], 'kp_wc_auth_failed' ) ) { // phpcs:ignore
				wp_send_json_error( 'bad_nonce' );
				exit;
			}
			// @codingStandardsIgnoreStart
			$order_id  = $_POST['order_id'];
			$show_form = $_POST['show_form'];
			$order     = wc_get_order( $order_id );
			// @codingStandardsIgnoreEnd
			if ( 'true' === $show_form ) {
				$order->add_order_note( __( 'Customer aborted purchase with Klarna.', 'klarna-payments-for-woocommerce' ) );
			} else {
				$order->add_order_note( __( 'Authorization rejected by Klarna.', 'klarna-payments-for-woocommerce' ) );
			}

			wp_send_json_success();
			wp_die();
		}

		/**
		 * Updates the Klarna Payments session.
		 *
		 * @return void
		 */
		public static function kp_wc_update_session() {
			if ( ! wp_verify_nonce( $_POST['nonce'], 'kp_wc_update_session' ) ) { // phpcs:ignore
				wp_send_json_error( 'bad_nonce' );
				exit;
			}
			// Need to calculate these here, because WooCommerce hasn't done it yet.
			WC()->cart->calculate_fees();
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();

			$kp_session = kp_maybe_create_session( WC()->customer->get_billing_country() );
			if ( ! is_array( $kp_session ) && ! empty( $kp_session ) ) {
				wp_send_json_error( $kp_session );
				wp_die();
			}
			wp_send_json_success( WC()->session->get( 'klarna_payments_client_token' ) );
			wp_die();
		}
	}
}
KP_AJAX::init();
