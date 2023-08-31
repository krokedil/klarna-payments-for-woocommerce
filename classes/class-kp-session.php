<?php
/**
 * Class file for the KP Session class. Used to handle sessions for Klarna Payments.
 *
 * @package WC_Klarna_Payments/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Session class.
 *
 * Class that has methods for the Klarna payments session.
 * Used to ensure that the Klarna session is updated only when needed and to prevent multiple requests from being run.
 */
class KP_Session {
	/**
	 * Saved version of the Klarna order. To prevent multiple requests from being run when its not needed.
	 *
	 *  Array(
	 *      'client_token' => string                - The token to use in the frontend to interact with the Klarna JS Api.
	 *      'session_id'   => string                - The session id used for API calls with Klarna using this session.
	 *      'payment_method_categories' => Array(   - The payment method categories available for this session. Array of objects describing the payment method categories.
	 *          {
	 *              'name'        => string         - The name of the payment method category.
	 *              'identifier'  => string         - The identifier of the payment method category.
	 *              'asset_urls' => array(         - The assets urls for the payment method category. Array of objects describing the assets urls.
	 *                 'descriptive' => string,     - The descriptive assets url.
	 *                 'standard'    => string,     - The standard assets url.
	 *              ),
	 *          },
	 *      ),
	 *  );
	 *
	 * @var array
	 */
	public $klarna_session = null;

	/**
	 * The last cart hash used to update the Klarna session. If this has not changed, there is no need to update the Klarna session.
	 *
	 * @var string
	 */
	public $session_hash = null;

	/**
	 * Get the country used for the Klarna session.
	 *
	 * @var string
	 */
	public $session_country = null;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'get_session' ), 999999 ); // Maybe update session on after_calculate_totals.
	}

	/**
	 * Gets a Klarna sessions. Creates or updates the Klarna session if needed.
	 *
	 * @param int|WC_Order|null $order The WooCommerce order or order id. Null if we are working with a cart.
	 */
	public function get_session( $order = null ) {
		if ( ! kp_is_available() || ! kp_is_checkout_page() && ! KP_Subscription::is_change_payment_method() ) {
			return;
		}

		// Check if we get an order.
		$order    = $this->maybe_get_order( $order );
		$order_id = ! ( empty( $order ) || is_wp_error( $order ) ) ? $order->get_id() : null;

		// Return WP Error if we get one.
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Set session data from WC Session or order meta if we have any.
		$this->set_session_data( $order );

		// If we already have a Klarna session, and the session_country has changed since our last request, reset all our params and a new session will be created.
		if ( null !== $this->klarna_session && kp_get_klarna_country( $order ) !== $this->session_country ) {
			$this->klarna_session  = null;
			$this->session_hash    = null;
			$this->session_country = null;
		}

		// If we already have a Klarna session and session does not need an update, return the Klarna session.
		if ( null !== $this->klarna_session && ! $this->session_needs_update( $order ) ) {
			return $this->klarna_session;
		}

		// If we have a Klarna session and we get here, we should update it.
		if ( null !== $this->klarna_session ) {
			$result = KP_WC()->api->update_session( kp_get_klarna_country( $order ), $this->klarna_session['session_id'], $order_id );
			return $this->process_result( $result, $order );
		}

		// If we get here, we should create a new Klarna session. Pass true to include_address if we have an order.
		$result = KP_WC()->api->create_session( kp_get_klarna_country( $order ), $order_id, null !== $order );

		// Process result and return the Klarna session.
		return $this->process_result( $result, $order );
	}

	/**
	 * Sets session data from a WC session or order meta.
	 *
	 * @param WC_Order|int|null $order The WooCommerce order or order id. Null if we are working with a cart (default).
	 * @return void
	 * @throws Exception If we get an error when trying to get the session data.
	 */
	public function set_session_data( $order = null ) {
		// Maybe get the order from order id.
		$order = $this->maybe_get_order( $order );

		// Return WP Error if we get one.
		if ( is_wp_error( $order ) ) {
			throw new Exception( $order->get_error_message() );
		}

		// If we have an order, get the Klarna session from order meta.
		$session_data = $order ? $order->get_meta( '_kp_session_data', true ) : WC()->session->get( 'kp_session_data' );

		// If the session data is empty, just return.
		if ( empty( $session_data ) ) {
			return;
		}

		// Decode the session data.
		$session_data = json_decode( $session_data, true );

		// If the session data is empty or not an array, return.
		if ( empty( $session_data ) || ! is_array( $session_data ) ) {
			return;
		}

		$this->klarna_session  = $session_data['klarna_session'];
		$this->session_hash    = $session_data['session_hash'];
		$this->session_country = $session_data['session_country'];
	}

	/**
	 * Process a API Response from Klarna and set the class parameters.
	 *
	 * @param array|WP_Error $result The Klarna API response.
	 * @param WC_Order       $order The WooCommerce order, or null if a cart was used.
	 * @return array|WP_Error
	 */
	private function process_result( $result, $order ) {
		if ( is_wp_error( $result ) ) {
			// If we get an error, clear the WC session or order meta.
			$this->clear_session_data_in_wc( $order );
			return $result;
		}

		$this->klarna_session  = ! empty( $result ) ? $result : $this->klarna_session; // If the result is empty, it was from a successfull update request. So no need to update the session data.
		$this->session_hash    = null === $order ? $this->get_session_cart_hash() : $this->get_session_order_hash( $order );
		$this->session_country = kp_get_klarna_country( $order );

		// Update the WC Session or the order meta with instance of class.
		$this->update_session_data_in_wc( $order );

		return $result;
	}

	/**
	 * Updates the saved session data in WooCommerce. Either to WC_Session or order meta.
	 *
	 * @param WC_Order|null $order The WooCommerce order. Null if we are working with a cart.
	 * @return void
	 */
	private function update_session_data_in_wc( $order ) {
		// Update the WC Session or the order meta with instance of class.
		if ( empty( $order ) ) {
			WC()->session->set( 'kp_session_data', wp_json_encode( $this ) );
		} else {
			$order->update_meta_data( '_kp_session_data', wp_json_encode( $this ) );
			$order->save();
		}
	}

	/**
	 * Clears the saved session data in WooCommerce. Either from WC_Session or order meta.
	 *
	 * @param WC_Order|null $order The WooCommerce order. Null if we are working with a cart.
	 * @return void
	 */
	private function clear_session_data_in_wc( $order ) {
		// Clear the WC Session or the order meta with instance of class.
		if ( null === $order ) {
			WC()->session->__unset( 'kp_session_data' );
		} else {
			$order->delete_meta_data( '_kp_session_data' );
			$order->save();
		}
	}

	/**
	 * Maybe gets the order from the value passed.
	 *
	 * @param int|WC_Order|null $order The WooCommerce order or order id. Null if we are working with a cart.
	 * @return WC_Order|null|WP_Error
	 */
	private function maybe_get_order( $order ) {
		// If the order is already null, just return.
		if ( null === $order ) {
			return null;
		}

		// If we get passed a cart by the WooCommerce actions, then we treat that the same as null.
		if ( is_a( $order, 'WC_Cart' ) ) {
			return null;
		}

		// If its already an order, just return.
		if ( is_a( $order, 'WC_Order' ) ) {
			return $order;
		}

		// Attempt to get the order from WooCommerce.
		$tmp_order = wc_get_order( $order );
		if ( ! $tmp_order ) {
			return new WP_Error( 'kp_order_not_found', __( 'Order was not found', 'klarna-payments-for-woocommerce' ), $order );
		}

		return $tmp_order;
	}

	/**
	 * Check if the current session needs to be updated or not.
	 *
	 * @param WC_Order|null $order The WooCommerce order, or null if working with cart.
	 * @return bool
	 */
	private function session_needs_update( $order ) {
		$session_hash = null === $order ? $this->get_session_cart_hash() : $this->get_session_order_hash( $order );

		$needs_update = $this->session_hash !== $session_hash;

		$this->session_hash = $session_hash;

		// Update stored session data, so we save the new hash.
		$this->update_session_data_in_wc( $order );

		return $needs_update;
	}

	/**
	 * Gets a hash from a cart we can use to verify if the session needs to be updated.
	 *
	 * @return string
	 */
	private function get_session_cart_hash() {
		// The `get_totals` method can return non-numeric items which should be removed before using `array_sum`.
		$cart_totals = array_filter(
			WC()->cart->get_totals(),
			function( $total ) {
				return is_numeric( $total );
			}
		);

		// Get values to use for the combined hash calculation.
		$total            = array_sum( $cart_totals );
		$billing_address  = WC()->customer->get_billing();
		$shipping_address = WC()->customer->get_shipping();
		$shipping_method  = WC()->session->get( 'chosen_shipping_methods' );

		// Calculate a hash from the values.
		$hash = md5( wp_json_encode( array( $total, $billing_address, $shipping_address, $shipping_method ) ) );

		return $hash;
	}

	/**
	 * Gets a hash from a order we can use to verify if the session needs to be updated.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return string
	 */
	private function get_session_order_hash( $order ) {
		// Get values to use for the combined hash calculation.
		$total            = $order->get_total( 'kp_total' );
		$billing_address  = $order->get_address( 'billing' );
		$shipping_address = $order->get_address( 'shipping' );

		// Calculate a hash from the values.
		$hash = md5( wp_json_encode( array( $total, $billing_address, $shipping_address ) ) );

		return $hash;
	}

	/**
	 * Get the Klarna session data.
	 *
	 * @return array|null
	 */
	public function get_klarna_session_data() {
		return $this->klarna_session;
	}

	/**
	 * Get the Klarna session id.
	 *
	 * @return string|null
	 */
	public function get_klarna_session_id() {
		return $this->klarna_session ? $this->klarna_session['session_id'] : null;
	}

	/**
	 * Get the Klarna session client token.
	 *
	 * @return string|null
	 */
	public function get_klarna_client_token() {
		return $this->klarna_session ? $this->klarna_session['client_token'] : null;
	}

	/**
	 * Get the Klarna session country.
	 *
	 * @param WC_Order|null $order The WooCommerce order, used for the fallback kp_get_klarna_country incase the session is missing the country.
	 * @return string
	 */
	public function get_klarna_session_country( $order = null ) {
		return $this->session_country ?? kp_get_klarna_country( $order );
	}

	/**
	 * Get the Klarna session payment method categories.
	 *
	 * @return array|null
	 */
	public function get_klarna_payment_method_categories() {
		return $this->klarna_session ? $this->klarna_session['payment_method_categories'] : null;
	}
}
