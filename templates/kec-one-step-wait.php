<?php
/**
 * The notice shown while waiting for Klarna to confirm a one step Express Checkout payment.
 *
 * This template can be overridden by copying it to yourtheme/klarna-payments/kec-one-step-wait.php.
 *
 * @package WC_Klarna_Payments/Templates
 *
 * @var \WC_Order $order The order awaiting Klarna's confirmation.
 */

defined( 'ABSPATH' ) || exit;

?>
<p class="woocommerce-notice woocommerce-notice--info kec-one-step-wait">
	<?php esc_html_e( 'Please wait while we confirm your payment with Klarna.', 'klarna-payments-for-woocommerce' ); ?>
</p>
