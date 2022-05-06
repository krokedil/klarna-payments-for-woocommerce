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
				'kp_wc_log_js'         => true,
				'kp_wc_express_button' => true,
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
			$order_id   = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_STRING );
			$auth_token = filter_input( INPUT_POST, 'auth_token', FILTER_SANITIZE_STRING );

			if ( empty( $order_id ) || empty( $auth_token ) ) {
				wp_send_json_error( 'missing_data' );
			}

			$order    = wc_get_order( $order_id );
			$request  = new KP_Place_Order( $order_id );
			$response = $request->request( $auth_token );
			if ( is_wp_error( $response ) ) {
				wc_add_notice( kp_extract_error_message( $response ), 'error' );
				wp_send_json_error( kp_extract_error_message( $response ) );
			}
			$fraud_status = $response['fraud_status'];
			switch ( $fraud_status ) {
				case 'ACCEPTED':
					kp_process_accepted( $order, $response );
					kp_unset_session_values();
					wp_send_json_success( $response['redirect_url'] );
					break;
				case 'PENDING':
					kp_process_pending( $order, $response );
					kp_unset_session_values();
					wp_send_json_success( $response['redirect_url'] );
					break;
				case 'REJECTED':
					kp_process_rejected( $order, $response );
					kp_unset_session_values();
					wp_send_json_error( $order->get_cancel_order_url_raw() );
					break;
				default:
					kp_unset_session_values();
					wp_send_json_error( $order->get_cancel_order_url_raw() );
					break;
			}
			wp_send_json_success();
		}

		/**
		 * Adds a order note on a failed auth call to KP.
		 *
		 * @return void
		 */
		public static function kp_wc_auth_failed() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'kp_wc_auth_failed' ) ) {
				wp_send_json_error( 'bad_nonce' );
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
		}

		/**
		 * Logs messages from the JavaScript to the server log.
		 *
		 * @return void
		 */
		public static function kp_wc_log_js() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
			if ( ! wp_verify_nonce( $nonce, 'kp_wc_log_js' ) ) {
				wp_send_json_error( 'bad_nonce' );
			}
			$posted_message    = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
			$klarna_session_id = WC()->session->get( 'klarna_payments_session_id' );
			$message           = "Frontend JS $klarna_session_id: $posted_message";
			KP_Logger::log( $message );
			wp_send_json_success();
		}

		/**
		 * Populate the customer object with data received from Klarna.
		 *
		 * @return void
		 */
		public static function kp_wc_express_button() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
			if ( ! wp_verify_nonce( $nonce, 'kp_wc_express_button' ) ) {
				wp_send_json_error( 'bad_nonce' );
			}

			$customer = filter_var_array( $_POST['message'], FILTER_SANITIZE_STRING );
			if ( ! $customer ) {
				wp_send_json_error( 'Failed to filter the message.', $customer );
			}

			WC()->customer->set_billing_first_name( $customer['first_name'] );
			WC()->customer->set_billing_last_name( $customer['last_name'] );
			WC()->customer->set_billing_email( $customer['email'] );
			WC()->customer->set_billing_phone( $customer['phone'] );
			WC()->customer->set_billing_address( $customer['address']['street_address'] );
			WC()->customer->set_billing_address_2( $customer['address']['street_address2'] );
			WC()->customer->set_billing_postcode( $customer['address']['postal_code'] );
			WC()->customer->set_billing_city( $customer['address']['city'] );
			WC()->customer->set_billing_state( $customer['address']['region'] );
			WC()->customer->set_billing_country( $customer['address']['country'] );

			WC()->customer->set_shipping_first_name( $customer['first_name'] );
			WC()->customer->set_shipping_last_name( $customer['last_name'] );
			WC()->customer->set_shipping_phone( $customer['phone'] );
			WC()->customer->set_shipping_address( $customer['address']['street_address'] );
			WC()->customer->set_shipping_address_2( $customer['address']['street_address2'] );
			WC()->customer->set_shipping_postcode( $customer['address']['postal_code'] );
			WC()->customer->set_shipping_city( $customer['address']['city'] );
			WC()->customer->set_shipping_state( $customer['address']['region'] );
			WC()->customer->set_shipping_country( $customer['address']['country'] );

			WC()->session->set( 'chosen_payment_method', 'klarna_payments' );

			wp_send_json_success( wc_get_checkout_url() );
		}
	}
}
KP_AJAX::init();
