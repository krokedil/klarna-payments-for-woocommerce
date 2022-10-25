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
	 * @param string $country The Klarna country to use.
	 * @return array|WP_Error The response from Klarna.
	 */
	public static function create_session( $country ) {
		$request  = new KP_Create_Session( array( 'country' => $country ) );
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Update session request.
	 *
	 * @param string $session_id The Klarna session id.
	 * @param string $country The Klarna country to use.
	 * @return array|WP_Error The response from Klarna.
	 */
	public static function update_session( $session_id, $country ) {
		$request  = new KP_Update_Session(
			array(
				'session_id' => $session_id,
				'country'    => $country,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Create HPP request.
	 *
	 * @param string $session_id The Klarna session id.
	 * @param string $order_id The WooCommerce order id.
	 * @param string $country The Klarna country to use.
	 * @return array|WP_Error The response from Klarna.
	 */
	public static function create_hpp( $session_id, $order_id, $country ) {
		$request  = new KP_Create_HPP(
			array(
				'session_id' => $session_id,
				'order_id'   => $order_id,
				'country'    => $country,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Place order request.
	 *
	 * @param string $session_id The Klarna session id.
	 * @param string $order_id The WooCommerce order id.
	 * @param string $country The Klarna country to use.
	 * @return array|WP_Error The response from Klarna.
	 */
	public static function place_order( $session_id, $order_id, $country ) {
		$request  = new KP_Place_Order(
			array(
				'session_id' => $session_id,
				'order_id'   => $order_id,
				'country'    => $country,
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
		if ( is_wp_error( $response ) ) {
			if ( ! is_admin() ) {
				kp_print_error_message( $response );
			}
		}
		return $response;
	}

}
