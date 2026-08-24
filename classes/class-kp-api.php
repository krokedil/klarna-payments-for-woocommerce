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
	 * @param string      $country The Klarna country to use.
	 * @param string      $session_id The Klarna session id.
	 * @param string      $order_id The WooCommerce order id.
	 * @param string|null $credentials_country The credential set the session was created with. Optional.
	 * @return array|WP_Error The response from Klarna.
	 */
	public function create_hpp( $country, $session_id, $order_id, $credentials_country = null ) {
		$request  = new KP_Create_HPP(
			array(
				'country'             => $country,
				'session_id'          => $session_id,
				'order_id'            => $order_id,
				'credentials_country' => $credentials_country,
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

		// The order is placed from a session, so it has to use the credentials that session was created with.
		// The order meta is the reliable source, the session singleton can hold another context.
		$credentials_country = kp_get_order_credentials_country( $order_id );

		if ( empty( $credentials_country ) ) {
			$credentials_country = KP_WC()->session->get_klarna_session_credentials_country( wc_get_order( $order_id ) );
		}

		$request  = new KP_Place_Order(
			array(
				'country'             => $country,
				'auth_token'          => $auth_token,
				'order_id'            => $order_id,
				'session_id'          => KP_WC()->session->get_klarna_session_id(),
				'credentials_country' => $credentials_country,
			)
		);
		$response = $request->request();

		/**
		 * Triggers after the place order request has been sent to Klarna.
		 *
		 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-the-place-order-request-completes
		 * @param array|WP_Error $response The response from the Klarna place order request.
		 * @param string         $order_id The WooCommerce order ID.
		 * @param string         $auth_token The Klarna auth token for the session.
		 */
		do_action( 'kp_after_place_order', $response, $order_id, $auth_token );
		return self::check_for_api_error( $response );
	}


	/**
	 * Create a customer token (required for creating subscriptions).
	 *
	 * @param mixed $country The Klarna country to use.
	 * @param mixed $auth_token The Klarna auth token for the session.
	 * @param mixed $order_id The WooCommerce order id.
	 * @return WP_Error|array
	 */
	public function create_customer_token( $country, $auth_token, $order_id ) {
		$request  = new KP_Create_Customer_Token(
			array(
				'country'             => $country,
				'auth_token'          => $auth_token,
				'order_id'            => $order_id,
				// The token is issued for an authorization, so it belongs to the set that authorized the order.
				'credentials_country' => kp_get_order_credentials_country( $order_id ),
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Create recurring order (subscription).
	 *
	 * @param mixed       $country The Klarna country to use.
	 * @param mixed       $recurring_token The recurring token for the subscription (referred to as customer token in docs).
	 * @param mixed       $order_id The WooCommerce order id.
	 * @param string|null $credentials_country The credential set the recurring token belongs to. Optional.
	 * @return WP_Error|array
	 */
	public function create_recurring_order( $country, $recurring_token, $order_id, $credentials_country = null ) {
		$request  = new KP_Create_Recurring(
			array(
				'country'             => $country,
				'recurring_token'     => $recurring_token,
				'order_id'            => $order_id,
				'credentials_country' => $credentials_country,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}


	/**
	 * Cancel recurring order (subscription).
	 * This is used when a subscription is cancelled in WooCommerce.
	 *
	 * @param mixed       $country The Klarna country to use.
	 * @param mixed       $recurring_token The recurring token for the subscription (referred to as customer token in docs).
	 * @param string|null $currency The purchase currency. Optional, since there is no order to read it from.
	 * @param string|null $credentials_country The credential set the recurring token belongs to. Optional.
	 * @return WP_Error|array
	 */
	public function cancel_recurring_order( $country, $recurring_token, $currency = null, $credentials_country = null ) {
		$request  = new KP_Cancel_Recurring(
			array(
				'country'             => $country,
				'recurring_token'     => $recurring_token,
				'currency'            => $currency,
				'credentials_country' => $credentials_country,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Get the Klarna order from the order management API.
	 *
	 * @param string      $country The Klarna country to use.
	 * @param string      $klarna_order_id The Klarna order id.
	 * @param string|null $credentials_country The credential set the order was authorized with. Optional.
	 *
	 * @return array|WP_Error The response from Klarna.
	 */
	public function get_klarna_om_order( $country, $klarna_order_id, $credentials_country = null ) {
		$request  = new KP_Get_Order(
			array(
				'country'             => $country,
				'klarna_order_id'     => $klarna_order_id,
				'credentials_country' => $credentials_country,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Upsell the klarna order.
	 *
	 * @param string      $country The Klarna country to use.
	 * @param string      $klarna_order_id The Klarna order id.
	 * @param int         $order_id The WooCommerce order id.
	 * @param string|null $credentials_country The credential set the order was authorized with. Optional.
	 *
	 * @return array|WP_Error The response from Klarna.
	 */
	public function upsell_klarna_order( $country, $klarna_order_id, $order_id, $credentials_country = null ) {
		$request  = new KP_Upsell_Order(
			array(
				'country'             => $country,
				'klarna_order_id'     => $klarna_order_id,
				'order_id'            => $order_id,
				'credentials_country' => $credentials_country,
			)
		);
		$response = $request->request();

		return self::check_for_api_error( $response );
	}

	/**
	 * Return the session from Klarna Payments.
	 *
	 * @param string $session_id The Klarna session id.
	 * @param string $country The Klarna country to use.
	 * @return array|WP_Error The response from Klarna.
	 */
	public function get_session( $session_id, $country = null ) {
		if ( ! $country ) {
			$country = kp_get_klarna_country();
		}

		$request  = new KP_Get_Session(
			array(
				'session_id' => $session_id,
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
		$is_testmode = 'yes' === ( get_option( 'woocommerce_klarna_payments_settings', array() )['testmode'] ?? 'no' );

		if ( is_wp_error( $response ) && $is_testmode ) {
			if ( ! is_admin() ) {
				kp_print_error_message( $response );
			}
		}
		return KP_WC()->report()->request( $response );
	}

	/**
	 * Get unavailable features.
	 *
	 * @param array $credentials The credentials to use.
	 * @return array|WP_Error
	 */
	public function get_unavailable_features( $credentials ) {
		$api_password = $credentials['shared_secret'] ?? false;

		if ( ! $api_password ) {
			/* translators: [merchant-facing]. */
			return new WP_Error( 'missing_shared_secret', __( 'Missing shared secret.', 'klarna-payments-for-woocommerce' ) );
		}

		if ( ! get_option( 'kp_uuid4' ) ) {
			add_option( 'kp_uuid4', wp_generate_uuid4() );
		}

		$mode       = $credentials['mode'] ?? 'live';
		$request_id = get_option( 'kp_uuid4' );

		$response = ( new KP_Unavailable_Features(
			array(
				'api_password' => $api_password,
				'mode'         => $mode,
				'request_id'   => $request_id,
				'country'      => $credentials['country_code'],
			)
		) )->request();

		return self::check_for_api_error( $response );
	}
}
