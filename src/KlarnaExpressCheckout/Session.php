<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Session class for Klarna Express Checkout.
 */
class Session {
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_update_order_review', __CLASS__ . '::on_update_order_review' );
		add_action( 'woocommerce_thankyou', __CLASS__ . '::unset_client_token', 1 );
		add_action( 'woocommerce_thankyou', __CLASS__ . '::unset_klarna_address', 1 );
	}

	/**
	 * Validate the stored KEC address against any changes made on the checkout page.
	 *
	 * @param string $posted_data The serialized checkout form data.
	 *
	 * @return void
	 */
	public static function on_update_order_review( $posted_data ) {
		$client_token   = self::get_client_token();
		$klarna_address = self::get_klarna_address();

		if ( ! $client_token || ! $klarna_address ) {
			return;
		}

		parse_str( $posted_data, $parsed_data );

		$payment_method = isset( $parsed_data['payment_method'] ) ? $parsed_data['payment_method'] : '';
		if ( 'klarna_payments' !== $payment_method ) {
			return;
		}

		$ship_to_different_address = isset( $parsed_data['ship_to_different_address'] ) ? $parsed_data['ship_to_different_address'] : false;

		$address_fields_map = array(
			'given_name'      => 'first_name',
			'family_name'     => 'last_name',
			'email'           => 'email',
			'phone'           => 'phone',
			'street_address'  => 'address_1',
			'street_address2' => 'address_2',
			'postal_code'     => 'postcode',
			'city'            => 'city',
			'region'          => 'state',
			'country'         => 'country',
		);

		foreach ( $address_fields_map as $klarna_field => $wc_field ) {
			if ( ! self::verify_address_field( $wc_field, $klarna_field, $parsed_data, $klarna_address, $ship_to_different_address ) ) {
				self::unset_client_token();
				self::unset_klarna_address();
				WC()->session->set( 'reload_checkout', true );
			}
		}
	}

	/**
	 * Verify that an address field has not changed from the stored KEC value.
	 *
	 * @param string $wc_field                  The WooCommerce field name.
	 * @param string $klarna_field              The Klarna address field name.
	 * @param array  $posted_data               The parsed checkout form data.
	 * @param array  $klarna_address            The stored Klarna address.
	 * @param bool   $ship_to_different_address Whether the customer chose to ship to a different address.
	 *
	 * @return bool
	 */
	private static function verify_address_field( $wc_field, $klarna_field, $posted_data, $klarna_address, $ship_to_different_address ) {
		$check_shipping_address = get_option( 'woocommerce_ship_to_destination' ) === 'billing_only' ? false : true;

		$billing_value  = isset( $posted_data[ 'billing_' . $wc_field ] ) ? $posted_data[ 'billing_' . $wc_field ] : '';
		$shipping_value = isset( $posted_data[ 'shipping_' . $wc_field ] ) ? $posted_data[ 'shipping_' . $wc_field ] : '';
		$klarna_value   = isset( $klarna_address[ $klarna_field ] ) ? $klarna_address[ $klarna_field ] : '';

		$billing_value  = html_entity_decode( $billing_value );
		$shipping_value = html_entity_decode( $shipping_value );
		$klarna_value   = html_entity_decode( $klarna_value );

		if ( $check_shipping_address && $shipping_value !== $klarna_value ) {
			if ( in_array( $wc_field, array( 'email', 'phone' ), true ) && empty( $shipping_value ) ) {
				return true;
			}

			self::set_field( 'shipping_' . $wc_field, $shipping_value );
			return false;
		}

		if ( ! $ship_to_different_address && $billing_value !== $klarna_value ) {
			self::set_field( 'billing_' . $wc_field, $billing_value );
			return false;
		}

		return true;
	}

	/**
	 * Set a customer field on the WooCommerce customer object.
	 *
	 * @param string $field The field name.
	 * @param string $value The value to set.
	 *
	 * @return void
	 */
	private static function set_field( $field, $value ) {
		WC()->customer->set_props( array( $field => $value ) );
	}

	/**
	 * Set the KEC client token in the WooCommerce session.
	 *
	 * @param string $token The client token.
	 *
	 * @return bool
	 */
	public static function set_client_token( $token ) {
		if ( ! WC()->session ) {
			return false;
		}

		WC()->session->set( 'kec_client_token', $token );
		return true;
	}

	/**
	 * Set the Klarna address in the WooCommerce session.
	 *
	 * @param array $address The Klarna address.
	 *
	 * @return bool
	 */
	public static function set_klarna_address( $address ) {
		if ( ! WC()->session ) {
			return false;
		}

		WC()->session->set( 'kec_klarna_address', wp_json_encode( $address ) );
		return true;
	}

	/**
	 * Get the KEC client token from the WooCommerce session.
	 *
	 * @return string|bool
	 */
	public static function get_client_token() {
		if ( ! WC()->session ) {
			return false;
		}

		return WC()->session->get( 'kec_client_token', false );
	}

	/**
	 * Get the stored Klarna address from the WooCommerce session.
	 *
	 * @return array|bool
	 */
	public static function get_klarna_address() {
		if ( ! WC()->session ) {
			return false;
		}

		$klarna_address = WC()->session->get( 'kec_klarna_address', false );

		if ( ! $klarna_address ) {
			return false;
		}

		return json_decode( $klarna_address, true );
	}

	/**
	 * Remove the KEC client token from the WooCommerce session.
	 *
	 * @return bool
	 */
	public static function unset_client_token() {
		if ( ! WC()->session ) {
			return false;
		}

		WC()->session->__unset( 'kec_client_token' );
		return true;
	}

	/**
	 * Remove the stored Klarna address from the WooCommerce session.
	 *
	 * @return bool
	 */
	public static function unset_klarna_address() {
		if ( ! WC()->session ) {
			return false;
		}

		WC()->session->__unset( 'kec_klarna_address' );
		return true;
	}
}
