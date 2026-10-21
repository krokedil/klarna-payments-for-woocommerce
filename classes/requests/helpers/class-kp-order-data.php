<?php
/**
 * Helper class to generate a Klarna payments request body for a order/session request.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

use KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\Cart\Cart;
use KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\Order\Order;

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
	 * @var KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderData
	 */
	public $order_data;

	/**
	 * If we should use separate sales tax or not for the requests.
	 *
	 * @var bool
	 */
	public $separate_sales_tax;

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
	 * @return KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderData
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
	 * Returns a formatted Klarna order object.
	 *
	 * @param KP_IFrame $iframe_options The options to use for the Klarna Payments iframes.
	 * @return array
	 */
	public function get_klarna_order_object( $iframe_options = null ) {
		$customer = $this->get_klarna_customer_object();

		return array(
			'purchase_country'  => $this->klarna_country,
			'purchase_currency' => empty( $this->order ) ? get_woocommerce_currency() : $this->order->get_currency(),
			'locale'            => kp_get_locale(),
			'order_amount'      => $this->order_data->get_total(),
			'order_tax_amount'  => $this->order_data->get_total_tax(),
			'order_lines'       => $this->get_klarna_order_lines_array(),
			'customer'          => get_klarna_customer( $this->customer_type ),
			'billing_address'   => $customer['billing'],
			'shipping_address'  => $customer['shipping'],
			'options'           => $iframe_options ? $iframe_options->get_kp_color_options() : array(),
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

		// Order compatibility, to support third party plugins that add orders lines that needs to be handled separately.
		foreach ( $this->order_data->get_line_compatibility() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object( $item );
		}

		// Order Coupons/Giftcards.
		foreach ( KP_WC()->krokedil->compatibility()->giftcards() as $giftcards ) {
			if ( false !== ( strpos( get_class( $giftcards ), 'WCGiftCards', true ) ) && ! function_exists( 'WC_GC' ) ) {
				continue;
			}

			$retrieved_giftcards = ! empty( $this->order ) ? $giftcards->get_order_giftcards( $this->order ) : $giftcards->get_cart_giftcards();
			foreach ( $retrieved_giftcards as $retrieved_giftcard ) {
				$klarna_order_lines[] = $this->get_klarna_order_line_object( $retrieved_giftcard );
			}
		}

		if ( $this->separate_sales_tax ) {
			$sales_tax_line       = array(
				/* translators: [merchant-facing]. */
				'name'                  => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'quantity'              => 1,
				/* translators: [merchant-facing]. */
				'reference'             => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'tax_rate'              => 0,
				'total_amount'          => (int) round( (float) $this->order_data->get_total_tax(), 0 ),
				'total_discount_amount' => 0,
				'total_tax_amount'      => 0,
				'type'                  => 'sales_tax',
				'unit_price'            => (int) round( (float) $this->order_data->get_total_tax(), 0 ),
			);
			$klarna_order_lines[] = $sales_tax_line;
		}

		// With separate sales tax the lines carry no tax, so only the amounts are matched.
		$total_tax = $this->separate_sales_tax ? null : $this->order_data->get_total_tax();

		$klarna_order_lines = self::adjust_rounding( $klarna_order_lines, $this->order_data->get_total(), $total_tax, self::get_rounding_unit() );

		return $this->separate_sales_tax ? $klarna_order_lines : self::align_tax_rates( $klarna_order_lines );
	}

	/**
	 * Gets the smallest step WooCommerce rounds prices to, in minor units. A store that shows whole kronor rounds to 100 öre.
	 *
	 * @return int
	 */
	public static function get_rounding_unit() {
		return (int) pow( 10, max( 0, 2 - wc_get_price_decimals() ) );
	}

	/**
	 * Gives every order line a tax rate that agrees with its own tax amount, the way Klarna validates it.
	 *
	 * @param array $order_lines The formatted Klarna order lines.
	 * @return array
	 */
	public static function align_tax_rates( $order_lines ) {
		foreach ( $order_lines as $key => $order_line ) {
			$total     = $order_line['total_amount'] ?? 0;
			$total_tax = $order_line['total_tax_amount'] ?? 0;
			$tax_rate  = $order_line['tax_rate'] ?? 0;

			if ( 0 === $total || $total === $total_tax ) {
				continue;
			}

			$expected_tax = $total - $total * 10000 / ( 10000 + $tax_rate );
			if ( abs( $total_tax - $expected_tax ) <= 1 ) {
				continue;
			}

			$tax_rate                        = (int) round( $total_tax * 10000 / ( $total - $total_tax ) );
			$order_lines[ $key ]['tax_rate'] = $tax_rate;

			// On a large line one step of tax rate moves the tax by more than a minor unit, so the closest rate can still miss it.
			// Moving the tax one minor unit stays within Klarna's tolerance on the order tax amount, anything more would break it.
			$miss = $total - $total * 10000 / ( 10000 + $tax_rate ) - $total_tax;
			if ( abs( $miss ) > 1 && abs( $miss ) <= 2 ) {
				$order_lines[ $key ]['total_tax_amount'] = $total_tax + ( $miss > 0 ? 1 : -1 );
			}
		}

		return $order_lines;
	}

	/**
	 * Makes the order lines add up to the WooCommerce totals.
	 *
	 * @param array    $order_lines The formatted Klarna order lines.
	 * @param int      $total The WooCommerce total in minor units.
	 * @param int|null $total_tax The WooCommerce total tax in minor units, or null to leave the tax amounts as they are.
	 * @param int      $unit The step WooCommerce rounds prices to, in minor units. Each line may be off by up to one step.
	 * @return array
	 */
	public static function adjust_rounding( $order_lines, $total, $total_tax = null, $unit = 1 ) {
		$adjusted = $order_lines;

		if ( null !== $total_tax ) {
			$adjusted = self::adjust_rounding_field( $adjusted, 'total_tax_amount', (int) $total_tax, (int) $unit );
			if ( null === $adjusted ) {
				return $order_lines;
			}
		}

		$adjusted = self::adjust_rounding_field( $adjusted, 'total_amount', (int) $total, (int) $unit );

		return $adjusted ?? $order_lines;
	}

	/**
	 * Spreads the difference between a total and the sum of one order line field over the lines, one minor unit at a time.
	 *
	 * @param array  $order_lines The formatted Klarna order lines.
	 * @param string $field The order line field to adjust, total_amount or total_tax_amount.
	 * @param int    $total The total the field should add up to, in minor units.
	 * @param int    $unit The step WooCommerce rounds prices to, in minor units.
	 * @return array|null The adjusted order lines, or null if the difference could not be placed.
	 */
	private static function adjust_rounding_field( $order_lines, $field, $total, $unit ) {
		$difference = $total - (int) array_sum( array_column( $order_lines, $field ) );
		if ( 0 === $difference ) {
			return $order_lines;
		}

		// Rounding can only ever put a line off by less than one rounding step, so anything larger is an actual mismatch that should not be hidden.
		if ( abs( $difference ) > count( $order_lines ) * $unit ) {
			KP_Logger::log( "The order lines {$field} differ from the WooCommerce total by {$difference}, which is too much to be a rounding difference. The order lines are left as they are." );
			return null;
		}

		$step = ( $difference > 0 ) ? 1 : -1;

		while ( 0 !== $difference ) {
			$applied = false;

			foreach ( self::get_rounding_order( $order_lines ) as $key ) {
				if ( 0 === $difference ) {
					break;
				}

				$line = self::apply_rounding_step( $order_lines[ $key ], $field, $step );
				if ( null === $line ) {
					continue;
				}

				$order_lines[ $key ] = $line;
				$difference         -= $step;
				$applied             = true;
			}

			if ( ! $applied ) {
				KP_Logger::log( "The order lines {$field} differ from the WooCommerce total by {$difference}, but no line was found that could take the difference. The order lines are left as they are." );
				return null;
			}
		}

		return $order_lines;
	}

	/**
	 * Moves one minor unit onto an order line, keeping total_amount equal to quantity * unit_price - total_discount_amount.
	 *
	 * @param array  $order_line The formatted Klarna order line.
	 * @param string $field The order line field to adjust, total_amount or total_tax_amount.
	 * @param int    $step 1 or -1.
	 * @return array|null The adjusted order line, or null if the line cannot take the step.
	 */
	private static function apply_rounding_step( $order_line, $field, $step ) {
		if ( 'total_tax_amount' === $field ) {
			if ( empty( $order_line['tax_rate'] ) || $order_line['total_tax_amount'] + $step < 0 ) {
				return null;
			}

			$order_line['total_tax_amount'] += $step;
			return $order_line;
		}

		if ( ( $order_line['total_discount_amount'] ?? 0 ) - $step >= 0 ) {
			$order_line['total_discount_amount'] = ( $order_line['total_discount_amount'] ?? 0 ) - $step;
		} elseif ( 1 === (int) $order_line['quantity'] && $order_line['unit_price'] + $step >= 0 ) {
			$order_line['unit_price'] += $step;
		} else {
			return null;
		}

		$order_line['total_amount'] += $step;
		return $order_line;
	}

	/**
	 * Gets the keys of the order lines that can carry a rounding difference, largest line first.
	 *
	 * @param array $order_lines The formatted Klarna order lines.
	 * @return array
	 */
	private static function get_rounding_order( $order_lines ) {
		$keys = array();
		foreach ( $order_lines as $key => $order_line ) {
			if ( ( $order_line['total_amount'] ?? 0 ) > 0 && 'sales_tax' !== ( $order_line['type'] ?? '' ) ) {
				$keys[] = $key;
			}
		}

		usort(
			$keys,
			function ( $a, $b ) use ( $order_lines ) {
				return array( $order_lines[ $b ]['total_amount'], $a ) <=> array( $order_lines[ $a ]['total_amount'], $b );
			}
		);

		return $keys;
	}

	/**
	 * Returns an array of Klarna order line objects for interoperability data.
	 *
	 * @return array
	 */
	public function get_klarna_order_lines_interoperability() {
		$klarna_order_lines = array();

		// Order shipping.
		$shipping_lines     = $this->order_data->get_line_shipping() ?? array();
		$klarna_shipping    = $this->get_klarna_shipping_line_object_interoperability( $shipping_lines[0] ?? null );
		$shipping_reference = $klarna_shipping['shipping_reference'] ?? '';

		// Order products.
		foreach ( $this->order_data->get_line_items() as $item ) {
			$klarna_order_lines[] = $this->get_klarna_order_line_object_interoperability( $item, $shipping_reference );
		}

		return array(
			'line_items' => $klarna_order_lines,
			'shipping'   => $klarna_shipping,
		);
	}

	/**
	 * Returns a formatted Klarna order line object for interoperability data.
	 *
	 * @param  KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderLineData $order_line The order line data.
	 * @param  string                                                        $shipping_reference The shipping reference to use for the order line.
	 * @return array
	 */
	public function get_klarna_order_line_object_interoperability( $order_line, $shipping_reference ) {
		$product_identifier_filter = $order_line->get_filter_name( 'product_identifiers' );

		$klarna_interoperability_item = array(
			'name'               => $order_line->get_name(),
			'quantity'           => $order_line->get_quantity(),
			'total_amount'       => (int) round( (float) ( $this->separate_sales_tax ? $order_line->get_total_amount() : $order_line->get_total_amount() + $order_line->get_total_tax_amount() ), 0 ),
			'unit_price'         => (int) round( (float) ( $this->separate_sales_tax ? $order_line->get_total_amount() : $order_line->get_total_amount() + $order_line->get_total_tax_amount() ), 0 ),
			'total_tax_amount'   => (int) round( (float) ( $this->separate_sales_tax ? 0 : $order_line->get_total_tax_amount() ), 0 ),
			'product_url'        => $order_line->get_product_url(),
			'image_url'          => $this->maybe_allow_order_line_url( $order_line->get_image_url() ),
			'product_identifier' => apply_filters( $product_identifier_filter, array(), $order_line ),
			'reference'          => $order_line->get_sku(),
			'shipping_reference' => $shipping_reference,
		);

		return $klarna_interoperability_item;
	}

	/**
	 * Returns a formatted Klarna shipping line object for interoperability data.
	 *
	 * @param  KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderLineData $shipping_line The shipping line data.
	 * @return array
	 */
	public function get_klarna_shipping_line_object_interoperability( $shipping_line ) {
		$customer_data = $this->order_data->customer;

		/**
		 * Filters whether to strip spaces from the postcode sent to Klarna.
		 *
		 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#remove-postcode-spaces
		 * @param bool $strip Whether to remove postcode spaces. Default false.
		 */
		$strip_postcode_spaces = apply_filters( 'wc_kp_remove_postcode_spaces', false );

		$recipient = array(
			'given_name'  => $customer_data->get_shipping_first_name() ?? '',
			'family_name' => $customer_data->get_shipping_last_name() ?? '',
			'email'       => $customer_data->get_shipping_email() ?? '',
			'phone'       => $customer_data->get_shipping_phone() ?? '',
		);

		$address = array(
			'street_address' => $customer_data->get_shipping_address_1() ?? '',
			'postal_code'    => $strip_postcode_spaces ? $customer_data->get_shipping_postcode() ?? '' : str_replace( ' ', '', $customer_data->get_shipping_postcode() ?? '' ),
			'city'           => $customer_data->get_shipping_city() ?? '',
			'country'        => $customer_data->get_shipping_country() ?? '',
		);

		return array(
			'shipping_reference' => $shipping_line ? $shipping_line->get_sku() : '',
			'recipient'          => $recipient,
			'address'            => $address,
		);
	}

	/**
	 * Returns a formatted Klarna order line object.
	 *
	 * @param  KrokedilKlarnaPaymentsDeps\Krokedil\WooCommerce\OrderLineData $order_line The order line data.
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

		$merchant_data_filter       = $order_line->get_filter_name( 'merchant_data' );
		$product_identifiers_filter = $order_line->get_filter_name( 'product_identifiers' );
		$quantity_unit_filter       = $order_line->get_filter_name( 'quantity_unit' );
		$subscription_filter        = $order_line->get_filter_name( 'subscription' );

		$klarna_item = array(
			'image_url'             => $this->maybe_allow_order_line_url( $order_line->get_image_url() ),
			'merchant_data'         => apply_filters( $merchant_data_filter, array(), $order_line ),
			'name'                  => $order_line->get_name(),
			'product_identifiers'   => apply_filters( $product_identifiers_filter, array(), $order_line ),
			'product_url'           => $order_line->get_product_url(),
			'quantity'              => $order_line->get_quantity(),
			'quantity_unit'         => apply_filters( $quantity_unit_filter, 'pcs', $order_line ),
			'reference'             => $order_line->get_sku(),
			'tax_rate'              => (int) round( (float) ( $this->separate_sales_tax ? 0 : $order_line->get_tax_rate() ), 0 ),
			'total_amount'          => (int) round( (float) ( $this->separate_sales_tax ? $order_line->get_total_amount() : $order_line->get_total_amount() + $order_line->get_total_tax_amount() ), 0 ),
			'total_discount_amount' => (int) round( (float) ( $this->separate_sales_tax ? $order_line->get_total_discount_amount() : $order_line->get_total_discount_amount() + $order_line->get_total_discount_tax_amount() ), 0 ),
			'total_tax_amount'      => (int) round( (float) ( $this->separate_sales_tax ? 0 : $order_line->get_total_tax_amount() ), 0 ),
			'type'                  => $type,
			'unit_price'            => (int) round( (float) ( $this->separate_sales_tax ? $order_line->get_subtotal_unit_price() : $order_line->get_subtotal_unit_price() + $order_line->get_subtotal_unit_tax_amount() ), 0 ),
			'subscription'          => apply_filters( $subscription_filter, array(), $order_line ),
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
	 * Returns a formatted Klarna customer object.
	 *
	 * @param  string|null $customer_type The customer type to use for generating the data. If empty it will use the class property instead.
	 * @return array
	 */
	public function get_klarna_customer_object( $customer_type = null ) {
		if ( empty( $customer_type ) ) {
			$customer_type = $this->customer_type;
		}

		/**
		 * Filters whether to strip spaces from the postcode sent to Klarna.
		 *
		 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#remove-postcode-spaces
		 * @param bool $strip Whether to remove postcode spaces. Default false.
		 */
		$strip_postcode_spaces = apply_filters( 'wc_kp_remove_postcode_spaces', false );
		$customer_data         = $this->order_data->customer;

		$billing = array(
			'given_name'      => $customer_data->get_billing_first_name() ?? '',
			'family_name'     => $customer_data->get_billing_last_name() ?? '',
			'email'           => $customer_data->get_billing_email() ?? '',
			'phone'           => $customer_data->get_billing_phone() ?? '',
			'street_address'  => $customer_data->get_billing_address_1() ?? '',
			'street_address2' => $customer_data->get_billing_address_2() ?? '',
			'postal_code'     => $strip_postcode_spaces ? ( $customer_data->get_billing_postcode() ?? '' ) : str_replace( ' ', '', $customer_data->get_billing_postcode() ?? '' ),
			'city'            => $customer_data->get_billing_city() ?? '',
			'region'          => $customer_data->get_billing_state() ?? '',
			'country'         => empty( $customer_data->get_billing_country() ) ? ( kp_get_klarna_country() ?? '' ) : $customer_data->get_billing_country(),
		);

		$shipping = array(
			'given_name'      => $customer_data->get_shipping_first_name() ?? '',
			'family_name'     => $customer_data->get_shipping_last_name() ?? '',
			'email'           => $customer_data->get_shipping_email() ?? '',
			'phone'           => $customer_data->get_shipping_phone() ?? '',
			'street_address'  => $customer_data->get_shipping_address_1() ?? '',
			'street_address2' => $customer_data->get_shipping_address_2() ?? '',
			'postal_code'     => $strip_postcode_spaces ? ( $customer_data->get_shipping_postcode() ?? '' ) : str_replace( ' ', '', $customer_data->get_shipping_postcode() ?? '' ),
			'city'            => $customer_data->get_shipping_city() ?? '',
			'region'          => $customer_data->get_shipping_state() ?? '',
			'country'         => $customer_data->get_shipping_country() ?? '',
		);

		if ( 'b2b' === $customer_type ) {
			$billing['organization_name']  = $customer_data->get_billing_company() ?? '';
			$shipping['organization_name'] = $customer_data->get_shipping_company() ?? '';
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
	 * @param mixed $value The line value to filter.
	 * @return bool
	 */
	public static function remove_null( $value ) {
		return is_array( $value ) ? ! empty( $value ) : null !== $value; // If empty array, or if value is null return true to remove value.
	}

	/**
	 * Maybe filters out product url for the order line before we send them to Klarna based on settings.
	 *
	 * @param string $url The URL to the product or product image.
	 * @return string|null
	 */
	public function maybe_allow_order_line_url( $url ) {
		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

		if ( 'yes' !== ( $settings['send_product_urls'] ?? 'no' ) ) {
			$url = null;
		}

		return $url;
	}
}
