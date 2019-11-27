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
function kp_maybe_create_session( $klarna_country = false ) {
	if ( ! $klarna_country ) {
		$klarna_country = WC()->checkout->get_value( 'billing_country' );
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
			WC()->session->set( 'klarna_payments_session_id', $response['session_id'] );
			WC()->session->set( 'klarna_payments_client_token', $response['client_token'] );
			WC()->session->set( 'klarna_payments_session_country', $klarna_country );
			WC()->session->set( 'klarna_payments_categories', $response['payment_method_categories'] );
			return $response;
		}
		return $response;
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
		return $response;
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
	WC()->session->__unset( 'kp_update_md5' );
}

/**
 * Checks if a response has errors in it.
 *
 * @param array $response Klarna Payment Response.
 * @return string
 */
function kp_extract_error_message( $response ) {
	$code    = $response->get_error_code();
	$message = $response->get_error_message();
	$text    = __( 'Klarna Payments API Error: ', 'klarna-payments-for-woocommerce' ) . '%s %s';
	return sprintf( $text, $code, $message );
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
	$type = ( 'b2b' === $customer_type ) ? 'organization' : 'person';
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

/**
 * Process accepted Klarna Payments order.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $decoded Klarna order.
 *
 * @return array   $result  Payment result.
 */
function kp_process_accepted( $order, $decoded ) {
	$kp_gateway = new WC_Gateway_Klarna_Payments();
	$order->payment_complete( $decoded['order_id'] );
	$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded['order_id'] );
	update_post_meta( $order->get_id(), '_wc_klarna_order_id', $decoded['order_id'], true );
	do_action( 'wc_klarna_payments_accepted', $order->get_id(), $decoded );
	do_action( 'wc_klarna_accepted', $order->get_id(), $decoded );
	return array(
		'result'   => 'success',
		'redirect' => $kp_gateway->get_return_url( $order ),
	);
}

/**
 * Process pending Klarna Payments order.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $decoded Klarna order.
 *
 * @return array   $result  Payment result.
 */
function kp_process_pending( $order, $decoded ) {
	$kp_gateway = new WC_Gateway_Klarna_Payments();
	$order->update_status( 'on-hold', 'Klarna order is under review, order ID: ' . $decoded['order_id'] );
	update_post_meta( $order->get_id(), '_wc_klarna_order_id', $decoded['order_id'], true );
	update_post_meta( $order->get_id(), '_transaction_id', $decoded['order_id'], true );
	do_action( 'wc_klarna_payments_pending', $order->get_id(), $decoded );
	do_action( 'wc_klarna_pending', $order->get_id(), $decoded );
	return array(
		'result'   => 'success',
		'redirect' => $kp_gateway->get_return_url( $order ),
	);
}

/**
 * Process rejected Klarna Payments order.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $decoded Klarna order.
 *
 * @return array   $result  Payment result.
 */
function kp_process_rejected( $order, $decoded ) {
	$order->update_status( 'on-hold', 'Klarna order was rejected.' );
	do_action( 'wc_klarna_payments_rejected', $order->get_id(), $decoded );
	do_action( 'wc_klarna_rejected', $order->get_id(), $decoded );
	return array(
		'result'   => 'failure',
		'redirect' => '',
		'messages' => '<div class="woocommerce-error">Klarna payment rejected</div>',
	);
}
