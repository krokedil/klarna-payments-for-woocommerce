<?php
/**
 * Template file for payment methods.
 *
 * @package WC_Klarna_Payments/Templates
 */

do_action( 'klarna_payments_template' );
if ( is_wc_endpoint_url( 'order-pay' ) ) {
	$key                = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
	$order_id           = wc_get_order_id_by_order_key( $key );
	$payment_categories = get_post_meta( $order_id, '_klarna_payments_categories', true );
} else {
	$payment_categories = WC()->session->get( 'klarna_payments_categories' );
}

if ( is_array( $payment_categories ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$kp                 = $available_gateways['klarna_payments'];

	foreach ( apply_filters( 'wc_klarna_payments_available_payment_categories', $payment_categories ) as $payment_category ) {
		if ( ! is_array( $payment_category ) ) {
			$payment_category = json_decode( wp_json_encode( $payment_category ), true );
		}
		$payment_category_id   = 'klarna_payments_' . $payment_category['identifier'];
		$payment_category_name = $payment_category['name'];
		$payment_category_icon = $payment_category['asset_urls']['standard'];
		$kp                    = $available_gateways['klarna_payments'];
		$kp->id                = $payment_category_id;
		$kp->title             = $payment_category_name;
		$kp->icon              = $payment_category_icon;
		wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $kp ) );
	}
}
