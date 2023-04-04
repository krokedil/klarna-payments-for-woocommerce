<?php
/**
 * API Class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Api class.
 *
 * Class that has methods for the Klarna payments communication.
 */
class KP_Api {
	/**
	 * Create session request.
	 *
	 * @param string   $country The Klarna country to use.
	 * @param int|null $order_id The WooCommerce order id. Optional.
	 * @param bool     $include_address True if address should be included in the request. Optional.
	 * @return array|WP_Error The response from Klarna.
	 */
	public function create_session( $country, $order_id = null, $include_address = false ) {
		$request  = new KP_Create_Session(
			array(
				'country'         => $country,
				'order_id'        => $order_id,
				'include_address' => $include_address,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Update session request.
	 *
	 * @param string   $country The Klarna country to use.
	 * @param string   $session_id The Klarna session id.
	 * @param int|null $order_id The WooCommerce order id. Optional.
	 * @param bool     $include_address True if address should be included in the request. Optional.
	 * @return array|WP_Error The response from Klarna.
	 */
	public function update_session( $country, $session_id, $order_id = null, $include_address = false ) {
		$request  = new KP_Update_Session(
			array(
				'country'         => $country,
				'session_id'      => $session_id,
				'order_id'        => $order_id,
				'include_address' => $include_address,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Create HPP request.
	 *
	 * @param string $country The Klarna country to use.
	 * @param string $session_id The Klarna session id.
	 * @param string $order_id The WooCommerce order id.
	 * @return array|WP_Error The response from Klarna.
	 */
	public function create_hpp( $country, $session_id, $order_id ) {
		$request  = new KP_Create_HPP(
			array(
				'country'    => $country,
				'session_id' => $session_id,
				'order_id'   => $order_id,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Place order request.
	 *
	 * @param string $country The Klarna country to use.
	 * @param string $auth_token The Klarna auth token for the session.
	 * @param string $order_id The WooCommerce order id.
	 * @return array|WP_Error The response from Klarna.
	 */
	public function place_order( $country, $auth_token, $order_id ) {
		KP_WC()->session->set_session_data( $order_id );

		$request  = new KP_Place_Order(
			array(
				'country'    => $country,
				'auth_token' => $auth_token,
				'order_id'   => $order_id,
				'session_id' => KP_WC()->session->get_klarna_session_id(),
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response or a WP Error..
	 *
	 * @param array|WP_Error $response The response from the request.
	 * @return array|WP_Error
	 */
	private static function check_for_api_error( $response ) {
		$is_testmode = 'yes' === get_option( 'woocommerce_klarna_payments_settings' )['testmode'];

		if ( is_wp_error( $response ) && $is_testmode ) {
			if ( ! is_admin() ) {
				kp_print_error_message( $response );
			}
		}
		return $response;
	}

}
