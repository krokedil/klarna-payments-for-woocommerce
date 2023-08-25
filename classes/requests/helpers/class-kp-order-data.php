<?php
/**
 * Helper class to generate a Klarna payments request body for a order/session request.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

use Krokedil\WooCommerce\Cart\Cart;
use Krokedil\WooCommerce\Order\Order;

/**
 * Helper class to generate a Klarna payments request body for a order/session request.
 */
class KP_Order_Data {
	/**
	 * The WooCommerce order id is used to calculate the order data. If null the cart will be used instead.
	 *
	 * @var int|null
	 */
	private $order_id;

	/**
	 * The WooCommerce order is used to calculate the order data. If null the cart will be used instead.
	 *
	 * @var WC_Order|null
	 */
	private $order;

	/**
	 * The Klarna country to use for the request body.
	 *
	 * @var string
	 */
	private $klarna_country;

	/**
	 * The Customer type to use for KP. Either B2B or B2C. Based on the setting in the plugin.
	 *
	 * @var string
	 */
	private $customer_type;

	/**
	 * The generated order data.
	 *
	 * @var \Krokedil\WooCommerce\OrderData
	 */
	public $order_data;

	/**
	 * If we should use separate sales tax or not for the requests.
	 *
	 * @var bool
	 */
	private $separate_sales_tax;

	/**
	 * Class constructor.
	 *
	 * @param string   $customer_type The Customer type to use for KP. Either B2B or B2C. Based on the setting in the plugin.
	 * @param int|null $order_id The WooCommerce order it used to calculate the order data. If null the cart will be used instead.
	 */
	public function __construct( $customer_type, $order_id = null ) {
		$this->customer_type      = $customer_type;
		$this->order_id           = $order_id;
		$this->order              = $this->order_id ? wc_get_order( $this->order_id ) : null;
		$this->klarna_country     = kp_get_klarna_country( $this->order );
		$this->order_data         = $this->get_order_data();
		$this->separate_sales_tax = 'US' === $this->klarna_country;
	}

	/**
	 * Returns the request helper for the request based on if we have a order id passed or not.
	 *
	 * @return \Krokedil\WooCommerce\OrderData
	 */
	public function get_order_data() {
		$config = array(
			'slug'         => 'kp',
			'price_format' => 'minor',
		);

		if ( $this->order_id ?? false && ! empty( $this->order_id ) ) {
			$order = wc_get_order( $this->order_id );
			return new Order( $order, $config );
		} else {
			return new Cart( WC()->cart, $config );
		}
	}

	/**
	 * Returns a formated Klarna order object.
	 *
	 * @param KP_IFrame $iframe_options The options to use for the Klarna Payments iframes.
	 * @return array
	 */
	public function get_klarna_order_object( $iframe_options ) {
		$customer = $this->get_klarna_customer_object();

		return array(
			'purchase_country'  => $this->klarna_country,
			'purchase_currency' => get_woocommerce_currency(),
			'locale'            => kp_get_locale(),
			'order_amount'      => $this->order_data->get_total(),
			'order_tax_amount'  => $this->order_data->get_total_tax(),
			'order_lines'       => $this->get_klarna_order_lines_array(),
			'customer'          => get_klarna_customer( $this->customer_type ),
			'billing_address'   => $customer['billing'],
			'shipping_address'  => $customer['shipping'],
			'options'           => $iframe_options->get_kp_color_options(),
			'merchant_urls'     => array(
				'authorization' => home_url( '/wc-api/KP_WC_AUTHORIZATION' ),
			),
		);
	}

	/**
	 * Returns an array of Klarna order line objects.
	 *
	 * @return array
	 */
	public function get_klarna_order_lines_array() {
		$klarna_order_lines = array();

		// Order items/products.
		foreach ( $this->order_data->get_line_items() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
		}

		// Order Shipping.
		foreach ( $this->order_data->get_line_shipping() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
		}

		// Order Fees.
		foreach ( $this->order_data->get_line_fees() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
		}

		// Order Coupons/Giftcards.
		foreach ( $this->order_data->get_line_coupons() as $item ) {
			// Skip any that are not type discount or gift_card.
			if ( ! in_array( $item->get_type(), array( 'discount', 'gift_card' ), true ) ) {
				continue;
			}

			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
		}

		// Order compatibility, to support third party plugins that add orders lines that needs to be handled seperatly.
		foreach ( $this->order_data->get_line_compatibility() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
		}

		if ( $this->separate_sales_tax ) {
			$klarna_order_lines[] = array(
				'name'                  => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'quantity'              => 1,
				'reference'             => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'tax_rate'              => 0,
				'total_amount'          => $this->order_data->get_total_tax(),
				'total_discount_amount' => 0,
				'total_tax_amount'      => 0,
				'type'                  => 'sales_tax',
				'unit_price'            => $this->order_data->get_total_tax(),
			);
		}

		return $klarna_order_lines;
	}

	/**
	 * Returns a formated Klarna order line object.
	 *
	 * @param  \Krokedil\WooCommerce\OrderLineData $order_line The order line data.
	 * @return array
	 */
	public function get_klarna_order_line_object( $order_line ) {
		$type = 'physical';

		switch ( $order_line->get_type() ) {
			case 'shipping':
				$type = 'shipping_fee';
				break;
			case 'fee':
				$type = 'surcharge';
				break;
			case 'gift_card':
				$type = 'gift_card';
				break;
			case 'discount':
				$type = 'discount';
				break;
		}

		$klarna_item = array(
			'image_url'             => $order_line->get_image_url(),
			'merchant_data'         => apply_filters( $order_line->get_filter_name( 'merchant_data' ), array(), $order_line ),
			'name'                  => $order_line->get_name(),
			'product_identifiers'   => apply_filters( $order_line->get_filter_name( 'product_identifiers' ), array(), $order_line ),
			'product_url'           => $order_line->get_product_url(),
			'quantity'              => $order_line->get_quantity(),
			'quantity_unit'         => apply_filters( $order_line->get_filter_name( 'quantity_unit' ), 'pcs', $order_line ),
			'reference'             => $order_line->get_sku(),
			'tax_rate'              => $this->separate_sales_tax ? 0 : $order_line->get_tax_rate(),
			'total_amount'          => $this->separate_sales_tax ? $order_line->get_total_amount() : $order_line->get_total_amount() + $order_line->get_total_tax_amount(),
			'total_discount_amount' => $this->separate_sales_tax ? $order_line->get_total_discount_amount() : $order_line->get_total_discount_amount() + $order_line->get_total_discount_tax_amount(),
			'total_tax_amount'      => $this->separate_sales_tax ? 0 : $order_line->get_total_tax_amount(),
			'type'                  => $type,
			'unit_price'            => $this->separate_sales_tax ? $order_line->get_subtotal_unit_price() : $order_line->get_subtotal_unit_price() + $order_line->get_subtotal_unit_tax_amount(),
			'subscription'          => apply_filters( $order_line->get_filter_name( 'subscription' ), array(), $order_line ),
		);

		if ( isset( $order_line->product ) ) {
			$product = $order_line->product;
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
				$klarna_item['subscription'] = array(
					'name'           => $klarna_item['name'],
					'interval'       => strtoupper( WC_Subscriptions_Product::get_period( $product ) ),
					'interval_count' => absint( WC_Subscriptions_Product::get_interval( $product ) ),
				);
			}
		}

		return array_filter( $klarna_item, 'KP_Order_Data::remove_null' );
	}

	/**
	 * Returns a formated Klarna customer object.
	 *
	 * @param  string|null $customer_type The customer type to use for generating the data. If empty it will use the class property instead.
	 * @return array
	 */
	public function get_klarna_customer_object( $customer_type = null ) {
		if ( null === $customer_type ) {
			$customer_type = $this->customer_type;
		}

		$strip_postcode_spaces = apply_filters( 'wc_kp_remove_postcode_spaces', false );
		$customer_data         = $this->order_data->customer;

		$billing = array(
			'given_name'      => $customer_data->get_billing_first_name(),
			'family_name'     => $customer_data->get_billing_last_name(),
			'email'           => $customer_data->get_billing_email(),
			'phone'           => $customer_data->get_billing_phone(),
			'street_address'  => $customer_data->get_billing_address_1(),
			'street_address2' => $customer_data->get_billing_address_2(),
			'postal_code'     => $strip_postcode_spaces ? $customer_data->get_billing_postcode() : str_replace( ' ', '', $customer_data->get_billing_postcode() ),
			'city'            => $customer_data->get_billing_city(),
			'region'          => $customer_data->get_billing_state(),
			'country'         => $customer_data->get_billing_country(),
		);

		$shipping = array(
			'given_name'      => $customer_data->get_shipping_first_name(),
			'family_name'     => $customer_data->get_shipping_last_name(),
			'email'           => $customer_data->get_shipping_email(),
			'phone'           => $customer_data->get_shipping_phone(),
			'street_address'  => $customer_data->get_shipping_address_1(),
			'street_address2' => $customer_data->get_shipping_address_2(),
			'postal_code'     => $strip_postcode_spaces ? $customer_data->get_shipping_postcode() : str_replace( ' ', '', $customer_data->get_shipping_postcode() ),
			'city'            => $customer_data->get_shipping_city(),
			'region'          => $customer_data->get_shipping_state(),
			'country'         => $customer_data->get_shipping_country(),
		);

		if ( 'b2b' === $customer_type ) {
			$billing['organization_name']  = $customer_data->get_billing_company();
			$shipping['organization_name'] = $customer_data->get_shipping_company();
		}

		foreach ( $shipping as $key => $value ) {
			if ( ! empty( $value ) ) {
				continue;
			}

			$shipping[ $key ] = $billing[ $key ];
		}

		$customer = array(
			'billing'  => $billing,
			'shipping' => $shipping,
		);

		return $customer;
	}

	/**
	 * Filters array values to remove all null values.
	 *
	 * @param mixed $var The line value to filter.
	 * @return bool
	 */
	public static function remove_null( $var ) {
		return is_array( $var ) ? ! empty( $var ) : null !== $var; // If empty array, or if value is null return true to remove value.
	}
}
