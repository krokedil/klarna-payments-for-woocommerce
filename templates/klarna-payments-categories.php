<?php
do_action( 'klarna_payments_template' );
if ( is_array( WC()->session->get( 'klarna_payments_categories' ) ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$kp                 = $available_gateways['klarna_payments'];

	foreach ( WC()->session->get( 'klarna_payments_categories' ) as $payment_category ) {
		$payment_category_id   = 'klarna_payments_' . $payment_category->identifier;
		$payment_category_name = $payment_category->name;
		$payment_category_icon = $payment_category->asset_urls->standard;

		$kp        = $available_gateways['klarna_payments'];
		$kp->id    = $payment_category_id;
		$kp->title = $payment_category_name;
		$kp->icon  = $payment_category_icon;

		wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $kp ) );
	}
}
