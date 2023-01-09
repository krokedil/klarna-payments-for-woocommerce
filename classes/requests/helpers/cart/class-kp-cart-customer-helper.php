<?php
/**
 * Handles customer data for Klarna Payments
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Cart_Customer_Helper class.
 *
 * Handles customer data for Klarna Payments
 */
class KP_Cart_Customer_Helper extends KP_Customer {
	/**
	 * Get the billing address for the customer.
	 *
	 * @param string $customer_type The customer type.
	 * @return array
	 */
	public static function get_billing_address( $customer_type ) {
		$address = array(
			'given_name'      => stripslashes( WC()->checkout->get_value( 'billing_first_name' ) ),
			'family_name'     => stripslashes( WC()->checkout->get_value( 'billing_last_name' ) ),
			'email'           => stripslashes( WC()->checkout->get_value( 'billing_email' ) ),
			'phone'           => stripslashes( WC()->checkout->get_value( 'billing_phone' ) ),
			'street_address'  => stripslashes( WC()->checkout->get_value( 'billing_address_1' ) ),
			'street_address2' => stripslashes( WC()->checkout->get_value( 'billing_address_2' ) ),
			'postal_code'     => stripslashes( ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? str_replace( ' ', '', WC()->checkout->get_value( 'billing_postcode' ) ) : WC()->checkout->get_value( 'billing_postcode' ) ),
			'city'            => stripslashes( WC()->checkout->get_value( 'billing_city' ) ),
			'region'          => stripslashes( WC()->checkout->get_value( 'billing_state' ) ),
			'country'         => stripslashes( WC()->checkout->get_value( 'billing_country' ) ),
		);

		if ( 'b2b' === $customer_type ) {
			$address['organization_name'] = stripslashes( WC()->checkout->get_value( 'billing_company' ) );
		}

		return apply_filters( 'wc_kp_billing_address_cart', $address, $customer_type, WC()->checkout );
	}

	/**
	 * Get the shipping address for the customer.
	 *
	 * @param string $customer_type The customer type.
	 * @return array
	 */
	public static function get_shipping_address( $customer_type ) {
		$address = array(
			'given_name'      => stripslashes( WC()->checkout->get_value( 'shipping_first_name' ) ),
			'family_name'     => stripslashes( WC()->checkout->get_value( 'shipping_last_name' ) ),
			'email'           => stripslashes( WC()->checkout->get_value( 'billing_email' ) ),
			'phone'           => stripslashes( WC()->checkout->get_value( 'billing_phone' ) ),
			'street_address'  => stripslashes( WC()->checkout->get_value( 'shipping_address_1' ) ),
			'street_address2' => stripslashes( WC()->checkout->get_value( 'shipping_address_2' ) ),
			'postal_code'     => stripslashes( ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? str_replace( ' ', '', WC()->checkout->get_value( 'shipping_postcode' ) ) : WC()->checkout->get_value( 'shipping_postcode' ) ),
			'city'            => stripslashes( WC()->checkout->get_value( 'shipping_city' ) ),
			'region'          => stripslashes( WC()->checkout->get_value( 'shipping_state' ) ),
			'country'         => stripslashes( WC()->checkout->get_value( 'shipping_country' ) ),
		);

		if ( 'b2b' === $customer_type ) {
			$address['organization_name'] = stripslashes( WC()->checkout->get_value( 'shipping_company' ) );
		}

		return apply_filters( 'wc_kp_shipping_address_cart', $address, $customer_type, WC()->checkout );
	}
}
