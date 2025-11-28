<?php
/**
 * Template file for payment methods.
 *
 * @package WC_Klarna_Payments/Templates
 */

if ( kp_is_order_pay_page() ) {
	$klarna_wc_order_id = absint( get_query_var( 'order-pay' ) );
	$klarna_wc_order    = wc_get_order( $klarna_wc_order_id );

	// Create a new session as 'woocommerce_after_calculate_totals' is only triggered on the cart (and checkout) page.
	KP_WC()->session->get_session( $klarna_wc_order );
}

$klarna_payment_categories = KP_WC()->session->get_klarna_payment_method_categories();

if ( is_array( $klarna_payment_categories ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$kp                 = $available_gateways['klarna_payments'];
	$chosen_gateway     = $available_gateways[ array_key_first( $available_gateways ) ];

	foreach ( apply_filters( 'wc_klarna_payments_available_payment_categories', $klarna_payment_categories ) as $klarna_payment_category ) {
		if ( ! is_array( $klarna_payment_category ) ) {
			$klarna_payment_category = json_decode( wp_json_encode( $klarna_payment_category ), true );
		}
		$klarna_payment_category_id   = 'klarna_payments_' . $klarna_payment_category['identifier'];
		$klarna_payment_category_name = $klarna_payment_category['name'];
		$klarna_payment_category_icon = $klarna_payment_category['asset_urls']['standard'] ?? null;
		$kp                           = $available_gateways['klarna_payments'];
		$kp->id                       = $klarna_payment_category_id;
		$kp->title                    = $klarna_payment_category_name;
		$kp->icon                     = $klarna_payment_category_icon;

		// Make sure the first KP payment categories is selected, if KP is the chosen gateway.
		if ( false !== strpos( $chosen_gateway->id, 'klarna_payments' ) || $kp->chosen ) {
			$kp->chosen = false;
			if ( $klarna_payment_category_name === $klarna_payment_categories[ array_key_first( $klarna_payment_categories ) ]['name'] ) {
				$kp->chosen = true;
			}
		}

		// For "Linear Checkout for WooCommerce by Cartimize" to work, we cannot output any HTML.
		if ( did_action( 'cartimize_get_payment_methods_html' ) === 0 ) {
			wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $kp ) );
		}
	}
}
