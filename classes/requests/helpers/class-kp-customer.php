<?php
/**
 * Handles customer data for Klarna Payments
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Customer class.
 *
 * Handles customer data for Klarna Payments
 */
abstract class KP_Customer {
	/**
	 * Gets the order billing address.
	 *
	 * @param string $customer_type Klarna Customer Type.
	 * @return array
	 */
	abstract public static function get_billing_address( $customer_type );

	/**
	 * Gets the order shipping address.
	 *
	 * @param string $customer_type Klarna Customer Type.
	 * @return array
	 */
	abstract public static function get_shipping_address( $customer_type );
}
