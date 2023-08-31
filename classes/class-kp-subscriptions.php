<?php //phpcs:ignore
/**
 * Class for handling WC subscriptions.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling subscriptions.
 */
class KP_Subscription {

	public const GATEWAY_ID      = 'klarna_payments';
	public const RECURRING_TOKEN = '_' . self::GATEWAY_ID . '_recurring_token';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, array( $this, 'process_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID, array( $this, 'cancel_scheduled_payment' ) );

		// Set the purchase intent to 'tokenize' for trial subscriptions. The 'buy_and_tokenize' intent is not allowed for 0 order amounts.
		add_filter( 'wc_klarna_payments_create_session_args', array( $this, 'set_tokenize_intent' ) );
		add_filter( 'wc_klarna_payments_place_order_args', array( $this, 'set_tokenize_intent' ) );
		add_filter( 'wc_klarna_payments_create_customer_token_args', array( $this, 'set_tokenize_intent' ) );
		add_filter( 'wc_klarna_payments_update_session_args', array( $this, 'set_tokenize_intent' ) );

		// For free or trial subscription, we set the order as captured to prevent KOM from setting the order to on-hold when the merchant set the order to "Completed".
		add_filter( 'woocommerce_payment_complete', array( $this, 'set_subscription_as_captured' ) );

		// Override the redirect URLs to redirect back to the change payment method page on failure or to the subscription view on success.
		add_filter( 'wc_klarna_payments_create_hpp_args', array( $this, 'set_subscription_order_redirect_urls' ) );
		// Override the subscription cost when change payment method.
		add_filter( 'wc_klarna_payments_create_session_args', array( $this, 'set_subscription_to_free' ) );
		// On successful payment method change, the customer is redirected back to the subscription view page. We need to handle the redirect and create a recurring token.
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'handle_redirect_from_change_payment_method' ) );

		// Show the recurring token on the subscription page in the billing fields.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_recurring_token' ) );
		// Ensure wp_safe_redirect do not redirect back to default dashboard or home page.
		add_filter( 'allowed_redirect_hosts', array( $this, 'extend_allowed_domains_list' ) );

	}

	/**
	 * Flags a free or trial subscription parent order as captured.
	 *
	 * This is required to prevent Klarna Order Management from attempting to process the order for capture when the customer sets the order to completed as there is nothing to capture.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return int WooCommerce order ID.
	 */
	public function set_subscription_as_captured( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( self::GATEWAY_ID !== $order->get_payment_method() ) {
			return $order_id;
		}

		if ( self::order_has_subscription( $order ) && 0.0 === floatval( $order->get_total() ) ) {
			$order->update_meta_data( '_wc_klarna_capture_id', 'trial' );
			$order->save();
		}

		return $order_id;
	}

	/**
	 * Process subscription renewal.
	 *
	 * @param float    $amount_to_charge
	 * @param WC_Order $renewal_order The WooCommerce order that will be created as a result of the renewal.
	 * @return void
	 */
	public function process_scheduled_payment( $amount_to_charge, $renewal_order ) {
		$recurring_token = $this->get_recurring_tokens( $renewal_order->get_id() );

		$response = KP_WC()->api->create_recurring_order( kp_get_klarna_country( $renewal_order ), $recurring_token, $renewal_order->get_id() );
		if ( ! is_wp_error( $response ) ) {
			$klarna_order_id = $response['order_id'];
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment made with Klarna. Klarna order id: %s', 'klarna-payments-for-woocommerce' ), $klarna_order_id ) );
			self::save_order_meta_data( $renewal_order, $response );
		} else {
			$error_message = $response->get_error_message();
			// Translators: Error message.
			$renewal_order->add_order_note( sprintf( __( 'Subscription payment failed with Klarna. Reason: %1$s', 'klarna-payments-for-woocommerce' ), $error_message ) );
		}

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		foreach ( $subscriptions as $subscription ) {
			if ( isset( $klarna_order_id ) ) {
				$subscription->payment_complete( $klarna_order_id );
			} else {
				$subscription->payment_failed();
			}

			// Save to the subscription.
			self::save_recurring_token( $subscription->get_id(), $recurring_token );
		}

		// Save to the WC order.
		self::save_recurring_token( $renewal_order->get_id(), $recurring_token );

	}

	/**
	 * Cancel the customer token to prevent further payments using the token.
	 *
	 * Note: When changing payment method, WC Subscriptions will cancel the subscription with existing payment gateway (which triggers this functions), and create a new one. Thus the new subscription must generate a new customer token.
	 *
	 * @see WC_Subscriptions_Change_Payment_Gateway::update_payment_method
	 *
	 * @param mixed $subscription WC_Subscription
	 * @return void
	 */
	public function cancel_scheduled_payment( $subscription ) {
		$recurring_token = $this->get_recurring_tokens( $subscription->get_id() );

		$response = KP_WC()->api->cancel_recurring_order( kp_get_klarna_country( $subscription ), $recurring_token );
		if ( ! is_wp_error( $response ) ) {
			$subscription->add_order_note( __( 'Subscription cancelled with Klarna Payments.', 'klarna-payments-for-woocommerce' ) );
		} else {
			$error_message = $response->get_error_message();
			// Translators: Error message.
			$subscription->add_order_note( sprintf( __( 'Subscription cancellation failed with Klarna Payments. Reason: %1$s', 'klarna-payments-for-woocommerce' ), $error_message ) );
		}

		// The session data must be deleted since Klarna doesn't allow reusing a session when generating a new customer token to change payment method.
		$subscription->delete_meta_data( '_kp_session_data' );
		$subscription->save();

	}

	/**
	 * Set the redirect URLs for the hosted payment page.
	 *
	 * Used for changing payment method.
	 *
	 * @param array $request The Klarna request.
	 * @return array
	 */
	public function set_subscription_order_redirect_urls( $request ) {
		if ( ! self::is_change_payment_method() ) {
			return $request;
		}

		$key          = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS );
		$order_id     = wc_get_order_id_by_order_key( $key );
		$subscription = wc_get_order( $order_id );
		$body         = json_decode( $request['body'], true );

		$success_url           = add_query_arg(
			array(
				'authorization_token' => '{{authorization_token}}',
			),
			$subscription->get_view_order_url()
		);
		$body['merchant_urls'] = array(
			'success' => $success_url,
			'cancel'  => $subscription->get_change_payment_method_url(),
			'back'    => $subscription->get_change_payment_method_url(),
			'failure' => $subscription->get_change_payment_method_url(),
			'error'   => $subscription->get_change_payment_method_url(),
		);

		$request['body'] = wp_json_encode( $body );
		return $request;
	}

	/**
	 * Handle the redirect from the hosted payment page.
	 *
	 * Used for changing payment method.
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return void
	 */
	public function handle_redirect_from_change_payment_method( $subscription_id ) {
		$auth_token = filter_input( INPUT_GET, 'authorization_token', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! isset( $auth_token ) ) {
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );
		$response     = KP_WC()->api->create_customer_token( kp_get_klarna_country( $subscription ), $auth_token, $subscription_id );
		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				/* translators: Error message. */
				__( 'Failed to create recurring token. Reason: %s', 'klarna-payments-for-woocommerce' ),
				$response->get_error_message()
			);
		} else {
			$message = sprintf(
			/* translators: Recurring token. */
				__( 'Recurring token created: %s', 'klarna-payments-for-woocommerce' ),
				$response['token_id']
			);

			self::save_recurring_token( $subscription_id, $response['token_id'] );
		}

		$subscription->add_order_note( $message );
		$subscription->save();
	}

	/**
	 * Set the subscription cost to 0.
	 * This is required when changing payment method.
	 *
	 * @param array $request The Klarna request.
	 * @return array
	 */
	public function set_subscription_to_free( $request ) {
		if ( ! self::is_change_payment_method() ) {
			return $request;
		}

		$body = json_decode( $request['body'], true );
		foreach ( $body['order_lines'] as $item => $order_line ) {
			$body['order_lines'][ $item ]['unit_price']   = 0;
			$body['order_lines'][ $item ]['total_amount'] = 0;
		}

		// 0 order amounts are allowed if the purchase intent is 'tokenize'. On the intent 'buy_and_tokenize' 0 order amounts are not allowed.
		$body['intent'] = 'tokenize';

		$request['body'] = wp_json_encode( $body );
		return $request;
	}


	/**
	 * Set the purchase intent to 'tokenize' for trial subscriptions.
	 *
	 * The 'buy_and_tokenize' intent is not allowed for 0 order amounts.
	 *
	 * @param array $request The Klarna request.
	 * @return array
	 */
	public function set_tokenize_intent( $request ) {
		$body = json_decode( $request['body'], true );

		if ( self::cart_has_subscription() ) {
			$body['intent'] = 'buy_and_tokenize';

			// Only allow free orders if the cart contains a subscription (not limited to trial subscription as a subscription can become free if a 100% discount coupon is applied).
			if ( 0.0 === floatval( $body['order_amount'] ) ) {
				$body['intent'] = 'tokenize';
			}

			$request['body'] = wp_json_encode( $body );
		}

		return $request;
	}

	/**
	 * Save the payment and recurring token to the order and its subscription(s).
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @param string $recurring_token The recurring token ("customer token").
	 * @return void
	 */
	public static function save_recurring_token( $order_id, $recurring_token ) {
		$order = wc_get_order( $order_id );
		$order->update_meta_data( self::RECURRING_TOKEN, $recurring_token );

		foreach ( wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) ) as $subscription ) {
			$subscription->update_meta_data( self::RECURRING_TOKEN, $recurring_token );
			$subscription->save();
		}

		$order->save();
	}

	/**
	 * Retrieve the necessary tokens required for subscriptions (unattended) payments.
	 *
	 * @param  int $order_id The WooCommerce order id.
	 * @return string The recurring token. If none is found, an empty string is returned.
	 */
	public static function get_recurring_tokens( $order_id ) {
		$order           = wc_get_order( $order_id );
		$recurring_token = $order->get_meta( self::RECURRING_TOKEN );

		if ( empty( $recurring_token ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			foreach ( $subscriptions as $subscription ) {
				$parent_order    = $subscription->get_parent();
				$recurring_token = $parent_order->get_meta( self::RECURRING_TOKEN );

				if ( ! empty( $recurring_token ) ) {
					break;
				}
			}
		}

		return $recurring_token;
	}

	/**
	 * Get a subscription's parent order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return WC_Order|false The parent order or false if none is found.
	 */
	public static function get_parent_order( $order_id ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		foreach ( $subscriptions as $subscription ) {
			$parent_order = $subscription->get_parent();
			return $parent_order;
		}

		return false;
	}

	/**
	 * Check if the current request is for changing the payment method.
	 *
	 * @return bool
	 */
	public static function is_change_payment_method() {
		return isset( $_GET['change_payment_method'] );
	}

	/**
	 * Process the response from a Klarna request to store meta data about an order.
	 *
	 * @param WC_Order $renewal_order The WooCommerce order.
	 * @param array    $response Response from Klarna request that contain order details.
	 *
	 * @return void
	 */
	public static function save_order_meta_data( $order, $response ) {
		$environment = 'yes' === get_option( 'woocommerce_klarna_payments_settings' )['testmode'] ? 'test' : 'live';

		$order->update_meta_data( '_wc_klarna_environment', $environment );
		$order->update_meta_data( '_wc_klarna_country', kp_get_klarna_country( $order ) );
		$order->update_meta_data( '_wc_klarna_order_id', $response['order_id'], true );
		$order->set_transaction_id( $response['order_id'] );
		$order->set_payment_method_title( 'Klarna' );

		$order->save();
	}

	/**
	 * Check if an order contains a subscription.
	 *
	 * @param WC_Order $order The WooCommerce order or leave empty to use the cart (default).
	 * @return bool
	 */
	public static function order_has_subscription( $order ) {
		if ( empty( $order ) ) {
			return false;
		}

		return function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) );
	}

	/**
	 * Check if a cart contains a subscription.
	 *
	 * @return bool
	 */
	public static function cart_has_subscription() {
		if ( ! is_checkout() ) {
			return false;
		}

		return ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) || ( function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) && wcs_cart_contains_failed_renewal_order_payment() );
	}

	/**
	 * Add Klarna hosted payment page as allowed external url for wp_safe_redirect.
	 * We do this because WooCommerce Subscriptions use wp_safe_redirect when processing a payment method change request (from v5.1.0).
	 *
	 * @param array $hosts Domains that are allowed when wp_safe_redirect is used.
	 * @return array
	 */
	public function extend_allowed_domains_list( $hosts ) {
		$hosts[] = 'pay.playground.klarna.com';
		$hosts[] = 'pay.klarna.com';
		return $hosts;
	}

	/**
	 * Shows the recurring token for the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function show_recurring_token( $order ) {
		if ( 'shop_subscription' === $order->get_type() && $order->get_meta( self::RECURRING_TOKEN ) ) {
			?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<p>
						<strong><?php echo esc_html( 'Klarna recurring token' ); ?>:</strong><?php echo esc_html( $order->get_meta( self::RECURRING_TOKEN ) ); ?>
					</p>
				</div>
				<div class="edit_address">
				<?php
					woocommerce_wp_text_input(
						array(
							'id'            => self::RECURRING_TOKEN,
							'label'         => __( 'Klarna recurring token', 'klarna-checkout-for-woocommerce' ),
							'wrapper_class' => '_billing_company_field',
						)
					);
				?>
				</div>
			</div>
				<?php
		}
	}
}
