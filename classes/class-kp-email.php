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
			$settings     = get_option( 'woocommerce_klarna_payments_settings', array() );
			$add_to_email = ( isset( $settings['add_to_email'] ) && 'yes' === $settings['add_to_email'] ) ? true : false;
			if ( 'klarna_payments' === $gateway_used && $add_to_email ) {
				$klarna_cs_url  = '<a href="https://www.klarna.com/customer-service">' . esc_html__( 'Klarna', 'klarna-payments-for-woocommerce' ) . '</a>';
				$klarna_app_url = '<a href="https://app.klarna.com/">' . esc_html__( 'Klarna App', 'klarna-payments-for-woocommerce' ) . '</a>';
				?>
				<p><?php echo esc_html__( 'Klarna order id:', 'klarna-payments-for-woocommerce' ) . ' ' . esc_html( $order->get_transaction_id() ); ?></p>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1. Klarna customer service URL. 2. Klarnas app url.
							__(
								'Your payment is processed by our partner %1$s. You will shortly receive instructions on how to complete your payment. You can manage all your payments via Klarna.com or in the %2$s',
								'klarna-payments-for-woocommerce'
							),
							$klarna_cs_url,
							$klarna_app_url
						),
						array( 'a' => array( 'href' => array() ) )
					);
					?>
				</p>
				<?php
			}
		}
	}
	new KP_Email();
}
