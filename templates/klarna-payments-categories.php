<?php
/**
 * Template file for payment methods.
 *
 * @package WC_Klarna_Payments/Templates
 */

do_action( 'klarna_payments_template' );
if ( is_array( WC()->session->get( 'klarna_payments_categories' ) ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$kp                 = $available_gateways['klarna_payments'];

	foreach ( apply_filters( 'wc_klarna_payments_available_payment_categories', WC()->session->get( 'klarna_payments_categories' ) ) as $payment_category ) {
		$payment_category_id   = 'klarna_payments_' . $payment_category['identifier'];
		$payment_category_name = $payment_category['name'];
		$payment_category_icon = $payment_category['asset_urls']['standard'];
		$kp                    = $available_gateways['klarna_payments'];
		$kp->id                = $payment_category_id;
		$kp->title             = $payment_category_name;
		$kp->icon              = $payment_category_icon;
		$headers               = get_headers( $kp->icon );
		if ( 'HTTP/1.1 404 Not Found' === $headers[0] ) {
			switch ( $kp->id ) {
				case 'klarna_payments_pay_later':
					$kp->icon = 'https://cdn.klarna.com/1.0/shared/image/generic/badge/sv_se/pay_later/standard/pink.svg';
					break;
				case 'klarna_payments_pay_over_time':
					$kp->icon = 'https://cdn.klarna.com/1.0/shared/image/generic/badge/sv_se/slice_it/standard/pink.svg';
					break;
				case 'klarna_payments_pay_now':
					$kp->icon = 'https://cdn.klarna.com/1.0/shared/image/generic/badge/sv_se/pay_now/standard/pink.svg';
					break;
			}
		}
		wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $kp ) );
	}
}
