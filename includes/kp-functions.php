<?php
/**
 * Plugin function file.
 *
 * @package WC_Klarna_Payments/Includes
 */

use KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderUtility;

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
	$type     = ( 'b2b' === $customer_type ) ? 'organization' : 'person';
	$customer = array(
		'type' => $type,
	);

	$access_token = KP_WC()->siwk->user->get_access_token( get_current_user_id() );
	if ( ! empty( $access_token ) ) {
		$customer['klarna_access_token'] = $access_token;
	}

	return apply_filters( 'kp_get_customer_type', $customer, $customer_type );
}

/**
 * Gets Klarna country.
 *
 * @param WC_Order|false $order The WooCommerce order.
 * @return string
 */
function kp_get_klarna_country( $order = false ) {
	if ( ! empty( $order ) ) {
		$country = $order->get_billing_country();

		// If the billing_country field is unset, $country will be empty.
		if ( ! empty( $country ) ) {
			return apply_filters( 'wc_klarna_payments_country', $country );
		}
	}

	/* The billing country selected on the checkout page is to prefer over the store's base location. It makes more sense that we check for available payment methods based on the customer's country. */
	if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) ) {
		$country = WC()->customer->get_billing_country();
		if ( ! empty( $country ) ) {
			return apply_filters( 'wc_klarna_payments_country', $country );
		}
	}

	/* Ignores whatever country the customer selects on the checkout page, and always uses the store's base location. Only used as fallback. */
	$base_location = wc_get_base_location();
	$country       = $base_location['country'];
	return apply_filters( 'wc_klarna_payments_country', $country );
}

/**
 * Process the response from a Klarna request to store meta data about an order.
 *
 * Also used for processing authorization or callback response for accepted or pending Klarna orders.
 *
 * @param WC_Order $order The WooCommerce order.
 * @param array    $response Response from Klarna request that contain order details.
 *
 * @return void
 */
function kp_save_order_meta_data( $order, $response ) {
	$settings    = get_option( 'woocommerce_klarna_payments_settings', array() );
	$testmode    = wc_string_to_bool( $settings['testmode'] ?? 'no' );
	$environment = $testmode ? 'test' : 'live';

	$klarna_country = kp_get_klarna_country( $order );

	$settings = get_option( 'woocommerce_klarna_payments_settings', array() );
	// If EU credentials are combined, we should use the EU country code.
	$combined_eu = 'yes' === ( isset( $settings['combine_eu_credentials'] ) ? $settings['combine_eu_credentials'] : 'no' );
	if ( $combined_eu && key_exists( strtolower( $klarna_country ), KP_Form_Fields::available_countries( 'eu' ) ) ) {
		$klarna_country = 'EU';
	}

	$order->update_meta_data( '_wc_klarna_environment', $environment );
	$order->update_meta_data( '_wc_klarna_country', $klarna_country );
	$order->update_meta_data( '_wc_klarna_order_id', $response['order_id'], true );
	$order->set_transaction_id( $response['order_id'] );
	$order->set_payment_method_title( 'Klarna' );
	$order->set_payment_method( 'klarna_payments' );

	OrderUtility::add_environment_info( $order, WC_KLARNA_PAYMENTS_VERSION, null, false );

	$order->save();
}

/**
 * Process accepted Klarna Payments order.
 *
 * @param WC_Order    $order WooCommerce order.
 * @param array       $decoded Klarna order.
 * @param string|bool $recurring_token Recurring token.
 *
 * @return array   $result  Payment result.
 */
function kp_process_accepted( $order, $decoded, $recurring_token = false ) {
	$kp_gateway = new WC_Gateway_Klarna_Payments();
	$order->payment_complete( $decoded['order_id'] );
	$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded['order_id'] );
	kp_save_order_meta_data( $order, $decoded );

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
	kp_save_order_meta_data( $order, $decoded );
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
	$status = apply_filters( 'kp_order_rejected_status', 'failed' );
	$order->update_status( $status, 'Klarna order was rejected.' );
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
	// Format exceptions. For example. Finish is returned as fi from WordPress, needs to be formatted to fi_fi.
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
	} elseif ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $error_message, 'error' );
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
	return $order && is_a( $order, WC_Order::class ) && $order->is_created_via( 'store-api' );
}

/**
 * Get the client id for Klarna Payments from the settings based on the customer country.
 *
 * @param string|null $country The customer country.
 *
 * @return string
 */
function kp_get_client_id( $country = null ) {
	$country  = strtolower( $country ? $country : kp_get_klarna_country() );
	$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

	$eu_combined = 'yes' === ( isset( $settings['combine_eu_credentials'] ) ? $settings['combine_eu_credentials'] : 'no' );
	$test_mode   = 'yes' === ( isset( $settings['testmode'] ) ? $settings['testmode'] : 'no' );

	if ( ! kp_is_country_available( $country ) ) {
		return '';
	}

	// If the country is in the EU and the EU combined setting is enabled, we should use the EU combined client id.
	if ( $eu_combined && key_exists( $country, KP_Form_Fields::available_countries( 'eu' ) ) ) {
		$country = 'eu';
	}
	$prefix      = $test_mode ? 'test_' : '';
	$setting_key = "{$prefix}client_id_{$country}";
	return $settings[ $setting_key ] ?? '';
}

/**
 * Get the client id based on the currency.
 *
 * @param string|null $currency The currency code to get the client id for, if null the current currency will be used.
 *
 * @return string
 */
function kp_get_client_id_by_currency( $currency = null ) {
	if ( empty( $currency ) ) {
		$currency = get_woocommerce_currency();
	}

	$country = null;
	// If the currency is EUR, we should maybe get the client id based on the locale for the customer.
	if ( 'EUR' === $currency ) {
		$country = kp_get_klarna_country();
	} else {
		foreach ( KP_Form_Fields::$kp_form_auto_countries as $cc => $country_data ) {
			if ( $country_data['currency'] === $currency ) {
				$country = $cc;
				break;
			}
		}
	}

	return kp_get_client_id( $country );
}

/**
 * Check if the country is available for Klarna Payments.
 *
 * @param string $country The country code.
 *
 * @return bool
 */
function kp_is_country_available( $country ) {
	$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

	/**
	 * Get the available countries from the settings. This is actually an array, even if the method says it's a string.
	 *
	 * @var array $available_countries The available countries.
	 */
	$available_countries = $settings['available_countries'] ?? array();

	$country = strtolower( $country );
	if ( empty( $available_countries ) ) {
		// See if the country has values saved from the old settings, before the available countries setting was added.
		$testmode = wc_string_to_bool( $settings['testmode'] ?? 'no' );
		$prefix   = $testmode ? 'test_' : '';

		// If the country is a EU country, check if we are using the combined EU credentials.
		if ( key_exists( $country, KP_Form_Fields::available_countries( 'eu' ) ) ) {
			$eu_combined = 'yes' === ( $settings['combine_eu_credentials'] ?? 'no' );
			if ( $eu_combined ) {
				$country = 'eu';
			}
		}

		$merchant_id = $settings[ "{$prefix}merchant_id_{$country}" ] ?? '';
		$secret      = $settings[ "{$prefix}shared_secret_{$country}" ] ?? '';

		// If we have the merchant id and secret, the country is available.
		if ( ! empty( $merchant_id ) && ! empty( $secret ) ) {
			return true;
		}

		return false;
	}

	$is_available = in_array( $country, $available_countries, true );
	return $is_available;
}

/**
 * Get the ids of the features that are not available for the given country credentials.
 *
 * @param array $country_credentials The country credentials.
 *
 * @return array
 */
function kp_get_unavailable_feature_ids( $country_credentials ) {
	$collected_errors   = array();
	$collected_features = array();

	foreach ( $country_credentials as $credentials ) {
		$settings_features = KP_WC()->api->get_unavailable_features( $credentials );

		if ( is_wp_error( $settings_features ) ) {
			$collected_errors[] = 'Error for credential country ' . $credentials['country_code'] . ': ' . kp_extract_error_message( $settings_features );
			continue;
		}
		$collected_features = array_merge(
			$collected_features,
			array_map(
				function ( $feature ) {
					return array(
						'feature_key'  => $feature['feature_key'],
						'availability' => $feature['availability'],
					);
				},
				$settings_features['features']
			)
		);
	}

	return array(
		'feature_ids' => kp_map_unavailable_features( $collected_features ),
		'errors'      => $collected_errors,
	);
}

/**
 * Maps the features that are not available to the feature ids that should be hidden.
 *
 * @param array $collected_features The collected features.
 *
 * @return array
 */
function kp_map_unavailable_features( $collected_features ) {

	$features = array(
		'platform-plugin-payments'                => array(
			'id'     => 'general',
			'status' => null,
		),
		'platform-plugin-on-site-messaging'       => array(
			'id'     => 'onsite_messaging',
			'status' => null,
		),
		'platform-plugin-klarna-express-checkout' => array(
			'id'     => 'kec_settings',
			'status' => null,
		),
		'platform-plugin-sign-in-with-klarna'     => array(
			'id'     => 'siwk',
			'status' => null,
		),
	);

	foreach ( $collected_features as $collected_feature ) {
		$feature_category = explode( ':', $collected_feature['feature_key'] )[0];

		if ( ! isset( $features[ $feature_category ] ) ) {
			continue;
		}

		if ( null === $features[ $feature_category ]['status'] ) {
			$features[ $feature_category ]['status'] = false;
		}

		if ( 'AVAILABLE' === $collected_feature['availability'] ) {
			$features[ $feature_category ]['status'] = true;
		}
	}

	// Filter out the features that are not available.
	$unavailable_features = array_filter(
		$features,
		function ( $feature ) {
			return false === $feature['status'];
		}
	) ?? array();

	// Return the identifying feature ids, of the features that should be hidden.
	$unavailable_features = array_values(
		array_map(
			function ( $feature ) {
				return $feature['id'];
			},
			$unavailable_features
		)
	);

	// If KP is unavailable, we should also hide the Klarna Order Management feature.
	if ( in_array( 'general', $unavailable_features, true ) ) {
		$unavailable_features[] = 'kom';
	}

	return $unavailable_features;
}
