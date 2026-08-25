<?php
/**
 * Filter the payment methods returned by Klarna Payments to only show the pay later option to make testing easier.
 */
add_filter( 'wc_klarna_payments_available_payment_categories', function ( $klarna_payment_categories ) {
	$filtered_payment_categories = array_filter( $klarna_payment_categories, function ( $payment_category ) {
		if ( ! is_array( $payment_category ) ) {
			$payment_category = json_decode( wp_json_encode( $payment_category ), true );
		}
		return isset( $payment_category['identifier'] ) && 'pay_later' === $payment_category['identifier'];
	} );

	return $filtered_payment_categories;
} );
