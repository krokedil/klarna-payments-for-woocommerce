<?php
/**
 * Plugin function file.
 *
 * @package WC_Klarna_Payments/Includes
 */

/**
 * Unsets all Klarna Payments sessions.
 */
function kp_unset_session_values() {
	if ( ! WC()->session ) {
		return;
	}

	WC()->session->__unset( 'kp_session_data' );
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
 * @return string
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
 * Process authorization or callback response for accepted or pending Klarna orders.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $decoded Klarna authorization or callback response.
 *
 * @return void
 */
function kp_process_auth_or_callback( $order, $response ) {

	$environment = 'yes' === get_option( 'woocommerce_klarna_payments_settings' )['testmode'] ? 'test' : 'live';

	$order->update_meta_data( '_wc_klarna_environment', $environment );
	$order->update_meta_data( '_wc_klarna_country', kp_get_klarna_country( $order ) );
	$order->update_meta_data( '_wc_klarna_order_id', $response['order_id'], true );
	$order->set_transaction_id( $response['order_id'] );
	$order->set_payment_method_title( 'Klarna' );

	$order->save();
}

/**
 * Process accepted Klarna Payments order.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $decoded Klarna order.
 * @param string|bool   $recurring_token Recurring token.
 *
 * @return array   $result  Payment result.
 */
function kp_process_accepted( $order, $decoded, $recurring_token = false ) {
	$kp_gateway = new WC_Gateway_Klarna_Payments();
	$order->payment_complete( $decoded['order_id'] );
	$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded['order_id'] );
	kp_process_auth_or_callback( $order, $decoded );

	if ( $recurring_token ) {
		KP_Subscription::save_recurring_token( $order->get_id(), $recurring_token );
	}

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
	kp_process_auth_or_callback( $order, $decoded );
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

	if ( is_ajax() || defined( 'REST_REQUEST' ) ) { // If ajax or rest request. Add notice instead of print.
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $error_message, 'error' );
		}
	} else {
		if ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $error_message, 'error' );
		}
	}
}

/**
 * Returns if Klarna payments is an available gateway from the WC()->paymnet_gateways->get_available_payment_gateways() array.
 *
 * @return bool
 */
function kp_is_available() {
	$available_payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	return isset( $available_payment_gateways['klarna_payments'] );
}

/**
 * Checks if the current page contains the WooCommerce checkout block.
 *
 * @return bool
 */
function kp_is_checkout_blocks_page() {
	// Get the post from WordPress.
	$post      = get_post();
	$has_block = has_block( 'woocommerce/checkout', $post );

	return $has_block;
}

/**
 * Returns if the current page is the checkout page or not. Includes if we are on a pay for order page, but not if we are on a thank you page.
 *
 * @return bool
 */
function kp_is_checkout_page() {
	return ( is_checkout() || is_wc_endpoint_url( 'order-pay' ) ) && ! is_wc_endpoint_url( 'order-received' );
}

/**
 * Returns if we are on a order pay page or not.
 *
 * @return bool
 */
function kp_is_order_pay_page() {
	return is_wc_endpoint_url( 'order-pay' );
}

/**
 * Returns if the order was created using the checkout block or not.
 *
 * @param WC_Order $order The WooCommerce order.
 * @return bool
 */
function kp_is_wc_blocks_order( $order ) {
	return $order && $order->is_created_via( 'store-api' );
}
