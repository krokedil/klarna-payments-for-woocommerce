<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout;

use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;

defined( 'ABSPATH' ) || exit;

/**
 * One-step checkout logic for Klarna Express Checkout.
 */
class OneStepCheckout {
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

		add_action( 'init', __CLASS__ . '::maybe_redirect_kec_one_step_checkout' );
	}

	/**
	 * Redirect the customer to the correct URL after a one-step checkout order.
	 *
	 * @return void
	 */
	public static function maybe_redirect_kec_one_step_checkout() {
		$kec_unique_id = filter_input( INPUT_GET, 'kec-one-step', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $kec_unique_id ) ) {
			return;
		}

		$args = array(
			'limit'        => 1,
			'meta_key'     => '_kec_unique_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => $kec_unique_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '=',
			'created_via'  => 'klarna_express_checkout',
			'date_created' => '>' . ( time() - ( DAY_IN_SECONDS * 2 ) ),
		);

		$orders = wc_get_orders( $args );
		if ( empty( $orders ) ) {
			self::unset_sessions();
			wc_add_notice( __( 'Your order could not be processed', 'klarna-payments-for-woocommerce' ) );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		$order = reset( $orders );

		if ( $order->get_meta( '_kec_unique_id' ) !== $kec_unique_id ) {
			self::unset_sessions();
			wc_add_notice( __( 'Your order could not be processed', 'klarna-payments-for-woocommerce' ) );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		$redirect_url = self::get_redirect_url_for_order( $order, $kec_unique_id );
		self::unset_sessions();
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Clear one-step session data.
	 *
	 * @return void
	 */
	public static function unset_sessions() {
		WC()->session->__unset( 'kec_one_step_unique_id' );
		WC()->session->__unset( 'kec_one_step_order_id' );
	}

	/**
	 * Wait for the order redirect URL to be set and return it.
	 *
	 * @param \WC_Order $order         The WooCommerce order.
	 * @param string    $kec_unique_id The KEC unique ID.
	 *
	 * @return string The redirect URL.
	 */
	public static function get_redirect_url_for_order( $order, $kec_unique_id ) {
		$max_attempts         = apply_filters( 'kec_one_step_redirect_wait_max_attempts', 20 );
		$sleep_time           = apply_filters( 'kec_one_step_redirect_wait_sleep_time_mu', 5 * 100000 );
		$attempt              = 0;
		$default_redirect_url = apply_filters( 'kec_one_step_default_redirect_url', $order->get_checkout_order_received_url(), $order, $kec_unique_id );

		while ( $attempt < $max_attempts ) {
			$order->read_meta_data( true );
			$redirect_url = $order->get_meta( '_kec_redirect_url' );

			if ( ! empty( $redirect_url ) ) {
				return $redirect_url;
			}

			usleep( $sleep_time );
			++$attempt;
		}

		return $default_redirect_url;
	}

	/**
	 * Build the body for the one-step initiate request.
	 *
	 * @return array
	 */
	public static function get_initiate_body() {
		$unique_id = uniqid( 'kec_one_step_' );
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
	 * @param string $payment_token      The Klarna payment token.
	 *
	 * @return array
	 */
	public static function get_shipping_address_change_body( $shipping_address, $payment_request_id, $payment_token ) {
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
		self::create_order( $payment_request_id, $payment_token );

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

		/** @var \WC_Shipping_Rate $selected_shipping_method */
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
			/** @var \WC_Product $product */
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
				/** @var \WC_Shipping_Rate $rate */
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
	 * @param string $payment_token      The Klarna payment token.
	 *
	 * @return \WC_Order|false
	 */
	public static function create_order( $payment_request_id, $payment_token ) {
		$order = self::update_or_create_wc_order( $payment_request_id, $payment_token );

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
			/** @var \WC_Product $product */
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
	 * @param string $payment_token      The Klarna payment token.
	 *
	 * @return \WC_Order|false
	 */
	private static function update_or_create_wc_order( $payment_request_id, $payment_token ) {
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
