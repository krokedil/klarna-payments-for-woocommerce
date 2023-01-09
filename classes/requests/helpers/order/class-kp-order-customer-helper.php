<?php
/**
 * Handles customer data for Klarna Payments
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Order_Customer_Helper class.
 *
 * Handles customer data for Klarna Payments
 */
class KP_Order_Customer_Helper extends KP_Customer {
	/**
	 * The WooCommerce order to be processed.
	 *
	 * @var WC_Order
	 */
	public static $order;

	/**
	 * Class constructor.
	 *
	 * @param WC_Order $order The WooCommerce order to be processed.
	 */
	public function __construct( $order ) {
		self::$order = $order;
	}

	/**
	 * Get the billing address for the customer.
	 *
	 * @param string $customer_type The customer type.
	 * @return array
	 */
	public static function get_billing_address( $customer_type ) {
		$address = array(
			'given_name'      => stripslashes( self::$order->get_billing_first_name() ),
			'family_name'     => stripslashes( self::$order->get_billing_last_name() ),
			'email'           => stripslashes( self::$order->get_billing_email() ),
			'phone'           => stripslashes( self::$order->get_billing_phone() ),
			'street_address'  => stripslashes( self::$order->get_billing_address_1() ),
			'street_address2' => stripslashes( self::$order->get_billing_address_2() ),
			'postal_code'     => stripslashes( ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? str_replace( ' ', '', self::$order->get_billing_postcode() ) : self::$order->get_billing_postcode() ),
			'city'            => stripslashes( self::$order->get_billing_city() ),
			'region'          => stripslashes( self::$order->get_billing_state() ),
			'country'         => stripslashes( self::$order->get_billing_country() ),
		);

		if ( 'b2b' === $customer_type ) {
			$address['organization_name'] = stripslashes( self::$order->get_billing_company() );
		}

		return apply_filters( 'wc_kp_billing_address_order', $address, $customer_type, self::$order );
	}

	/**
	 * Get the shipping address for the customer.
	 *
	 * @param string $customer_type The customer type.
	 * @return array
	 */
	public static function get_shipping_address( $customer_type ) {
		$address = array(
			'given_name'      => stripslashes( self::$order->get_shipping_first_name() ),
			'family_name'     => stripslashes( self::$order->get_shipping_last_name() ),
			'email'           => stripslashes( self::$order->get_billing_email() ),
			'phone'           => stripslashes( self::$order->get_billing_phone() ),
			'street_address'  => stripslashes( self::$order->get_shipping_address_1() ),
			'street_address2' => stripslashes( self::$order->get_shipping_address_2() ),
			'postal_code'     => stripslashes( ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? str_replace( ' ', '', self::$order->get_shipping_postcode() ) : self::$order->get_shipping_postcode() ),
			'city'            => stripslashes( self::$order->get_shipping_city() ),
			'region'          => stripslashes( self::$order->get_shipping_state() ),
			'country'         => stripslashes( self::$order->get_shipping_country() ),
		);

		if ( 'b2b' === $customer_type ) {
			$address['organization_name'] = stripslashes( self::$order->get_shipping_company() );
		}

		// Get the billing address as well.
		$billing_address = self::get_billing_address( $customer_type );

		// Replace any empty values with the billing address value instead.
		foreach ( $address as $key => $value ) {
			if ( empty( $value ) ) {
				$address[ $key ] = $billing_address[ $key ];
			}
		}

		return apply_filters( 'wc_kp_shipping_address_order', $address, $customer_type, self::$order );
	}
}
