<?php
/**
 * Handles order lines for Klarna Payments.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Payments_Order_Lines class.
 *
 * Processes order lines for Klarna Payments requests.
 *
 * @TODO: Test with coupons.
 */
class KP_Order_Lines {

	/**
	 * Formatted order lines.
	 *
	 * @var $order_lines
	 */
	private $order_lines = array();

	/**
	 * Shop country.
	 *
	 * @var string
	 */
	private $shop_country;

	/**
	 * Send sales tax as separate item (US merchants).
	 *
	 * @var bool
	 */
	private $separate_sales_tax = false;

	/**
	 * WC_Klarna_Payments_Order_Lines constructor.
	 *
	 * @param bool|string $shop_country Shop country.
	 */
	public function __construct( $shop_country ) {
		$this->shop_country = $shop_country;

		if ( 'US' === $this->shop_country ) {
			$this->separate_sales_tax = true;
		}
	}

	/**
	 * Gets formatted order lines from WooCommerce cart or from WooCommerce order if order exists.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return array
	 */
	public function order_lines( $order_id = false ) {
		$this->get_order_items( $order_id );
		$this->get_order_shipping( $order_id );
		$this->get_order_sales_tax( $order_id );
		$this->process_coupons();
		$this->get_order_fees( $order_id );

		return array(
			'order_lines'      => $this->get_order_lines(),
			'order_amount'     => $this->get_order_amount_via_order( $order_id ),
			'order_tax_amount' => $this->get_order_tax_amount_via_order( $order_id ),
		);
	}

	/**
	 * Get order lines formatted for Klarna API.
	 *
	 * @access private
	 * @return mixed
	 */
	private function get_order_lines() {
		return $this->order_lines;
	}

	/**
	 * Get order total amount via order for Klarna API.
	 *
	 * @access private
	 * @param int $order_id WooCommerce order id.
	 * @return mixed
	 */
	private function get_order_amount_via_order( $order_id = false ) {
		$order = wc_get_order( $order_id );
		return round( $order->get_total() * 100 );
	}

	/**
	 * Get order tax amount via order for Klarna API.
	 *
	 * @access private
	 * @param int $order_id WooCommerce order id.
	 * @return mixed
	 */
	private function get_order_tax_amount_via_order( $order_id = false ) {
		$order = wc_get_order( $order_id );
		return round( ( $order->get_total_tax() ) * 100 );
	}

	/**
	 * Gets the tax rate for the order line.AMFReader
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param int      $order_item WooCommerce order item.
	 * @return int
	 */
	public function get_order_line_tax_rate( $order, $order_item = false ) {
		if ( $this->separate_sales_tax ) {
			return 0;
		}
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( key( $order_item->get_taxes()['total'] ) === $rate_id ) {
				return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
			}
		}
	}

	/**
	 * Process smart coupons.
	 *
	 * @access private
	 */
	private function process_coupons() {
		if ( ! empty( WC()->cart->get_coupons() ) ) {
			foreach ( WC()->cart->get_coupons() as $coupon_key => $coupon ) {
				$coupon_reference  = '';
				$coupon_amount     = 0;
				$coupon_tax_amount = '';

				// Smart coupons and store credit are processed as real line items, cart and product discounts sent for reference only.
				if ( 'smart_coupon' === $coupon->get_discount_type() || 'store_credit' === $coupon->get_discount_type() ) {
					$coupon_amount     = - round( WC()->cart->get_coupon_discount_amount( $coupon_key ) * 100 );
					$coupon_tax_amount = - round( WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) * 100 );
					$coupon_reference  = 'Discount';
				} else {
					if ( 'US' === $this->shop_country ) {
						$coupon_amount     = 0;
						$coupon_tax_amount = 0;

						if ( $coupon->is_type( 'fixed_cart' ) || $coupon->is_type( 'percent' ) ) {
							$coupon_type = 'Cart discount';
						} elseif ( $coupon->is_type( 'fixed_product' ) || $coupon->is_type( 'percent_product' ) ) {
							$coupon_type = 'Product discount';
						} else {
							$coupon_type = 'Discount';
						}

						$coupon_reference = $coupon_type . ' (amount: ' . WC()->cart->get_coupon_discount_amount( $coupon_key ) . ', tax amount: ' . WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) . ')';
					}
				}

				// Add separate discount line item, but only if it's a smart coupon, store credit or country is US.
				if ( 'smart_coupon' === $coupon->get_discount_type() || 'store_credit' === $coupon->get_discount_type() || 'US' === $this->shop_country ) {
					$discount = array(
						'type'                  => 'discount',
						'reference'             => substr( strval( $coupon_reference ), 0, 64 ),
						'name'                  => $coupon_key,
						'quantity'              => 1,
						'unit_price'            => $coupon_amount,
						'tax_rate'              => 0,
						'total_amount'          => $coupon_amount,
						'total_discount_amount' => 0,
						'total_tax_amount'      => $coupon_tax_amount,
					);

					$this->order_lines[] = $discount;
				}
			} // End foreach().
		} // End if().
	}

	/**
	 * Get order items for Klarna API
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return void
	 */
	private function get_order_items( $order_id = false ) {
		$order = wc_get_order( $order_id );
		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item->get_quantity() ) {
				if ( $order_item->get_variation_id() ) {
					$product = wc_get_product( $order_item->get_variation_id() );
				} else {
					$product = wc_get_product( $order_item->get_product_id() );
				}
				$klarna_item = array(
					'reference'             => $this->get_item_reference( $product ),
					'name'                  => $order_item->get_name(),
					'quantity'              => $order_item->get_quantity(),
					'unit_price'            => $this->get_order_item_unit_price( $order_item ),
					'tax_rate'              => ( '0' !== $order_item->get_total_tax() ) ? $this->get_order_line_tax_rate( $order, $order_item ) : 0,
					'total_amount'          => $this->get_order_item_total_amount( $order_item ),
					'total_tax_amount'      => $this->get_order_item_total_tax( $order_item ),
					'total_discount_amount' => $this->get_order_item_discount_amount( $order_item ),
				);

				// Add images.
				$klarna_payment_settings = get_option( 'woocommerce_klarna_payments_settings' );
				if ( 'yes' === $klarna_payment_settings['send_product_urls'] ) {
					$klarna_item['product_url'] = $this->get_item_product_url( $product );
					if ( $this->get_item_image_url( $product ) ) {
						$klarna_item['image_url'] = $this->get_item_image_url( $product );
					}
				}

				$this->order_lines[] = $klarna_item;
			}
		}
	}

	/**
	 * Get order shipping for Klarna API
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return void
	 */
	private function get_order_shipping( $order_id = false ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_shipping_method() ) {
			$shipping = array(
				'type'             => 'shipping_fee',
				'reference'        => $this->get_order_shipping_reference( $order ),
				'name'             => ( '' !== $order->get_shipping_method() ) ? $order->get_shipping_method() : $shipping_name = __( 'Shipping', 'klarna-payments-for-woocommerce' ),
				'quantity'         => 1,
				'unit_price'       => $this->get_order_shipping_unit_price( $order ),
				'tax_rate'         => ( '0' !== $order->get_shipping_tax() ) ? $this->get_order_line_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0,
				'total_amount'     => $this->get_order_shipping_unit_price( $order ),
				'total_tax_amount' => $this->get_order_shipping_tax_amount( $order ),
			);

			$this->order_lines[] = $shipping;
		}
	}

	/**
	 * Get order fees for Klarna API
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return void
	 */
	private function get_order_fees( $order_id = false ) {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order->get_fees() ) ) {
			foreach ( $order->get_fees() as $order_fee ) {
				if ( 0 !== $order_fee->get_total_tax() ) {
					// Calculate tax rate.
					if ( $this->separate_sales_tax ) {
						$order_fee_tax_rate   = 0;
						$order_fee_tax_amount = 0;
						$order_fee_total      = round( $order_fee->get_total() * 100 );
					} else {
						$_tax      = new WC_Tax();
						$tmp_rates = $_tax::get_rates( $order_fee->get_tax_class() );
						$vat       = array_shift( $tmp_rates );

						if ( isset( $vat['rate'] ) ) {
							$order_fee_tax_rate = round( $vat['rate'] * 100 );
						} else {
							$order_fee_tax_rate = 0;
						}

						$order_fee_tax_amount = round( $order_fee->get_total_tax() * 100 );
						$order_fee_total      = round( ( $order_fee->get_total() + $order_fee->get_total_tax() ) * 100 );
					}
				} else {
					$order_fee_tax_rate   = 0;
					$order_fee_tax_amount = 0;
					$order_fee_total      = round( $order_fee->get_total() * 100 );
				}
				$fee = array(
					'type'                  => 'surcharge',
					'reference'             => 'Fee',
					'name'                  => $order_fee->get_name(),
					'quantity'              => 1,
					'unit_price'            => round( $order_fee_total ),
					'tax_rate'              => round( $order_fee_tax_rate ),
					'total_amount'          => round( $order_fee_total ),
					'total_discount_amount' => 0,
					'total_tax_amount'      => round( $order_fee_tax_amount ),
				);

				$this->order_lines[] = $fee;
			} // End foreach().
		} // End if().
	}

	/**
	 * Get order sales tax for Klarna API
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return void
	 */
	private function get_order_sales_tax( $order_id = false ) {
		$order = wc_get_order( $order_id );
		if ( $this->separate_sales_tax ) {
			$sales_tax_amount = round( ( $order->get_total_tax() ) * 100 );

			// Add sales tax line item.
			$sales_tax = array(
				'type'                  => 'sales_tax',
				'reference'             => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'name'                  => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
				'quantity'              => 1,
				'unit_price'            => $sales_tax_amount,
				'tax_rate'              => 0,
				'total_amount'          => $sales_tax_amount,
				'total_discount_amount' => 0,
				'total_tax_amount'      => 0,
			);

			$this->order_lines[] = $sales_tax;
		}
	}

	/**
	 * Get item reference.
	 * Returns SKU or product ID.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param  object $product Product object.
	 *
	 * @return string $item_reference Order item reference.
	 */
	private function get_item_reference( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return substr( strval( $item_reference ), 0, 64 );
	}


	/**
	 * Get cart item product URL.
	 *
	 * @since  1.1
	 * @access private
	 *
	 * @param  WC_Product $product Product.
	 *
	 * @return string $item_product_url Cart item product URL.
	 */
	private function get_item_product_url( $product ) {
		return $product->get_permalink();
	}

	/**
	 * Get cart item product image URL.
	 *
	 * @since  1.1
	 * @access private
	 *
	 * @param  WC_Product $product Product.
	 *
	 * @return string $item_product_image_url Order item product image URL.
	 */
	private function get_item_image_url( $product ) {
		$image_url = false;

		if ( $product->get_image_id() > 0 ) {
			$image_id  = $product->get_image_id();
			$image_url = wp_get_attachment_image_url( $image_id, 'shop_thumbnail', false );
		}

		return $image_url;
	}

	/**
	 * Returns the order items unit price.
	 *
	 * @param WC_Order_Item $order_item WooCommerce order item.
	 * @return int
	 */
	private function get_order_item_unit_price( $order_item ) {
		if ( $this->separate_sales_tax ) {
			return round( ( ( $order_item->get_subtotal() ) / $order_item->get_quantity() ) * 100 );
		}
		return round( ( ( $order_item->get_subtotal() + $order_item->get_subtotal_tax() ) / $order_item->get_quantity() ) * 100 );
	}

	/**
	 * Returns the order item total amount.
	 *
	 * @param WC_Order_Item $order_item WooCommerce order item.
	 * @return int
	 */
	private function get_order_item_total_amount( $order_item ) {
		if ( $this->separate_sales_tax ) {
			return round( $order_item->get_total() * 100 );
		}
		return round( ( $order_item->get_total() + $order_item->get_total_tax() ) * 100 );
	}

	/**
	 * Returns the order item total tax amount.
	 *
	 * @param WC_Order_Item $order_item WooCommerce order item.
	 * @return int
	 */
	private function get_order_item_total_tax( $order_item ) {
		if ( $this->separate_sales_tax ) {
			return 0;
		}
		return round( $order_item->get_total_tax() * 100 );
	}


	/**
	 * Returns the order item total tax amount.
	 *
	 * @param WC_Order_Item $order_item WooCommerce order item.
	 * @return int
	 */
	private function get_order_item_discount_amount( $order_item ) {
		if ( null === $order_item ) {
			return 0;
		}
		if ( $this->separate_sales_tax ) {
			return round( ( $order_item->get_subtotal() - $order_item->get_total() ) * 100 );
		}
		return round( ( $order_item->get_subtotal() + $order_item->get_subtotal_tax() - $order_item->get_total() - $order_item->get_total_tax() ) * 100 );
	}

	/**
	 * Get the order shipping unit price
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return int
	 */
	private function get_order_shipping_unit_price( $order ) {
		if ( $this->separate_sales_tax ) {
			return round( $order->get_shipping_total() * 100 );
		}
		return round( ( $order->get_shipping_total() + $order->get_shipping_tax() ) * 100 );
	}

	/**
	 * Get the order shipping tax amount
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return int
	 */
	private function get_order_shipping_tax_amount( $order ) {
		if ( $this->separate_sales_tax ) {
			return 0;
		}
		return $order->get_shipping_tax() * 100;
	}

	/**
	 * Get the order shipping reference
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string $order_shipping_reference
	 */
	private function get_order_shipping_reference( $order ) {
		$order_shipping_items = $order->get_items( 'shipping' );
		foreach ( $order_shipping_items as $order_shipping_item ) {
			$order_shipping_reference = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		if ( ! isset( $order_shipping_reference ) ) {
			$order_shipping_reference = __( 'Shipping', 'klarna-payments-for-woocommerce' );
		}

		return substr( strval( $order_shipping_reference ), 0, 64 );
	}
}
