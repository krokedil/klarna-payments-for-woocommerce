<?php
/**
 * Plugin function file.
 *
 * @package WC_Klarna_Payments/Includes
 */

/**
 * Maybe creates or updates Klarna Payments session.
 *
 * @param string $klarna_country The Klarna Country.
 * @return void|string
 */
function kp_maybe_create_session( $klarna_country ) {
	if ( ! is_checkout() || is_order_received_page() ) {
		return;
	}

	// Need to calculate these here, because WooCommerce hasn't done it yet.
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();

	if ( WC()->session->get( 'klarna_payments_session_id' ) && ( WC()->checkout->get_value( 'billing_country' ) === WC()->session->get( 'klarna_payments_session_country' ) ) ) { // Check if we have session ID and country has not changed.
		// Try to update the session, if it fails try to create new session.
		$request  = new KP_Update_Session();
		$response = $request->request();
		if ( is_wp_error( $response ) ) { // If update session failed try to create new session.
			kp_unset_session_values();
			$request  = new KP_Create_Session();
			$response = $request->request();
			if ( is_wp_error( $response ) ) {
				return kp_extract_error_message( $response );
			}
			WC()->session->set( 'klarna_payments_session_id', $create_response['session_id'] );
			WC()->session->set( 'klarna_payments_client_token', $create_response['client_token'] );
			WC()->session->set( 'klarna_payments_session_country', $klarna_country );
			WC()->session->set( 'klarna_payments_categories', $create_response['payment_method_categories'] );
		}
	} else {
		$request  = new KP_Create_Session();
		$response = $request->request();
		if ( is_wp_error( $response ) ) {
			return kp_extract_error_message( $response );
		}
		WC()->session->set( 'klarna_payments_session_id', $response['session_id'] );
		WC()->session->set( 'klarna_payments_client_token', $response['client_token'] );
		WC()->session->set( 'klarna_payments_session_country', $klarna_country );
		WC()->session->set( 'klarna_payments_categories', $response['payment_method_categories'] );
	}

	// If we have a client token now, initialize Klarna Credit.
	if ( WC()->session->get( 'klarna_payments_client_token' ) ) {
		// @codingStandardsIgnoreStart
		// @TODO Maybe change this to not be inline included.
		?>
		<script>
			window.klarnaInitData = {client_token: "<?php echo esc_attr( WC()->session->get( 'klarna_payments_client_token' ) ); ?>"};
			window.klarnaAsyncCallback = function () {
				Klarna.Payments.init(klarnaInitData);
			};
		</script>
		<script src="https://x.klarnacdn.net/kp/lib/v1/api.js" async></script>
		<?php
		// @codingStandardsIgnoreEnd
	}
}

/**
 * Unsets all Klarna Payments sessions.
 */
function kp_unset_session_values() {
	WC()->session->__unset( 'klarna_payments_session_id' );
	WC()->session->__unset( 'klarna_payments_client_token' );
	WC()->session->__unset( 'klarna_payments_session_country' );
	WC()->session->__unset( 'klarna_payments_categories' );
}

/**
 * Checks if a response has errors in it.
 *
 * @param array $response Klarna Payment Response.
 * @return string
 */
function kp_extract_error_message( $response ) {
	return 'Error';
}

/**
 * Gets the locale need for the klarna country.
 *
 * @param string $klarna_country Klarna country.
 * @return string
 */
function get_locale_for_klarna_country( $klarna_country ) {
	$has_english_locale = 'en_US' === get_locale() || 'en_GB' === get_locale();
	switch ( $klarna_country ) {
		case 'AT':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-at';
			} else {
				$klarna_locale = 'de-at';
			}
			break;
		case 'BE':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-be';
			} elseif ( 'fr_be' === strtolower( get_locale() ) ) {
				$klarna_locale = 'fr-be';
			} else {
				$klarna_locale = 'nl-be';
			}
			break;
		case 'CA':
			$klarna_locale = 'en-ca';
			break;
		case 'CH':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-ch';
			} else {
				$klarna_locale = 'de-ch';
			}
			break;
		case 'DE':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-de';
			} else {
				$klarna_locale = 'de-de';
			}
			break;
		case 'DK':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-dk';
			} else {
				$klarna_locale = 'da-dk';
			}
			break;
		case 'ES':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-es';
			} else {
				$klarna_locale = 'es-es';
			}
			break;
		case 'FI':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-fi';
			} elseif ( 'sv_se' === strtolower( get_locale() ) ) {
				$klarna_locale = 'sv-fi';
			} else {
				$klarna_locale = 'fi-fi';
			}
			break;
		case 'IT':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-it';
			} else {
				$klarna_locale = 'it-it';
			}
			break;
		case 'NL':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-nl';
			} else {
				$klarna_locale = 'nl-nl';
			}
			break;
		case 'NO':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-no';
			} else {
				$klarna_locale = 'nb-no';
			}
			break;
		case 'PL':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-pl';
			} else {
				$klarna_locale = 'pl-pl';
			}
			break;
		case 'SE':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-se';
			} else {
				$klarna_locale = 'sv-se';
			}
			break;
		case 'GB':
			$klarna_locale = 'en-gb';
			break;
		case 'US':
			$klarna_locale = 'en-us';
			break;
		default:
			$klarna_locale = 'en-us';
	}
	return $klarna_locale;
}

/**
 * Adds the customer object to the request arguments.
 *
 * @param string $customer_type The customer type from the settings.
 * @return array
 */
function get_klarna_customer( $customer_type ) {
	$type = ( 'b2c' === $customer_type ) ? 'person' : 'organization';
	return array(
		'type' => $type,
	);
}

/**
 * Gets Klarna country.
 */
function kp_get_klarna_country() {
	if ( ! method_exists( 'WC_Customer', 'get_billing_country' ) ) {
			return;
	}
	if ( WC()->customer === null ) {
		return;
	}
	return apply_filters( 'wc_klarna_payments_country', WC()->customer->get_billing_country() );
}
