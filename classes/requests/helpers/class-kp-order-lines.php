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
}
