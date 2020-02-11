<?php
/**
 * Adds the possiblity to add Klarna data to the end of order confirmation emails.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KP_Email' ) ) {
	/**
	 * The class for email handling for KP.
	 */
	class KP_Email {
		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'woocommerce_email_after_order_table', array( $this, 'add_klarna_data_to_mail' ), 10, 3 );
		}

		/**
		 * Adds Klarna data to the order email.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 *
		 * @return void
		 */
		public function add_klarna_data_to_mail( $order ) {
			$gateway_used = $order->get_payment_method();
			$settings     = get_option( 'woocommerce_klarna_payments_settings' );
			$add_to_email = 'yes' === $settings['add_to_email'] ? true : false;
			if ( 'klarna_payments' === $gateway_used && $add_to_email ) {
				?>
				<p><a href="https://app.klarna.com/"><?php echo esc_html__( 'Klarna App', 'klarna-payments-for-woocommerce' ); ?></a></p>
				<p><a href="https://www.klarna.com/customer-service"><?php echo esc_html__( 'Klarna Customer Service', 'klarna-payments-for-woocommerce' ); ?></a></p>
				<?php
			}
		}
	}
	new KP_Email();
}
