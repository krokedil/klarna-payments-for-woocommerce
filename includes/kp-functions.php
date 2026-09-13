<?php
/**
 * Plugin function file.
 *
 * @package WC_Klarna_Payments/Includes
 */

use Krokedil\Klarna\Utilities\ApiCredentialsUtility;
use KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderUtility;
use Automattic\WooCommerce\Utilities\OrderUtil;
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
	/* translators: 1: Klarna API error code, 2: Klarna API error message. [merchant-facing]. */
	$text = __( 'Klarna Payments API Error: %1$s %2$s', 'klarna-payments-for-woocommerce' );
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

	/**
	 * Filters the Klarna customer object added to the request arguments.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-customer-object-for-klarna-payments
	 * @param array  $customer The Klarna customer object.
	 * @param string $customer_type The customer type from the settings.
	 */
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
			/**
			 * Filters the country code used to determine the Klarna market for the customer.
			 *
			 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-purchase-country-sent-to-klarna
			 * @param string $country The two-letter country code.
			 */
			return apply_filters( 'wc_klarna_payments_country', $country );
		}
	}

	/* The billing country selected on the checkout page is to prefer over the store's base location. It makes more sense that we check for available payment methods based on the customer's country. */
	if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) ) {
		$country = WC()->customer->get_billing_country();
		if ( ! empty( $country ) ) {
			/**
			 * Filters the country code used to determine the Klarna market for the customer.
			 *
			 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-purchase-country-sent-to-klarna
			 * @param string $country The two-letter country code.
			 */
			return apply_filters( 'wc_klarna_payments_country', $country );
		}
	}

	/* Ignores whatever country the customer selects on the checkout page, and always uses the store's base location. Only used as fallback. */
	$base_location = wc_get_base_location();
	$country       = $base_location['country'];

	/**
	 * Filters the country code used to determine the Klarna market for the customer.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-purchase-country-sent-to-klarna
	 * @param string $country The two-letter country code.
	 */
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

	$order->update_meta_data( '_wc_klarna_environment', $environment );
	$order->update_meta_data( '_wc_klarna_order_id', $response['order_id'], true );

	kp_save_order_credentials_meta( $order );

	$order->set_transaction_id( $response['order_id'] );
	kp_set_payment_method_title( $order, $response );
	$order->set_payment_method( 'klarna_payments' );

	OrderUtility::add_environment_info( $order, 'woocommerce_klarna_payments_plugin', WC_KLARNA_PAYMENTS_VERSION, null, false );

	$order->save();
}

/**
 * Store which market, and which set of credentials, an order belongs to.
 *
 * With cross border credentials these differ, and order management needs the credential set to authenticate.
 * Does not save the order, the caller is expected to do that.
 *
 * @param WC_Order    $order The WooCommerce order.
 * @param string|null $market The market (customer country) code. Defaults to the orders market.
 * @param string|null $credentials_country The settings country code of the credential set. Resolved if not passed.
 *
 * @return void
 */
function kp_save_order_credentials_meta( $order, $market = null, $credentials_country = null ) {
	$market = empty( $market ) ? kp_get_klarna_country( $order ) : $market;

	// Never re-resolve over a credential set the order was already authorized with. Resolution can pick a
	// different set once Klarna changes what the credentials are granted, and order management would then
	// authenticate against the wrong Klarna account.
	if ( empty( $credentials_country ) ) {
		$credentials_country = $order->get_meta( '_wc_klarna_credentials_country', true );
	}

	if ( empty( $credentials_country ) ) {
		$credentials = ApiCredentialsUtility::resolve( $market, $order->get_currency() );

		if ( ! is_wp_error( $credentials ) ) {
			$credentials_country = $credentials['country_code'];
		}
	}

	// Both metas are written once and then left alone, so the market cannot drift from the credential set it
	// was paired with. Order management would otherwise authenticate against the wrong Klarna account.
	if ( empty( $order->get_meta( '_wc_klarna_country', true ) ) ) {
		// The market, or 'EU' when the combined EU credentials are used.
		$order->update_meta_data( '_wc_klarna_country', strtoupper( ApiCredentialsUtility::get_credentials_country_for_market( $market ) ) );
	}

	// Never overwrite a known credential set with nothing, order management would have nothing to authenticate with.
	if ( ! empty( $credentials_country ) ) {
		$order->update_meta_data( '_wc_klarna_credentials_country', strtolower( $credentials_country ) );
	}
}

/**
 * Get the settings country code of the credential set an order was authorized with.
 *
 * Falls back to the market, for orders created before the credential set was stored separately.
 *
 * @param WC_Order|int $order The WooCommerce order or order id.
 *
 * @return string The settings country code, or an empty string if the order has neither.
 */
function kp_get_order_credentials_country( $order ) {
	$order = is_a( $order, 'WC_Order' ) ? $order : wc_get_order( $order );

	if ( empty( $order ) ) {
		return '';
	}

	$credentials_country = $order->get_meta( '_wc_klarna_credentials_country', true );

	if ( ! empty( $credentials_country ) ) {
		return strtolower( $credentials_country );
	}

	return strtolower( $order->get_meta( '_wc_klarna_country', true ) );
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
	$order_id   = $order->get_id();
	$kp_gateway = new WC_Gateway_Klarna_Payments();
	kp_save_order_meta_data( $order, $decoded );
	$order->payment_complete( $decoded['order_id'] );
	$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded['order_id'] );

	/**
	 * Triggers after an accepted Klarna Payments order has been completed and its meta data stored.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-a-klarna-payment-is-accepted
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $decoded The decoded Klarna order data.
	 */
	do_action( 'wc_klarna_payments_accepted', $order_id, $decoded );

	/**
	 * Alias of wc_klarna_payments_accepted. Fires at the same time with the same arguments for cross-plugin compatibility.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-a-klarna-payment-is-accepted
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $decoded The decoded Klarna order data.
	 */
	do_action( 'wc_klarna_accepted', $order_id, $decoded );

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
	$order_id   = $order->get_id();
	$kp_gateway = new WC_Gateway_Klarna_Payments();
	$order->update_status( 'on-hold', 'Klarna order is under review, order ID: ' . $decoded['order_id'] );
	kp_save_order_meta_data( $order, $decoded );

	/**
	 * Triggers after a pending Klarna Payments order has been set to on-hold for review.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $decoded The decoded Klarna order data.
	 */
	do_action( 'wc_klarna_payments_pending', $order_id, $decoded );

	/**
	 * Alias of wc_klarna_payments_pending. Fires at the same time with the same arguments for cross-plugin compatibility.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $decoded The decoded Klarna order data.
	 */
	do_action( 'wc_klarna_pending', $order_id, $decoded );

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
	$order_id = $order->get_id();
	/**
	 * Filters the WooCommerce order status applied to a rejected Klarna Payments order.
	*
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-order-status-for-rejected-payments
	* @param string $status The order status to set. Default 'failed'.
	*/
	$status = apply_filters( 'kp_order_rejected_status', 'failed' );
	$order->update_status( $status, 'Klarna order was rejected.' );

	/**
	 * Triggers after a rejected Klarna Payments order has had its status updated.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-rejected
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $decoded The decoded Klarna order data.
	 */
	do_action( 'wc_klarna_payments_rejected', $order_id, $decoded );

	/**
	 * Alias of wc_klarna_payments_rejected. Fires at the same time with the same arguments for cross-plugin compatibility.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-rejected
	 * @param int   $order_id The WooCommerce order ID.
	 * @param array $decoded The decoded Klarna order data.
	 */
	do_action( 'wc_klarna_rejected', $order_id, $decoded );

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

	/**
	 * Filters the locale string sent to Klarna, formatted to match the Klarna API.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#force-locale-to-a-specific-country-and-language
	 * @param string $locale The formatted locale, for example 'en-GB'.
	 */
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
 * Has to belong to the same credential set the purchase is authorized with, or the Web SDK renders against
 * different credentials than the session was created with.
 *
 * @param string|null $country The customer country. Defaults to the customers market.
 * @param string|null $currency The purchase currency. Defaults to the current store currency.
 *
 * @return string
 */
function kp_get_client_id( $country = null, $currency = null ) {
	$country = empty( $country ) ? kp_get_klarna_country() : $country;

	if ( ! kp_is_country_available( $country ) ) {
		return '';
	}

	$credentials = ApiCredentialsUtility::resolve( $country, $currency );

	// Fall back to the markets own client id. Nothing can be purchased in that combination, but on site
	// messaging and the interoperability token also use this and only need a client id for the market.
	if ( is_wp_error( $credentials ) ) {
		return klarna_sanitize_client_id( ApiCredentialsUtility::get_client_id( $country ) );
	}

	return klarna_sanitize_client_id( $credentials['client_id'] );
}

/**
 * Sanitize the client id to make sure it is valid.
 * The client id should start with either klarna_live_client_ or klarna_test_client_. If it doesn't, we return an empty string.
 * This is to prevent invalid client ids from being used in the WebSDK.
 *
 * @param string $client_id The client id to sanitize.
 *
 * @return string
 */
function klarna_sanitize_client_id( $client_id ) {
	// If the client id is empty, just return it.
	if ( empty( $client_id ) ) {
		return $client_id;
	}

	// Ensure the client id starts with either klarna_live_client_ or klarna_test_client_. Otherwise return an empty string.
	if ( ! preg_match( '/^(klarna_live_client_|klarna_test_client_).+$/', $client_id ) ) {
		KP_Logger::log( "[Invalid Client ID] The client id '$client_id' is invalid. It should start with either 'klarna_live_client_' or 'klarna_test_client_'. Returning an empty string." );
		return '';
	}

	return $client_id;
}

/**
 * Get the client id based on the currency.
 *
 * @param string|null $currency The currency code to get the client id for, if null the current currency will be used.
 *
 * @return string
 */
function kp_get_client_id_by_currency( $currency = null ) {
	$credentials_country = kp_get_credentials_country_by_currency( $currency );

	if ( empty( $credentials_country ) ) {
		return '';
	}

	// The country code is already a credential set, so combined_eu is false.
	return klarna_sanitize_client_id( ApiCredentialsUtility::get_client_id( $credentials_country, false ) );
}

/**
 * Get the credential set to render the Klarna Web SDK against for a currency.
 *
 * Used where there is a price but no purchase yet, so the customer market is only a hint.
 *
 * @param string|null $currency The currency code, if null the current currency will be used.
 *
 * @return string The settings country code of the credential set, or an empty string if there is none.
 */
function kp_get_credentials_country_by_currency( $currency = null ) {
	$currency = empty( $currency ) ? get_woocommerce_currency() : $currency;
	$market   = kp_get_klarna_country();

	// Prefer the credentials that would serve a purchase in the customers market in this currency.
	if ( kp_is_country_available( $market ) ) {
		$credentials = ApiCredentialsUtility::resolve( $market, $currency );

		if ( ! is_wp_error( $credentials ) ) {
			return $credentials['country_code'];
		}
	}

	// Otherwise fall back to the market whose own currency this is, for pages that only have a price.
	if ( 'EUR' !== $currency ) {
		foreach ( KP_Form_Fields::$kp_form_auto_countries as $cc => $country_data ) {
			if ( $country_data['currency'] === $currency ) {
				return kp_is_country_available( $cc ) ? ApiCredentialsUtility::get_credentials_country_for_market( $cc ) : '';
			}
		}
	}

	// EUR, and any currency without a market of its own, uses the customers market.
	return kp_is_country_available( $market ) ? ApiCredentialsUtility::get_credentials_country_for_market( $market ) : '';
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
		if ( ! empty( ApiCredentialsUtility::get_merchant_id( $country ) ) && ! empty( ApiCredentialsUtility::get_shared_secret( $country ) ) ) {
			return true;
		}

		// The country can also be served by credentials belonging to another country.
		return in_array( strtoupper( $country ), ApiCredentialsUtility::get_serviceable_markets(), true );
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

/**
 * Set the payment method title for a Klarna order.
 *
 * @param WC_Order $order WooCommerce order.
 * @param array    $klarna_place_order_response The Klarna place order response.
 * @return void
 */
function kp_set_payment_method_title( $order, $klarna_place_order_response ) {
	$klarna_order_id = $klarna_place_order_response['order_id'];
	$response        = KP_WC()->api->get_klarna_om_order( $order->get_billing_country(), $klarna_order_id, kp_get_order_credentials_country( $order ) );
	if ( is_wp_error( $response ) || ! isset( $response['initial_payment_method']['description'] ) ) {
		$klarna_method = $klarna_place_order_response['authorized_payment_method']['type'];
		switch ( $klarna_method ) {
			case 'invoice':
				$klarna_method = 'Pay Later';
				break;
			case 'base_account':
				$klarna_method = 'Slice It';
				break;
			case 'direct_debit':
				$klarna_method = 'Direct Debit';
				break;
			default:
				$klarna_method = null;
		}
	} else {
		$klarna_method = $response['initial_payment_method']['description'];
	}

	$title = $order->get_payment_method_title();
	$title = empty( $title ) ? 'Klarna' : $title;

	if ( $klarna_method ) {
		$order->update_meta_data( '_kp_payment_method', $klarna_method );
		$title = "$title - $klarna_method";
	}

	$order->set_payment_method_title( $title );
}

/**
 * Whether the "Combine payment methods" setting is enabled, meaning Klarna should be displayed as a
 * single payment method in the checkout instead of one payment method per Klarna payment category.
 *
 * @return bool
 */
function kp_is_combined_payment_methods_enabled() {
	$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

	return 'yes' === ( $settings['combine_payment_methods'] ?? 'no' );
}

/**
 * Get the title for the combined Klarna payment method displayed in the checkout.
 *
 * @return string
 */
function kp_get_combined_payment_method_title() {
	/* translators: [customer-facing]. */
	$title = __( 'Pay with Klarna', 'klarna-payments-for-woocommerce' );

	/**
	 * Filters the title of the combined Klarna payment method displayed in the checkout.
	 *
	 * @param string $title The combined Klarna payment method title.
	 */
	return apply_filters( 'wc_klarna_payments_combined_payment_method_title', $title );
}

/**
 * Whether HPOS is enabled.
 *
 * @return bool
 */
function kp_is_hpos_enabled() {
	if ( class_exists( OrderUtil::class ) ) {
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}
	return false;
}

/**
 * Equivalent to WP's get_the_ID() with HPOS support.
 *
 * @return int|false|int the order ID if the ID exist, otherwise false or zero.
 */
   //phpcs:ignore
  function kp_get_the_ID() {
	$hpos_enabled = kp_is_hpos_enabled();
	$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
	if ( empty( $order_id ) ) {
		$order_id = filter_input( INPUT_POST, 'post', FILTER_SANITIZE_NUMBER_INT );
	}
	return empty( $order_id ) ? false : absint( $order_id );
}

/**
 * Get the customer type for Klarna Payments.
 *
 * @param string $customer_type The customer type from the settings.
 * @return string
 */
function klarna_get_customer_type( $customer_type ) {
	/**
	 * Filters the customer type used for Klarna Payments.
	 *
	 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-customer-type-b2cb2b
	 * @param string $customer_type The customer type from the settings.
	 */
	return apply_filters( 'klarna_get_customer_type', $customer_type );
}
