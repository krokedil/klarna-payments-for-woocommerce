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
	 * The WooCommerce order it used to calculate the order data. If null the cart will be used instead.
	 *
	 * @var int|null
	 */
	private $order_id;

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
     * Class constructor.
     *
     * @param string   $customer_type The Customer type to use for KP. Either B2B or B2C. Based on the setting in the plugin.
     * @param int|null $order_id The WooCommerce order it used to calculate the order data. If null the cart will be used instead.
     */
    public function __construct( $customer_type, $order_id = null ) {
		$this->customer_type = $customer_type;
        $this->order_id      = $order_id;

		$this->order_data    = $this->get_order_data();
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
			'purchase_country'  => kp_get_klarna_country(),
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

		// Order compatibility, to support third party plugins that add orders lines that needs to be handled seperatly.
		foreach ( $this->order_data->get_line_compatibility() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
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
			default:
				$type = 'physical';
				break;
		}

		return array_filter(
			array(
				'image_url'             => $order_line->get_image_url(),
				'merchant_data'         => apply_filters( $order_line->get_filter_name( 'merchant_data' ), array(), $order_line ),
				'name'                  => $order_line->get_name(),
				'product_identifiers'   => apply_filters( $order_line->get_filter_name( 'product_identifiers' ), array(), $order_line ),
				'product_url'           => $order_line->get_product_url(),
				'quantity'              => $order_line->get_quantity(),
				'quantity_unit'         => apply_filters( $order_line->get_filter_name( 'quantity_unit' ), 'pcs', $order_line ),
				'reference'             => $order_line->get_sku(),
				'tax_rate'              => $order_line->get_tax_rate(),
				'total_amount'          => $order_line->get_total_amount() + $order_line->get_total_tax_amount(),
				'total_discount_amount' => $order_line->get_total_discount_amount(),
				'total_tax_amount'      => $order_line->get_total_tax_amount(),
				'type'                  => $type,
				'unit_price'            => $order_line->get_unit_price() + $order_line->get_unit_tax_amount(),
				'subscription'          => apply_filters( $order_line->get_filter_name( 'subscription' ), array(), $order_line ),
			),
			'KP_Order_Data::remove_null' );
		;
	}

	/**
	 * Returns a formated Klarna customer object.
	 *
	 * @param  string|null $customer_type The customer type to use for generating the data. If empty it will use the class property instead.
	 * @return array
	 */
	public function get_klarna_customer_object( $customer_type = null ) {
        if( null === $customer_type ) {
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

		$customer              = array(
			'billing'  => $billing,
			'shipping' => array_merge( $shipping, $billing ),
		);

		if ( 'b2b' === $customer_type ) {
			$customer['billing']['organization_name']  = $customer_data->get_billing_company();
			$customer['shipping']['organization_name'] = $customer_data->get_shipping_company();
		}

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
