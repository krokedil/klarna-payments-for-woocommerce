<?php
/**
 * Klarna Payments AJAX class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

use Krokedil\Klarna\PluginFeatures;

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
				'kp_wc_place_order'                => true,
				'kp_wc_auth_failed'                => true,
				'kp_wc_log_js'                     => true,
				'kp_wc_express_button'             => true,
				'kp_wc_get_unavailable_features'   => true,
				'kp_wc_set_interoperability_token' => true,
				'kp_wc_get_interoperability_data'  => true,
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
			$order_key  = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_SPECIAL_CHARS );
			$order_id   = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
			$auth_token = filter_input( INPUT_POST, 'auth_token', FILTER_SANITIZE_SPECIAL_CHARS );

			if ( empty( $order_key ) || empty( $order_id ) || empty( $auth_token ) ) {
				wp_send_json_error( 'missing params' );
			}

			$order = wc_get_order( $order_id );
			if ( empty( $order ) ) {
				wp_send_json_error( 'no order found' );
			}

			if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
				wp_send_json_error( 'order id and key do not match order' );
			}

			// Prevent further processing if the order has already been processed once.
			if ( ! empty( $order->get_date_paid() ) ) {
				wp_send_json_success( $order->get_checkout_order_received_url() );
			}

			$recurring_token = false;
			if ( KP_Subscription::order_has_subscription( $order ) ) {
				$recurring_token = KP_WC()->subscription->create_customer_token( $order, $auth_token );
				if ( is_wp_error( $recurring_token ) ) {
					if ( 'FREE_TRIAL' === $recurring_token->get_error_code() ) {
						kp_unset_session_values();

						// If the intent is only 'tokenize', we should not proceed further as 'place_order' only allows 'buy_and_tokenize' intent.
						wp_send_json_success( $order->get_checkout_order_received_url() );
					} else {
						KP_Logger::log( sprintf( '[AJAX]: Order ID: %s. Auth token: %s. %s', $order->get_id(), $auth_token, $recurring_token->get_error_message() ) );
						wp_send_json_error( 'customer_token_failed' );
					}
				}
			}

			$response = KP_WC()->api->place_order( kp_get_klarna_country( $order ), $auth_token, $order_id );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( kp_extract_error_message( $response ) );
			}

			$fraud_status = $response['fraud_status'];
			switch ( $fraud_status ) {
				case 'ACCEPTED':
					kp_process_accepted( $order, $response, $recurring_token );
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

			$order_key = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_SPECIAL_CHARS );
			$order_id  = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
			$show_form = filter_input( INPUT_POST, 'show_form', FILTER_SANITIZE_SPECIAL_CHARS );

			if ( empty( $_POST['order_key'] ) || empty( $_POST['order_id'] ) || empty( $_POST['show_form'] ) ) {
				wp_send_json_error( 'missing params' );
			}

			$order = wc_get_order( $order_id );
			if ( empty( $order ) ) {
				wp_send_json_error( 'no order found' );
			}

			if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
				wp_send_json_error( 'order id and key do not match order' );
			}

			if ( ! in_array( $show_form, array( 'false', 'true' ), true ) ) {
				wp_send_json_error( 'unexpected form data' );
			}

			$show_form = 'true' === $show_form ? true : false;
			if ( $show_form ) {
				$order->add_order_note( __( 'Customer aborted purchase with Klarna.', 'klarna-payments-for-woocommerce' ) );
			} else {
				$order->add_order_note( __( 'Authorization rejected by Klarna.', 'klarna-payments-for-woocommerce' ) );
			}

			do_action( 'kp_modal_closed', $order, $show_form );
			wp_send_json_success();
		}

		/**
		 * Logs messages from the JavaScript to the server log.
		 *
		 * @return void
		 */
		public static function kp_wc_log_js() {
			check_ajax_referer( 'kp_wc_log_js', 'nonce' );
			$klarna_session_id = KP_WC()->session->get_klarna_session_id();

			// Get the content size of the request.
			$post_size = (int) $_SERVER['CONTENT_LENGTH'] ?? 0;

			// If the post data is to long, log a error message and return.
			if ( $post_size > 1024 ) {
				KP_logger::log( "Frontend JS $klarna_session_id: message to long and can't be logged." );
				wp_send_json_success(); // Return success to not stop anything in the frontend if this happens.
			}

			$posted_message = filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$message        = "Frontend JS $klarna_session_id: $posted_message";
			KP_logger::log( $message );
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

			$customer = filter_input( INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY );

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

		/**
		 * Get the unavailable features from the Klarna plugins API.
		 *
		 * @return void
		 */
		public static function kp_wc_get_unavailable_features() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';

			if ( ! wp_verify_nonce( $nonce, 'kp_wc_get_unavailable_features' ) ) {
				wp_send_json_error( 'bad_nonce' );
			}

			$country_credentials = filter_input( INPUT_POST, 'country_credentials', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY );

			if ( ! $country_credentials ) {
				wp_send_json_error( 'Missing credentials.' );
			}

			$sections_to_hide = array();
			foreach ( $country_credentials as $credentials ) {
				$features         = KP_WC()->plugin_features()->process_api_credentials( $credentials );
				$sections_to_hide = PluginFeatures::get_sections_to_hide( $features );
			}

			wp_send_json_success( $sections_to_hide );
		}

		/**
		 * Set the interoperability token in the session for the current user.
		 *
		 * @return void
		 */
		public static function kp_wc_set_interoperability_token() {
			// Verify the nonce.
			check_ajax_referer( 'kp_wc_set_interoperability_token', 'nonce' );
			$token = filter_input( INPUT_POST, 'token', FILTER_SANITIZE_SPECIAL_CHARS );

			if ( empty( $token ) ) {
				wp_send_json_error( 'missing token' );
			}

			KP_Interoperability_Token::set_token( $token );

			wp_send_json_success();
		}

		/**
		 * Set the interoperability data in the session for the current user.
		 *
		 * @return void
		 */
		public static function kp_wc_get_interoperability_data() {
			// Verify the nonce.
			check_ajax_referer( 'kp_wc_get_interoperability_data', 'nonce' );

			$interoperability_data = KP_Interoperability_Token::get_data();

			wp_send_json_success( $interoperability_data );
		}
	}
}
KP_AJAX::init();
