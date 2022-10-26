<?php
/**
 * Plugin function file.
 *
 * @package WC_Klarna_Payments/Includes
 */

/**
 * Creates a Klarna Payments session if needed for an order.
 *
 * @param int         $order_id The WooCommerce order id.
 * @param string|bool $klarna_country The Klarna country.
 * @return void|WP_Error
 */
function kp_create_session_order( $order_id, $klarna_country = false ) {
	$order = wc_get_order( $order_id );
	if ( ! $klarna_country ) {
		$klarna_country = kp_get_klarna_country( $order );
	}

	$klarna_payments_session_id = get_post_meta( $order_id, '_klarna_payments_session_id', true );

	if ( $klarna_payments_session_id ) {
		$response = KP_WC()->api->update_session( $klarna_country, $klarna_payments_session_id, $order_id );
		if ( is_wp_error( $response ) ) {
			$response = KP_WC()->api->create_session( $klarna_country, $order_id );
			if ( is_wp_error( $response ) ) {
				return kp_extract_error_message( $response );
			}
		}
	} else {
		$response = KP_WC()->api->create_session( $klarna_country, $order_id );
		if ( is_wp_error( $response ) ) {
			return kp_extract_error_message( $response );
		}
	}
	update_post_meta( $order_id, '_kp_session_id', $response['session_id'] );
	update_post_meta( $order_id, '_klarna_payments_client_token', $response['client_token'] );
	update_post_meta( $order_id, '_klarna_payments_categories', $response['payment_method_categories'] );
	update_post_meta( $order_id, '_wc_klarna_country', kp_get_klarna_country( $order ) );

	return $response;
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
 * @param WP_Error $response Klarna Payment Response.
 * @return string
 */
function kp_extract_error_message( $response ) {
	$code    = $response->get_error_code();
	$message = $response->get_error_message();
	$text    = __( 'Klarna Payments API Error: ', 'klarna-payments-for-woocommerce' ) . '%s %s';
	return sprintf( $text, $code, $message );
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
 *
 * @param WC_Order|false $order The WooCommerce order.
 */
function kp_get_klarna_country( $order = false ) {
	if ( ! empty( $order ) ) {
		return apply_filters( 'wc_klarna_payments_country', $order->get_billing_country() );
	}

	/* The billing country selected on the checkout page is to prefer over the store's base location. It makse more sense that we check for available payment methods based on the customer's country. */
	if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) && ! empty( WC()->customer->get_billing_country() ) ) {
		return apply_filters( 'wc_klarna_payments_country', WC()->customer->get_billing_country() );
	}

	/* Ignores whatever country the customer selects on the checkout page, and always uses the store's base location. Only used as fallback. */
	$base_location = wc_get_base_location();
	$country       = $base_location['country'];
	return apply_filters( 'wc_klarna_payments_country', $country );
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
	update_post_meta( $order->get_id(), '_payment_method', 'klarna_payments' );
	update_post_meta( $order->get_id(), '_payment_method_title', 'Klarna' );
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


/**
 * Formats the locale to match Klarnas api.
 *
 * @return string
 */
function kp_get_locale() {
	$locale = get_locale();
	// Format exceptions. For example. Finish is returned as fi from WordPress, needs to be formated to fi_fi.
	switch ( $locale ) {
		case 'fi':
			$locale = 'fi_fi';
			break;
		default:
			break;
	}

	return apply_filters( 'kp_locale', substr( str_replace( '_', '-', $locale ), 0, 5 ) );
}

/**
 * Adds customer data to the create session call to KP. Used by payment block.
 *
 * @param array $request_args The request arguments.
 * @param int   $order_id The WooCommerce order id.
 * @return array
 */
function kp_send_customer_data_with_session( $request_args, $order_id ) {
	if ( null === $order_id ) {
		return $request_args;
	}

	$body     = json_decode( $request_args['body'], true );
	$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

	$billing_address  = KP_Customer_Data::get_billing_address( $order_id, $settings['customer_type'] ?? 'b2c' );
	$shipping_address = KP_Customer_Data::get_shipping_address( $order_id, $settings['customer_type'] ?? 'b2c' );

	$body['billing_address']  = $billing_address;
	$body['shipping_address'] = $shipping_address;

	$request_args['body'] = wp_json_encode( $body );

	return $request_args;
}

/**
 * Prints error message to the frotend on api errors.
 *
 * @param WP_Error $wp_error The error response.
 * @return void
 */
function kp_print_error_message( $wp_error ) {
	$error_message = $wp_error->get_error_message();

	if ( is_array( $error_message ) ) {
		// Rather than assuming the first element is a string, we'll force a string conversion instead.
		$error_message = implode( ' ', $error_message );
	}

	if ( is_ajax() ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $error_message, 'error' );
		}
	} else {
		if ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $error_message, 'error' );
		}
	}
}
