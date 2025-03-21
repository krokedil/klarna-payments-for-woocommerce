<?php
/**
 * Class for managing the Klarna Interoperability token.
 *
 * @package KlarnaPayments/Classes
 */

defined( 'ABSPATH' ) || exit;

class KP_Interoperability_Token {
	/**
	 * Get the token from the WooCommerce session.
	 *
	 * @return string|null
	 */
	public static function get_token() {
		// If we don't have a session, we dont have a token.
		if ( null === WC()->session || ! WC()->session->has_session() ) {
			return null;
		}

		return WC()->session->get( 'klarna_interoperability_token' );
	}

	/**
	 * Set the token in the WooCommerce session.
	 *
	 * @param string $token The token to set.
	 *
	 * @return void
	 */
	public static function set_token( $token ) {
		// Maybe start the WooCommerce session if it's not already started for the current user.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		WC()->session->set( 'klarna_interoperability_token', $token );
	}
}
