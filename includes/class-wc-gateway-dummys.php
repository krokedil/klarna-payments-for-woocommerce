<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Klarna_Payments_Pay_Later extends WC_Payment_Gateway {
	public function __construct() {
		$this->id      = 'klarna_payments_pay_later';
		$this->title   = 'Klarna Payments Pay Later';
		$this->enabled = 'no';
	}
}

class WC_Gateway_Klarna_Payments_Pay_Over_Time extends WC_Payment_Gateway {
	public function __construct() {
		$this->id      = 'klarna_payments_pay_over_time';
		$this->title   = 'Klarna Payments Pay Over Time';
		$this->enabled = 'no';
	}
}

class WC_Gateway_Klarna_Payments_Pay_Now extends WC_Payment_Gateway {
	public function __construct() {
		$this->id      = 'klarna_payments_pay_now';
		$this->title   = 'Klarna Payments Pay Now';
		$this->enabled = 'no';
	}
}

add_filter( 'woocommerce_payment_gateways', 'add_klarna_payment_dummy_gateways' );
/**
 * Registers the gateways.
 *
 * @param array $methods
 * @return array $methods
 */
function add_klarna_payment_dummy_gateways( $methods ) {
	$methods[] = 'WC_Gateway_Klarna_Payments_Pay_Later';
	$methods[] = 'WC_Gateway_Klarna_Payments_Pay_Over_Time';
	$methods[] = 'WC_Gateway_Klarna_Payments_Pay_Now';
	return $methods;
}
