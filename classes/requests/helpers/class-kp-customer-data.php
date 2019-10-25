<?php
/**
 * Handles order lines for Klarna Payments.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Payments_Order_Lines class.
 *
 * Processes order lines for Klarna Payments requests.
 */
class KP_Customer_Data {
	/**
	 * Gets the order billing address.
	 *
	 * @param int    $order_id WooCommerce order id.
	 * @param string $customer_type Klarna Customer Type.
	 * @return array
	 */
	public static function get_billing_address( $order_id, $customer_type ) {
		$order           = wc_get_order( $order_id );
		$billing_address = array(
			'given_name'      => stripslashes( $order->get_billing_first_name() ),
			'family_name'     => stripslashes( $order->get_billing_last_name() ),
			'email'           => stripslashes( $order->get_billing_email() ),
			'phone'           => stripslashes( $order->get_billing_phone() ),
			'street_address'  => stripslashes( $order->get_billing_address_1() ),
			'street_address2' => stripslashes( $order->get_billing_address_2() ),
			'postal_code'     => stripslashes( ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? str_replace( ' ', '', $order->get_billing_postcode() ) : $order->get_billing_postcode() ),
			'city'            => stripslashes( $order->get_billing_city() ),
			'region'          => stripslashes( $order->get_billing_state() ),
			'country'         => stripslashes( $order->get_billing_country() ),
		);
		if ( 'b2b' === $customer_type ) {
			$billing_address['organization_name'] = stripslashes( $order->get_billing_company() );
		}
		return $billing_address;
	}

	/**
	 * Gets the order shipping address.
	 *
	 * @param int    $order_id WooCommerce order id.
	 * @param string $customer_type Klarna Customer Type.
	 * @return array
	 */
	public static function get_shipping_address( $order_id, $customer_type ) {
		$order = wc_get_order( $order_id );
		if ( '' !== $order->get_shipping_first_name() && 'b2c' === $customer_type && ! wc_ship_to_billing_address_only() ) {
			$shipping_address = array(
				'given_name'      => stripslashes( $order->get_shipping_first_name() ),
				'family_name'     => stripslashes( $order->get_shipping_last_name() ),
				'email'           => stripslashes( $order->get_billing_email() ),
				'phone'           => stripslashes( $order->get_billing_phone() ),
				'street_address'  => stripslashes( $order->get_shipping_address_1() ),
				'street_address2' => stripslashes( $order->get_shipping_address_2() ),
				'postal_code'     => stripslashes( ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? str_replace( ' ', '', $order->get_shipping_postcode() ) : $order->get_shipping_postcode() ),
				'city'            => stripslashes( $order->get_shipping_city() ),
				'region'          => stripslashes( $order->get_shipping_state() ),
				'country'         => stripslashes( $order->get_shipping_country() ),
			);
		} else {
			$shipping_address = self::get_billing_address( $order_id, $customer_type );
		}

		return $shipping_address;
	}
}
