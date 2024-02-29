<?php
/**
 * Class for handling supplementary data sent to Klarna throughout the checkout process.
 *
 * @package WC_Klarna_Payments/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Supplementary_Data class.
 *
 * Class for handling supplementary data sent to Klarna throughout the checkout process.
 */
class KP_Supplementary_Data {

	/**
	 * Key used for identifying an ongoing checkout session per user.
	 *
	 * @var string
	 */
	public static $session_key = 'kp_supplementary_data_id';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_pre_payment_complete', array( $this, 'send_after_redirect' ), 10, 3 );
		add_filter( 'woocommerce_payment_successful_result', array( $this, 'send_before_redirect' ), 10, 2 );
	}

	/**
	 * Handle pre-purchase.
	 *
	 * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-form-handler.html#source-view.445
	 * @hooked woocommerce_payment_successful_result
	 *
	 * @param array|bool $result The result of processing the payment.
	 * @param int        $order_id WC order ID.
	 * @return array|bool $result, unmodified.
	 */
	public function send_before_redirect( $result, $order_id ) {
		$order          = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();
		if ( false !== strpos( $payment_method, 'klarna' ) ) {
			KP_WC()->api->send_supplementary_data(
				array(
					'order_id'       => $order_id,
					'order_number'   => $order->get_order_number(),
					'transaction_id' => $order->get_transaction_id(),
				)
			);
		}

		return $result;
	}

	/**
	 * Handle post-purchase.
	 *
	 * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-order.html#source-view.137
	 * @hooked woocommerce_pre_payment_complete
	 *
	 * @param int    $order_id WC order ID.
	 * @param string $transaction_id Transaction ID.
	 * @return void
	 */
	public function send_after_redirect( $order_id, $transaction_id ) {
		$order          = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();
		if ( false !== strpos( $payment_method, 'klarna' ) ) {
			KP_WC()->api->send_supplementary_data(
				array(
					'order_id'       => $order_id,
					'order_number'   => $order->get_order_number(),
					'transaction_id' => $transaction_id,
				)
			);
		}

		WC()->session->__unset( self::$session_key );
	}


	/**
	 * Generates a random unique ID.
	 *
	 * @param int $length The length of the string.
	 * @return string
	 */
	public static function generate_unique_id( $length = 36 ) {
		try {
			// random_bytes while more secure is not available on all systems.
			return bin2hex( random_bytes( $length / 2 ) );
		} catch ( Exception $e ) {
			return wp_generate_password( $length, false );
		}
	}

}

new KP_Supplementary_Data();
