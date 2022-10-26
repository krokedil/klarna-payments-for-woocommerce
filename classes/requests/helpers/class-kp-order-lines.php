<?php
/**
 * Handles order lines for Klarna Payments.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Klarna_Payments_Order_Lines class.
 *
 * Processes order lines for Klarna Payments requests.
 */
abstract class KP_Order_Lines {
	/**
	 * Converts minor units to major units for Klarna Payments.
	 *
	 * @param mixed $number The number to convert.
	 * @return int The converted number.
	 */
	public static function format_number( $number ) {
		return intval( wc_format_decimal( $number, '' ) * 100 );
	}

	/**
	 * Filters array values to remove all null values.
	 *
	 * @param mixed $var The line value to filter.
	 * @return bool
	 */
	public static function remove_null( $var ) {
		return is_array( $var ) ? ! empty( $var ) : null !== $var; // If empty array, or if value is null return true to remove value.
	}

	/**
	 * Returns processed order lines from either a WooCommerce order or cart. Has to be overriden in child class.
	 *
	 * @return array
	 */
	abstract public static function get_kp_order_lines();

	/**
	 * Returns the order amount for Klarna Payments. Has to be overriden in child class.
	 *
	 * @return int
	 */
	abstract public static function get_kp_order_amount();

	/**
	 * Returns the order tax amount for Klarna Payments. Has to be overriden in child class.
	 *
	 * @return int
	 */
	abstract public static function get_kp_order_tax_amount();
}
