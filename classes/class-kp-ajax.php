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
		 * The most characters of a single frontend message that are logged. Longer messages are truncated.
		 */
		const LOG_JS_MAX_LENGTH = 1024;

		/**
		 * The most frontend messages logged for one session within LOG_JS_WINDOW seconds.
		 */
		const LOG_JS_MAX_MESSAGES = 20;

		/**
		 * The window, in seconds, that LOG_JS_MAX_MESSAGES is counted over.
		 */
		const LOG_JS_WINDOW = 300;

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
				'kp_wc_get_unavailable_features'   => false, // Settings screen only. Never registered for logged out requests.
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
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'Customer aborted purchase with Klarna.', 'klarna-payments-for-woocommerce' ) );
			} else {
				/* translators: [merchant-facing]. */
				$order->add_order_note( __( 'Authorization rejected by Klarna.', 'klarna-payments-for-woocommerce' ) );
			}

			/**
			 * Triggers after an order note has been added when the Klarna authorization modal is closed or rejected.
			 *
			 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#handle-the-klarna-modal-closed-event
			 * @param WC_Order $order The WooCommerce order object.
			 * @param bool     $show_form Whether the checkout form should be shown again to the customer.
			 */
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

			if ( self::log_js_budget_spent( $klarna_session_id ) ) {
				wp_send_json_success();
			}

			$posted_message = self::truncate_log_js_message( (string) filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

			KP_Logger::log( "Frontend JS $klarna_session_id: $posted_message" );
			wp_send_json_success();
		}

		/**
		 * Cuts a frontend message down to the size the log accepts.
		 *
		 * @param string $message The message reported by the checkout page.
		 * @return string
		 */
		public static function truncate_log_js_message( $message ) {
			if ( mb_strlen( $message ) <= self::LOG_JS_MAX_LENGTH ) {
				return $message;
			}

			return mb_substr( $message, 0, self::LOG_JS_MAX_LENGTH ) . ' [truncated]';
		}

		/**
		 * Whether this session has already used up its frontend logging budget.
		 *
		 * @param string|null $klarna_session_id The Klarna session the message belongs to.
		 * @return bool
		 */
		public static function log_js_budget_spent( $klarna_session_id ) {
			$session_id = ! empty( $klarna_session_id ) ? $klarna_session_id : WC_Geolocation::get_ip_address();

			$key    = 'kp_log_js_' . md5( (string) $session_id );
			$logged = (int) get_transient( $key );

			if ( $logged >= self::LOG_JS_MAX_MESSAGES ) {
				if ( self::LOG_JS_MAX_MESSAGES === $logged ) {
					KP_Logger::log( sprintf( 'Frontend JS %s: further messages dropped, more than %d in %d seconds.', $session_id, self::LOG_JS_MAX_MESSAGES, self::LOG_JS_WINDOW ) );
					set_transient( $key, $logged + 1, self::LOG_JS_WINDOW );
				}

				return true;
			}

			set_transient( $key, $logged + 1, self::LOG_JS_WINDOW );

			return false;
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

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'forbidden' );
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
