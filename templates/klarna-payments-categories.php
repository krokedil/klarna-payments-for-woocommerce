<?php
/**
 * Template file for payment methods.
 *
 * @package WC_Klarna_Payments/Templates
 */
if ( kp_is_order_pay_page() ) {
	$order_id = absint( get_query_var( 'order-pay' ) );
	$order    = wc_get_order( $order_id );

	// Create a new session as 'woocommerce_after_calculate_totals' is only triggered on the cart (and checkout) page.
	KP_WC()->session->get_session( $order );
}

$payment_categories = KP_WC()->session->get_klarna_payment_method_categories();

if ( is_array( $payment_categories ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$kp                 = $available_gateways['klarna_payments'];
	$chosen_gateway     = $available_gateways[ array_key_first( $available_gateways ) ];

	foreach ( apply_filters( 'wc_klarna_payments_available_payment_categories', $payment_categories ) as $payment_category ) {
		if ( ! is_array( $payment_category ) ) {
			$payment_category = json_decode( wp_json_encode( $payment_category ), true );
		}
		$payment_category_id   = 'klarna_payments_' . $payment_category['identifier'];
		$payment_category_name = $payment_category['name'];
		$payment_category_icon = $payment_category['asset_urls']['standard'] ?? null;
		$kp                    = $available_gateways['klarna_payments'];
		$kp->id                = $payment_category_id;
		$kp->title             = $payment_category_name;
		$kp->icon              = $payment_category_icon;

		// Make sure the first KP payment categories is selected, if KP is the chosen gateway.
		if ( false !== strpos( $chosen_gateway->id, 'klarna_payments' ) || $kp->chosen ) {
			$kp->chosen = false;
			if ( $payment_category_name == $payment_categories[ array_key_first( $payment_categories ) ]['name'] ) {
				$kp->chosen = true;
			}
		}

		wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $kp ) );
	}
}
