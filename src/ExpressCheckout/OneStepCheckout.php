<?php

namespace Krokedil\Klarna\ExpressCheckout;

use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;

defined( 'ABSPATH' ) || exit;

/**
 * One-step checkout logic for Klarna Express Checkout.
 */
class OneStepCheckout {
	/**
	 * The handle of the script that waits for Klarna's payment confirmation.
	 *
	 * @var string
	 */
	private const WAIT_SCRIPT_HANDLE = 'kec-one-step-wait';

	/**
	 * The order the current request is waiting for a payment confirmation for.
	 *
	 * @var \WC_Order|false
	 */
	private static $waiting_for_order = false;

	/**
	 * The KEC unique ID the current request is waiting for a payment confirmation for.
	 *
	 * @var string
	 */
	private static $waiting_for_unique_id = '';

	/**
	 * Register the hooks needed for the one-step checkout flow.
	 *
	 * @param string $flow The selected KEC flow.
	 *
	 * @return void
	 */
	public static function register_hooks( $flow ) {
		if ( 'one_step' !== $flow || ! PluginFeatures::is_available( Features::KEC_ONE_STEP ) ) {
			return;
		}

		add_action( 'init', __CLASS__ . '::maybe_handle_kec_one_step_return' );
	}

	/**
	 * Handle the customer's return from a one-step checkout.
	 *
	 * @return void
	 */
	public static function maybe_handle_kec_one_step_return() {
		$kec_unique_id = filter_input( INPUT_GET, 'kec-one-step', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $kec_unique_id ) ) {
			return;
		}

		$is_poll = ! empty( filter_input( INPUT_GET, 'kec-one-step-poll', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$order   = self::is_own_unique_id( $kec_unique_id ) ? self::get_order_by_unique_id( $kec_unique_id ) : false;

		if ( ! $order ) {
			// A poll is answered to a page the customer is already on, so tell it to stop rather than redirect it away.
			if ( $is_poll ) {
				self::answer_redirect_poll( '', true );
			}

			self::abort_redirect();
		}

		// The order has been handed over to Klarna, so a later checkout in this session must not pick it up again.
		if ( WC()->session ) {
			WC()->session->__unset( 'kec_one_step_order_id' );
		}

		$redirect_url = $order->get_meta( '_kec_redirect_url' );

		if ( $is_poll ) {
			self::answer_redirect_poll( $redirect_url, self::is_final_poll() );
		}

		if ( ! empty( $redirect_url ) ) {
			self::unset_sessions();
			wp_safe_redirect( $redirect_url );
			exit;
		}

		self::wait_for_confirmation( $order, $kec_unique_id );
	}

	/**
	 * Check that the reference in the return URL is the one issued to the current shopper's session.
	 *
	 * @param string $kec_unique_id The KEC unique ID from the return URL.
	 *
	 * @return bool
	 */
	private static function is_own_unique_id( $kec_unique_id ) {
		$session_unique_id = WC()->session ? WC()->session->get( 'kec_one_step_unique_id' ) : '';

		return ! empty( $session_unique_id ) && hash_equals( $session_unique_id, $kec_unique_id );
	}

	/**
	 * Get the order the KEC unique ID was issued for.
	 *
	 * @param string $kec_unique_id The KEC unique ID.
	 *
	 * @return \WC_Order|false The order, or false if no recent order matches the ID.
	 */
	private static function get_order_by_unique_id( $kec_unique_id ) {
		$args = array(
			'limit'        => 1,
			'meta_key'     => '_kec_unique_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => $kec_unique_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '=',
			'created_via'  => 'klarna_express_checkout',
			'date_created' => '>' . ( time() - ( \DAY_IN_SECONDS * 2 ) ),
		);

		$orders = wc_get_orders( $args );

		if ( empty( $orders ) ) {
			return false;
		}

		$order = reset( $orders );

		return $order->get_meta( '_kec_unique_id' ) === $kec_unique_id ? $order : false;
	}

	/**
	 * Send the customer back to the cart with a generic error notice.
	 *
	 * @return never
	 */
	private static function abort_redirect() {
		self::unset_sessions();
		wc_add_notice( __( 'Your order could not be processed', 'klarna-payments-for-woocommerce' ) );
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Clear one-step session data.
	 *
	 * @return void
	 */
	public static function unset_sessions() {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->__unset( 'kec_one_step_unique_id' );
		WC()->session->__unset( 'kec_one_step_order_id' );
	}

	/**
	 * Answer the waiting browser with the redirect URL Klarna's confirmation left on the order.
	 *
	 * @param string $redirect_url The redirect URL, or an empty string while the confirmation is still outstanding.
	 * @param bool   $is_final     Whether this is the last time the browser will ask.
	 *
	 * @return never
	 */
	private static function answer_redirect_poll( $redirect_url, $is_final ) {
		$done = ! empty( $redirect_url ) || $is_final;

		// Once the browser stops waiting, the session has served its purpose whether the confirmation arrived or not.
		if ( $done ) {
			self::unset_sessions();
		}

		nocache_headers();
		wp_send_json_success(
			array(
				'redirect_url' => $redirect_url,
				'done'         => $done,
			)
		);
	}

	/**
	 * Check whether the browser has used up the attempts it was given to wait for the confirmation.
	 *
	 * @return bool
	 */
	private static function is_final_poll() {
		$attempt = absint( filter_input( INPUT_GET, 'kec-one-step-attempt', FILTER_SANITIZE_NUMBER_INT ) );

		return $attempt >= self::get_wait_max_attempts();
	}

	/**
	 * Let the customer wait for Klarna's confirmation on the page they are headed for anyway.
	 *
	 * The order received page is rendered by WordPress as usual, and the waiting is added to it, so that the theme,
	 * its styling and anything else the merchant runs on the page is kept intact while the confirmation is pending.
	 *
	 * @param \WC_Order $order         The WooCommerce order.
	 * @param string    $kec_unique_id The KEC unique ID.
	 *
	 * @return void Exits if the customer still has to be sent to that page.
	 */
	private static function wait_for_confirmation( $order, $kec_unique_id ) {
		if ( empty( filter_input( INPUT_GET, 'kec-one-step-wait', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'kec-one-step'      => $kec_unique_id,
						'kec-one-step-wait' => '1',
					),
					self::get_default_redirect_url( $order, $kec_unique_id )
				)
			);
			exit;
		}

		nocache_headers();

		self::$waiting_for_order     = $order;
		self::$waiting_for_unique_id = $kec_unique_id;

		add_action( 'wp_enqueue_scripts', __CLASS__ . '::enqueue_wait_assets' );
		add_action( 'wp_head', __CLASS__ . '::render_wait_noscript_refresh' );
		add_action( 'woocommerce_before_thankyou', __CLASS__ . '::render_wait_notice' );
	}

	/**
	 * Enqueue the script that waits for Klarna's payment confirmation.
	 *
	 * @return void
	 */
	public static function enqueue_wait_assets() {
		$order = self::$waiting_for_order;

		if ( ! $order ) {
			return;
		}

		wp_register_script(
			self::WAIT_SCRIPT_HANDLE,
			Assets::get_assets_path() . 'js/kec-one-step-wait.js',
			array(),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);

		wp_localize_script(
			self::WAIT_SCRIPT_HANDLE,
			'kec_one_step_wait_params',
			array(
				'poll_url'     => add_query_arg(
					array(
						'kec-one-step'      => self::$waiting_for_unique_id,
						'kec-one-step-poll' => '1',
					),
					home_url()
				),
				'fallback_url' => self::get_default_redirect_url( $order, self::$waiting_for_unique_id ),
				'max_attempts' => self::get_wait_max_attempts(),
				'interval'     => self::get_wait_interval(),
			)
		);

		wp_enqueue_script( self::WAIT_SCRIPT_HANDLE );
	}

	/**
	 * Print the notice telling the customer that the payment is still being confirmed.
	 *
	 * @param int $order_id The ID of the order the page is rendered for.
	 *
	 * @return void
	 */
	public static function render_wait_notice( $order_id = 0 ) {
		$order = self::$waiting_for_order;

		if ( ! $order || ( ! empty( $order_id ) && $order->get_id() !== absint( $order_id ) ) ) {
			return;
		}

		wc_get_template(
			'kec-one-step-wait.php',
			array( 'order' => $order ),
			'klarna-payments/',
			WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Print the refresh that takes a browser without JavaScript to the order received page once the wait is over.
	 *
	 * @return void
	 */
	public static function render_wait_noscript_refresh() {
		$order = self::$waiting_for_order;

		if ( ! $order ) {
			return;
		}

		$seconds      = absint( ceil( self::get_wait_max_attempts() * self::get_wait_interval() / 1000 ) );
		$fallback_url = self::get_default_redirect_url( $order, self::$waiting_for_unique_id );

		$refresh = $seconds . ';url=' . esc_url_raw( $fallback_url );

		printf( '<noscript><meta http-equiv="refresh" content="%s"></noscript>' . "\n", esc_attr( $refresh ) );
	}

	/**
	 * Get the URL to send the customer to when Klarna's confirmation does not arrive in time.
	 *
	 * @param \WC_Order $order         The WooCommerce order.
	 * @param string    $kec_unique_id The KEC unique ID.
	 *
	 * @return string
	 */
	private static function get_default_redirect_url( $order, $kec_unique_id ) {
		/**
		 * Filters the fallback redirect URL used when the order redirect URL is not set in time.
		 *
		 * @param string    $default_redirect_url The fallback redirect URL. Defaults to the order received URL.
		 * @param \WC_Order $order                 The WooCommerce order.
		 * @param string    $kec_unique_id         The KEC unique ID.
		 */
		return apply_filters( 'kec_one_step_default_redirect_url', $order->get_checkout_order_received_url(), $order, $kec_unique_id );
	}

	/**
	 * Get the number of times the browser asks whether Klarna has confirmed the payment.
	 *
	 * @return int
	 */
	private static function get_wait_max_attempts() {
		/**
		 * Filters the maximum number of attempts to wait for the order redirect URL to be set.
		 *
		 * @param int $max_attempts The maximum number of polling attempts. Default 20.
		 */
		return intval( apply_filters( 'kec_one_step_redirect_wait_max_attempts', 20 ) );
	}

	/**
	 * Get the time, in milliseconds, the browser waits between asking whether Klarna has confirmed the payment.
	 *
	 * @return int
	 */
	private static function get_wait_interval() {
		/**
		 * Filters the wait time, in microseconds, between attempts to read the order redirect URL.
		 *
		 * @param int $sleep_time The wait time between attempts, in microseconds. Default 500000.
		 */
		$sleep_time = intval( apply_filters( 'kec_one_step_redirect_wait_sleep_time_mu', 5 * 100000 ) );

		return intval( $sleep_time / 1000 );
	}

	/**
	 * Build the body for the one-step initiate request.
	 *
	 * @return array
	 */
	public static function get_initiate_body() {
		$unique_id = 'kec_one_step_' . bin2hex( random_bytes( 16 ) );
		WC()->session->set( 'kec_one_step_unique_id', $unique_id );

		return array(
			'collectCustomerProfile'    => array(
				'profile:name',
				'profile:email',
				'profile:phone',
				'profile:locale',
				'profile:billing_address',
				'profile:country',
			),
			'shippingConfig'            => array(
				'mode' => 'EDITABLE',
			),
			'paymentRequestReference'   => $unique_id,
			'customerInteractionConfig' => array(
				'returnUrl' => add_query_arg( array( 'kec-one-step' => $unique_id ), home_url() ),
			),
			'amount'                    => self::format_price( WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() ),
			'currency'                  => get_woocommerce_currency(),
			'supplementaryPurchaseData' => array(
				'lineItems' => self::get_cart_items(),
			),
		);
	}

	/**
	 * Calculate shipping options for the given address and return the response body.
	 *
	 * @param array  $shipping_address   The shipping address from Klarna.
	 * @param string $payment_request_id The Klarna payment request ID.
	 *
	 * @return array
	 */
	public static function get_shipping_address_change_body( $shipping_address, $payment_request_id ) {
		if ( ! empty( $shipping_address['country'] ?? '' ) ) {
			WC()->customer->set_shipping_country( $shipping_address['country'] ?? '' );
		}

		if ( ! empty( $shipping_address['region'] ?? '' ) ) {
			WC()->customer->set_shipping_state( $shipping_address['region'] ?? '' );
		}

		if ( ! empty( $shipping_address['postalCode'] ?? '' ) ) {
			WC()->customer->set_shipping_postcode( $shipping_address['postalCode'] ?? '' );
		}

		if ( ! empty( $shipping_address['city'] ?? '' ) ) {
			WC()->customer->set_shipping_city( $shipping_address['city'] ?? '' );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$shipping_needed = WC()->cart->needs_shipping();
		if ( $shipping_needed ) {
			$shipping_options = self::get_shipping_options( WC()->shipping->get_packages() );
		} else {
			$shipping_options = array(
				array(
					'shippingOptionReference' => 'digital-delivery',
					'amount'                  => 0,
					'displayName'             => __( 'Digital delivery', 'klarna-payments-for-woocommerce' ),
					'description'             => __( 'Digital delivery', 'klarna-payments-for-woocommerce' ),
					'shippingType'            => 'DIGITAL_EMAIL',
				),
			);
		}

		$selected_shipping_option_reference = WC()->session->get( 'chosen_shipping_methods', array() );
		$selected_shipping_option_reference = ( ! empty( $selected_shipping_option_reference ) ) ? $selected_shipping_option_reference[0] : '';

		$selected_shipping_option = array_filter(
			$shipping_options,
			function ( $option ) use ( $selected_shipping_option_reference ) {
				return $option['shippingOptionReference'] === $selected_shipping_option_reference;
			}
		);

		$selected_shipping_option = ( empty( $selected_shipping_option ) && ! empty( $shipping_options ) ) ? $shipping_options[0] : reset( $selected_shipping_option );

		$line_items = self::get_cart_items();
		self::create_order( $payment_request_id );

		if ( ! empty( $selected_shipping_option ) ) {
			$line_items[] = array(
				'name'              => $selected_shipping_option['displayName'],
				'shippingReference' => $selected_shipping_option['shippingOptionReference'],
				'quantity'          => 1,
				'totalAmount'       => $selected_shipping_option['amount'],
				'totalTaxAmount'    => self::format_price( WC()->cart->get_shipping_tax() ),
			);
		}

		return array(
			'amount'                          => self::format_price( WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() ) + ( $selected_shipping_option['amount'] ?? 0 ),
			'currency'                        => get_woocommerce_currency(),
			'lineItems'                       => $line_items,
			'selectedShippingOptionReference' => $selected_shipping_option['shippingOptionReference'] ?? '',
			'shippingOptions'                 => $shipping_options,
		);
	}

	/**
	 * Update the selected shipping option and return the response body.
	 *
	 * @param string $selected_option The selected shipping option reference.
	 *
	 * @return array
	 */
	public static function get_changed_shipping_option_response( $selected_option ) {
		WC()->session->set( 'chosen_shipping_methods', array( $selected_option ) );

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$selected_shipping_methods = WC()->cart->get_shipping_methods();

		/**
		 * Selected shipping method.
		 *
		 * @var \WC_Shipping_Rate $selected_shipping_method
		 */
		$selected_shipping_method = is_array( $selected_shipping_methods ) && ! empty( $selected_shipping_methods ) ? reset( $selected_shipping_methods ) : null;

		$line_items = self::get_cart_items();

		if ( ! empty( $selected_shipping_method ) ) {
			$line_items[] = array(
				'name'              => $selected_shipping_method->get_label(),
				'shippingReference' => $selected_shipping_method->get_id(),
				'quantity'          => 1,
				'totalAmount'       => self::format_price( $selected_shipping_method->get_cost() + $selected_shipping_method->get_shipping_tax() ),
				'totalTaxAmount'    => self::format_price( WC()->cart->get_shipping_tax() ),
			);

			$order = self::get_wc_order();
			if ( $order && ! is_wp_error( $order ) ) {
				$order->remove_order_items( 'shipping' );
				self::set_order_item_shipping( $order );
				$order->calculate_totals();
				$order->save();
			}
		}

		return array(
			'amount'    => self::format_price( WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() + $selected_shipping_method->get_cost() + $selected_shipping_method->get_shipping_tax() ),
			'lineItems' => $line_items,
		);
	}

	/**
	 * Get cart items formatted for Klarna.
	 *
	 * @return array
	 */
	private static function get_cart_items() {
		$line_items = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			/**
			 * Product in cart item.
			 *
			 * @var \WC_Product $product
			 */
			$product = $cart_item['data'];

			if ( ! $product->exists() || $product->is_type( 'line_item' ) ) {
				continue;
			}

			$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			$line_item = array(
				'name'              => $product->get_name(),
				'lineItemReference' => $product->get_sku(),
				'quantity'          => $cart_item['quantity'],
				'totalAmount'       => self::format_price( $cart_item['line_total'] + $cart_item['line_tax'] ),
				'totalTaxAmount'    => self::format_price( $cart_item['line_tax'] ),
			);

			if ( ! empty( $image_url ) ) {
				$line_item['imageUrl'] = $image_url;
			}

			if ( ! empty( $product->get_global_unique_id() ) ) {
				$line_item['productIdentifier'] = $product->get_global_unique_id();
			}

			$line_items[] = $line_item;
		}

		return $line_items;
	}

	/**
	 * Get shipping options from the shipping packages.
	 *
	 * @param array $shipping_packages The WooCommerce shipping packages.
	 *
	 * @return array
	 */
	public static function get_shipping_options( $shipping_packages ) {
		$shipping_options = array();

		foreach ( $shipping_packages as $package ) {
			if ( empty( $package['rates'] ) ) {
				continue;
			}

			foreach ( $package['rates'] as $rate ) {
				/**
				 * Shipping rate object.
				 *
				 * @var \WC_Shipping_Rate $rate
				 */
				$shipping_options[] = array(
					'shippingOptionReference' => $rate->get_id(),
					'amount'                  => self::format_price( $rate->get_cost() + $rate->get_shipping_tax() ),
					'displayName'             => $rate->get_label(),
				);
			}
		}

		return $shipping_options;
	}

	/**
	 * Format a price value for Klarna (convert to minor units).
	 *
	 * @param float $price The price to format.
	 *
	 * @return int The price in minor units.
	 */
	private static function format_price( $price ) {
		$price = floatval( $price );
		return intval( number_format( $price * 100, 0, '.', '' ) );
	}

	/**
	 * Create an order from the KEC session and cart data.
	 *
	 * @param string $payment_request_id The Klarna payment request ID.
	 *
	 * @return \WC_Order|false
	 */
	public static function create_order( $payment_request_id ) {
		$order = self::update_or_create_wc_order( $payment_request_id );

		if ( ! $order || is_wp_error( $order ) ) {
			return false;
		}

		WC()->session->set( 'kec_one_step_order_id', $order->get_id() );

		return $order;
	}

	/**
	 * Get the existing order from the session or create a new one.
	 *
	 * @return \WC_Order|false
	 */
	private static function get_wc_order() {
		$order_id = WC()->session->get( 'kec_one_step_order_id' );
		$order    = wc_get_order( $order_id );

		if ( $order && $order->get_user_id() === get_current_user_id() ) {
			return $order;
		}

		$order = wc_create_order(
			array(
				'status'      => 'pending',
				'customer_id' => get_current_user_id(),
				'created_via' => 'klarna_express_checkout',
			)
		);

		if ( ! $order || is_wp_error( $order ) ) {
			return false;
		}

		return $order;
	}

	/**
	 * Add products from the cart to the order.
	 *
	 * @param \WC_Order $order The WooCommerce order. Passed by reference.
	 *
	 * @return void
	 */
	private static function set_order_item_products( &$order ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			/**
			 * Product in cart item.
			 *
			 * @var \WC_Product $product
			 */
			$product = $cart_item['data'];

			if ( ! $product->exists() || $product->is_type( 'line_item' ) ) {
				continue;
			}

			$order->add_product(
				$product,
				$cart_item['quantity'],
				array(
					'totals' => array(
						'subtotal'     => $cart_item['line_subtotal'],
						'subtotal_tax' => $cart_item['line_subtotal_tax'],
						'total'        => $cart_item['line_total'],
						'tax'          => $cart_item['line_tax'],
					),
				)
			);
		}
	}

	/**
	 * Add shipping items from the cart to the order.
	 *
	 * @param \WC_Order $order The WooCommerce order. Passed by reference.
	 *
	 * @return void
	 */
	private static function set_order_item_shipping( &$order ) {
		$shipping_methods = WC()->cart->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {
			$shipping_item = new \WC_Order_Item_Shipping();
			$shipping_item->set_shipping_rate( $shipping_method );
			$order->add_item( $shipping_item );
		}
	}

	/**
	 * Set the billing and shipping address on the order from the customer session.
	 *
	 * @param \WC_Order $order The WooCommerce order. Passed by reference.
	 *
	 * @return void
	 */
	private static function set_order_address( &$order ) {
		foreach ( WC()->customer->get_billing() as $key => $value ) {
			if ( ! empty( $value ) && method_exists( $order, "set_billing_$key" ) ) {
				$order->{"set_billing_$key"}( $value );
			}
		}

		foreach ( WC()->customer->get_shipping() as $key => $value ) {
			if ( ! empty( $value ) && method_exists( $order, "set_shipping_$key" ) ) {
				$order->{"set_shipping_$key"}( $value );
			}
		}
	}

	/**
	 * Update or create a WooCommerce order with the current cart and customer data.
	 *
	 * @param string $payment_request_id The Klarna payment request ID.
	 *
	 * @return \WC_Order|false
	 */
	private static function update_or_create_wc_order( $payment_request_id ) {
		$order = self::get_wc_order();

		if ( ! $order || is_wp_error( $order ) ) {
			return false;
		}

		$unique_id = WC()->session->get( 'kec_one_step_unique_id' );

		$order->remove_order_items();

		$order->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
		$order->set_customer_user_agent( wc_get_user_agent() );

		$order->update_meta_data( '_kec_payment_request_id', $payment_request_id );
		$order->update_meta_data( '_kec_unique_id', $unique_id );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		WC()->checkout()->set_data_from_cart( $order );

		self::set_order_address( $order );

		$order->calculate_totals();
		$order->save();

		WC()->session->set( 'kec_one_step_order_id', $order->get_id() );

		return $order;
	}

	/**
	 * Set the billing and shipping address on the order from the Klarna payment data.
	 *
	 * @param \WC_Order $order        The WooCommerce order. Passed by reference.
	 * @param array     $payment_data The payment data from Klarna.
	 *
	 * @return void
	 */
	public static function set_order_address_from_payment_data( &$order, $payment_data ) {
		$context           = $payment_data['stateContext'] ?? array();
		$billing_customer  = $context['klarnaCustomer']['customerProfile'] ?? array();
		$billing_address   = $context['klarnaCustomer']['customerProfile']['address'] ?? array();
		$shipping_customer = $context['shipping']['recipient'] ?? array();
		$shipping_address  = $context['shipping']['address'] ?? array();

		self::set_address_field( $order, $billing_customer['givenName'] ?? '', 'first_name', 'billing' );
		self::set_address_field( $order, $billing_customer['familyName'] ?? '', 'last_name', 'billing' );
		self::set_address_field( $order, $billing_customer['email'] ?? '', 'email', 'billing' );
		self::set_address_field( $order, $billing_customer['phone'] ?? '', 'phone', 'billing' );
		self::set_address_field( $order, $billing_address['streetAddress'] ?? '', 'address_1', 'billing' );
		self::set_address_field( $order, $billing_address['streetAddress2'] ?? '', 'address_2', 'billing' );
		self::set_address_field( $order, $billing_address['postalCode'] ?? '', 'postcode', 'billing' );
		self::set_address_field( $order, $billing_address['city'] ?? '', 'city', 'billing' );
		self::set_address_field( $order, $billing_address['region'] ?? '', 'state', 'billing' );
		self::set_address_field( $order, $billing_address['country'] ?? '', 'country', 'billing' );
		self::set_address_field( $order, $shipping_customer['givenName'] ?? '', 'first_name', 'shipping' );
		self::set_address_field( $order, $shipping_customer['familyName'] ?? '', 'last_name', 'shipping' );
		self::set_address_field( $order, $shipping_customer['email'] ?? '', 'email', 'shipping' );
		self::set_address_field( $order, $shipping_customer['phone'] ?? '', 'phone', 'shipping' );
		self::set_address_field( $order, $shipping_address['streetAddress'] ?? '', 'address_1', 'shipping' );
		self::set_address_field( $order, $shipping_address['streetAddress2'] ?? '', 'address_2', 'shipping' );
		self::set_address_field( $order, $shipping_address['postalCode'] ?? '', 'postcode', 'shipping' );
		self::set_address_field( $order, $shipping_address['city'] ?? '', 'city', 'shipping' );
		self::set_address_field( $order, $shipping_address['region'] ?? '', 'state', 'shipping' );
		self::set_address_field( $order, $shipping_address['country'] ?? '', 'country', 'shipping' );
	}

	/**
	 * Set a specific address field on the order if the value is non-empty.
	 *
	 * @param \WC_Order $order        The WooCommerce order. Passed by reference.
	 * @param mixed     $value        The value to set.
	 * @param string    $field        The order field name.
	 * @param string    $address_type The address type ('billing' or 'shipping').
	 *
	 * @return void
	 */
	private static function set_address_field( &$order, $value, $field, $address_type ) {
		if ( ! empty( $value ) && method_exists( $order, "set_{$address_type}_{$field}" ) ) {
			$method = "set_{$address_type}_{$field}";
			$order->$method( $value );
		}
	}
}
