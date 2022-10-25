<?php
/**
 * Handles order lines for Klarna Payments from WooCommerce orders.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Order_Helper class.
 *
 * Processes order lines for Klarna Payments requests.
 */
class KP_Order_Helper extends KP_Order_Lines {

	/**
	 * Gets the KP Order lines from a WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array
	 */
	public static function get_kp_order_lines( $order ) {
		$order_lines = array();
		return $order_lines;
	}

	/**
	 * Returns the formated order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Product $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_kp_order_line( $order_item ) {
		return array(
			'image_url'             => self::get_product_image( $order_item ),
			'merchant_data'         => apply_filters( 'wc_kp_line_merchant_data', array(), $order_item ),
			'name'                  => self::get_product_name( $order_item ),
			'product_identifiers'   => self::get_product_identifiers( $order_item ),
			'product_url'           => self::get_product_url( $order_item ),
			'quantity'              => self::get_product_quantity( $order_item ),
			'quantity_unit'         => apply_filters( 'wc_kp_quantity_unit', 'pcs', $order_item ),
			'reference'             => self::get_product_reference( $order_item ),
			'tax_rate'              => self::get_product_tax_rate( $order_item ),
			'total_amount'          => self::get_product_total_amount( $order_item ),
			'total_discount_amount' => self::get_product_total_discount_amount( $order_item ),
			'total_tax_amount'      => self::get_product_total_tax_amount( $order_item ),
			'type'                  => self::get_product_type( $order_item ),
			'unit_price'            => self::get_product_unit_price( $order_item ),
			'subscription'          => self::get_product_subscription( $order_item ),
		);
	}

	/**
	 * Returns the product image url.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_product_image( $order_item ) {
		$product = $order_item->get_product();
		if ( $product ) {
			$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			if ( $image_url ) {
				return $image_url;
			}
		}
		return '';
	}
	/**
	 * Returns the product name from the order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_product_name( $order_item ) {
		return $order_item->get_name();
	}

	/**
	 * Get the product identifiers from the order item.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return array
	 */
	public static function get_product_identifiers( $order_item ) {
		$product = $order_item->get_product();
		if ( $product ) {
			return array(
				'category_path' => wc_get_product_category_list( $product->get_id(), '>' ), // Product categories separated by >.
				'brand'         => apply_filters( 'wc_kp_item_brand', '', $product ),
			);
		}
		return array();
	}

	/**
	 * Get the product URL from the order item.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_product_url( $order_item ) {
		$product = $order_item->get_product();
		if ( $product ) {
			return $product->get_permalink();
		}
		return '';
	}

	/**
	 * Get the quantity from the order item.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_product_quantity( $order_item ) {
		return $order_item->get_quantity();
	}

	/**
	 * Get the product reference (sku) of the order item.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_product_reference( $order_item ) {
		$product = $order_item->get_product();

		if ( $product ) {
			if ( $product->get_sku() ) {
				$item_reference = $product->get_sku();
			} else {
				$item_reference = $product->get_id();
			}

			return substr( strval( $item_reference ), 0, 64 );
		}

		return '';
	}

	/**
	 * Get the order item tax rate.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_product_tax_rate( $order_item ) {
		$tax_rate = 0;
		$taxes    = $order_item->get_taxes();
		if ( ! empty( $taxes['total'] ) ) {
			foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
				if ( $tax_amount > 0 ) {
					$tax_rate = WC_Tax::get_rate_percent( $tax_id );
					break;
				}
			}
		}

		return $tax_rate;
	}

	/**
	 * Get the total amount for the order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_product_total_amount( $order_item ) {
		return self::format_number( $order_item->get_total() );
	}

	/**
	 * Get the total discounted amount by subtracting the total amount from the subtotal amount.
	 * If subtotal = 100, and discount = 20. Then total would be 80. So 100 - 80 = 20.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_product_total_discount_amount( $order_item ) {
		return self::format_number( $order_item->get_subtotal() - $order_item->get_total() );
	}

	/**
	 * Get the total tax amount for the order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_product_total_tax_amount( $order_item ) {
		return self::format_number( $order_item->get_total_tax() );
	}

	/**
	 * Get the product type, if its digital or physical.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_product_type( $order_item ) {
		$product = $order_item->get_product();
		if ( $product ) {
			if ( $product->is_virtual() || $product->is_downloadable() ) {
				return 'digital';
			}
		}
		return 'physical';
	}

	/**
	 * Get the unit price for the order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_product_unit_price( $order_item ) {
		return self::format_number( $order_item->get_subtotal() / $order_item->get_quantity() );
	}

	/**
	 * Get the subscription object for the order line. NOT YET SUPPORTED.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return array
	 */
	public static function get_product_subscription( $order_item ) {
		return array();
	}


	/**
	 * Returns the formated order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Shipping $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_kp_shipping_line( $order_item ) {
		return array(
			'image_url'             => null, // Not supported for shipping.
			'merchant_data'         => apply_filters( 'wc_kp_line_merchant_data', array(), $order_item ),
			'name'                  => self::get_shipping_name( $order_item ),
			'product_identifiers'   => self::get_shipping_identifiers( $order_item ),
			'product_url'           => null, // Not supported for fee.
			'quantity'              => 1, // Allways 1 for shipping lines.
			'quantity_unit'         => apply_filters( 'wc_kp_quantity_unit', 'pcs', $order_item ),
			'reference'             => self::get_shipping_reference( $order_item ),
			'tax_rate'              => self::get_shipping_tax_rate( $order_item ),
			'total_amount'          => self::get_shipping_total_amount( $order_item ),
			'total_discount_amount' => self::get_shipping_total_discount_amount( $order_item ),
			'total_tax_amount'      => self::get_shipping_total_tax_amount( $order_item ),
			'type'                  => 'shipping_fee', // Allways shipping_fee for shipping lines.
			'unit_price'            => self::get_shipping_unit_price( $order_item ),
			'subscription'          => self::get_shipping_subscription( $order_item ),
		);
	}

	/**
	 * Returns the formated order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Fee $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_kp_fee_line( $order_item ) {
		return array(
			'image_url'             => null, // Not supported for fee.
			'merchant_data'         => apply_filters( 'wc_kp_line_merchant_data', array(), $order_item ),
			'name'                  => self::get_fee_name( $order_item ),
			'product_identifiers'   => self::get_fee_identifiers( $order_item ),
			'product_url'           => null, // Not supported for fee.
			'quantity'              => 1, // Allways 1 for fee lines.
			'quantity_unit'         => apply_filters( 'wc_kp_quantity_unit', 'pcs', $order_item ),
			'reference'             => self::get_fee_reference( $order_item ),
			'tax_rate'              => self::get_fee_tax_rate( $order_item ),
			'total_amount'          => self::get_fee_total_amount( $order_item ),
			'total_discount_amount' => self::get_fee_total_discount_amount( $order_item ),
			'total_tax_amount'      => self::get_fee_total_tax_amount( $order_item ),
			'type'                  => 'surcharge', // Allways surcharge for fee lines.
			'unit_price'            => self::get_fee_unit_price( $order_item ),
			'subscription'          => self::get_fee_subscription( $order_item ),
		);
	}

	/**
	 * Returns the formated order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Coupon $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_kp_coupon_line( $order_item ) {
		return array(
			'image_url'             => null, // Not supported for coupon.
			'merchant_data'         => apply_filters( 'wc_kp_line_merchant_data', array(), $order_item ),
			'name'                  => self::get_coupon_name( $order_item ),
			'product_identifiers'   => self::get_coupon_identifiers( $order_item ),
			'product_url'           => null, // Not supported for fee.
			'quantity'              => 1, // Allways 1 for coupon lines.
			'quantity_unit'         => apply_filters( 'wc_kp_quantity_unit', 'pcs', $order_item ),
			'reference'             => self::get_coupon_reference( $order_item ),
			'tax_rate'              => self::get_coupon_tax_rate( $order_item ),
			'total_amount'          => self::get_coupon_total_amount( $order_item ),
			'total_discount_amount' => self::get_coupon_total_discount_amount( $order_item ),
			'total_tax_amount'      => self::get_coupon_total_tax_amount( $order_item ),
			'type'                  => 'discount', // Allways discount for coupon lines.
			'unit_price'            => self::get_coupon_unit_price( $order_item ),
			'subscription'          => self::get_coupon_subscription( $order_item ),
		);
	}
}
