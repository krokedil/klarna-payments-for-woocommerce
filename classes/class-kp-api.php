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
	 * Saved version of the Klarna order. To prevent multiple requests from being run when its not needed.
	 *
	 * @var array
	 */
	public $klarna_session = null;

	/**
	 * The last cart hash used to update the Klarna session. If this has not changed, there is no need to update the Klarna session.
	 *
	 * @var string
	 */
	public $cart_hash = null;

	/**
	 * Checks if the cart hash has changed since last we updated the Klarna session.
	 *
	 * @return bool
	 */
	private function check_cart_hash() {
		if ( empty( WC()->cart ) ) {
			return true; // Always return true if we dont have a cart.
		}

		if ( WC()->cart->get_cart_hash() !== $this->cart_hash ) {
			$this->cart_hash = WC()->cart->get_cart_hash();
			return true;
		}

		return false;
	}

	/**
	 * Maybe update the session and the cart hash if the respons is not a WP_Error.
	 *
	 * @param array $klarna_session The Klarna session.
	 * @return void
	 */
	private function maybe_update_saved_session( $klarna_session ) {
		// Check if its a valid response.
		if ( ! is_wp_error( $klarna_session ) ) {
			$this->klarna_session = $klarna_session;
			$this->cart_hash      = WC()->cart ? WC()->cart->get_cart_hash() : null;
		}
	}

	/**
	 * Maybe create a new Klarna session or update the previous one if we have a session id stored.
	 *
	 * @return array|WP_Error
	 */
	public function get_session_cart() {
		// Trigger calculate totals if we are not in ajax and they have not already been calculated by WooCommerce.
		if ( ! is_ajax() && 0 >= did_action( 'woocommerce_before_calculate_totals' ) ) {
			WC()->cart->calculate_fees();
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();
		}

		// If the cart is empty, do nothing.
		if ( empty( WC()->cart->get_cart() ) ) {
			return array();
		}

		$session_id               = WC()->session->get( 'klarna_payments_session_id' );
		$billing_country          = WC()->checkout->get_value( 'billing_country' );
		$previous_billing_country = WC()->session->get( 'klarna_payments_session_country' );

		// Check if we have a session to update.
		if ( $session_id && ( $billing_country === $previous_billing_country ) ) { // Check if we have session ID and country has not changed.
			// Check if we need to update the session before even making an attempt.
			if ( ! $this->check_cart_hash() ) {
				return $this->klarna_session;
			}

			$klarna_session = $this->update_session( $billing_country, $session_id );

			if ( is_wp_error( $klarna_session ) ) {
				// Clear sessions, and run recursive to create a new session.
				kp_unset_session_values();
				$this->klarna_session = null;
				$this->cart_hash      = null;

				if ( did_action( 'kp_session_api_error' ) > 0 ) {
					return $klarna_session; // Prevent infinite loop.
				}

				do_action( 'kp_session_api_error', $klarna_session );

				return $this->get_session_cart();
			}

			// If we have a valid response, update the saved session and cart hash.
			$this->maybe_update_saved_session( $klarna_session );
			$this->set_wc_sessions( $klarna_session, $billing_country );
		} else {
			// If we dont have a session ID, create a new one.
			$klarna_session = $this->create_session( $billing_country );

			// If we get a WP Error on create, just return WP_Error object.
			if ( is_wp_error( $klarna_session ) ) {
				$this->klarna_session = null;
				$this->cart_hash      = null;

				do_action( 'kp_session_api_error', $klarna_session );

				return $klarna_session;
			}

			// If we have a valid response, update the saved session and cart hash.
			$this->maybe_update_saved_session( $klarna_session );
			$this->set_wc_sessions( $klarna_session, $billing_country );
		}

		$this->maybe_update_saved_session( $klarna_session );

		return $this->klarna_session;
	}

	/**
	 * Set WooCommerce sessions from a Klarna session response.
	 *
	 * @param array  $klarna_session The Klarna session response.
	 * @param string $billing_country The billing country used for the Klarna session.
	 * @return void
	 */
	private function set_wc_sessions( $klarna_session, $billing_country ) {
		if ( null === $klarna_session ) {
			return;
		}

		WC()->session->set( 'klarna_payments_session_id', $klarna_session['session_id'] );
		WC()->session->set( 'klarna_payments_client_token', $klarna_session['client_token'] );
		WC()->session->set( 'klarna_payments_categories', $klarna_session['payment_method_categories'] );
		WC()->session->set( 'klarna_payments_session_country', $billing_country );
	}

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

		$this->maybe_update_saved_session( $response );

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
		$request  = new KP_Place_Order(
			array(
				'country'    => $country,
				'auth_token' => $auth_token,
				'order_id'   => $order_id,
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
