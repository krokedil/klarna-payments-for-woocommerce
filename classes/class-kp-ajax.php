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
				'kp_wc_place_order' => true,
				'kp_wc_auth_failed' => true,
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
		public static function place_order() {
			$kp = new WC_Gateway_Klarna_Payments();

			$kp->place_order();

			wp_send_json_success();
			wp_die();
		}

		/**
		 * Adds a order note on a failed auth call to KP.
		 *
		 * @return void
		 */
		public static function auth_failed() {
			// @codingStandardsIgnoreStart
			$order_id  = $_POST['order_id'];
			$show_form = $_POST['show_form'];
			$order     = wc_get_order( $order_id );
			// @codingStandardsIgnoreEnd

			if ( 'true' === $show_form ) {
				$order->add_order_note( __( 'Customer aborted purchase with klarna.', 'klarna-payments-for-woocommerce' ) );
			} else {
				$order->add_order_note( __( 'Payment rejected by klarna.', 'klarna-payments-for-woocommerce' ) );
			}

			wp_send_json_success();
			wp_die();
		}
	}
}
new KP_AJAX();
