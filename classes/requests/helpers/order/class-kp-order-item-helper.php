<?php
/**
 * Handles order lines for Klarna Payments from WooCommerce orders.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Order_Item_Helper class.
 *
 * Processes order lines for Klarna Payments requests.
 */
class KP_Order_Item_Helper extends KP_Order_Helper {
	/**
	 * The WooCommerce product for the order line.
	 *
	 * @var WC_Product
	 */
	public static $product;

	/**
	 * Returns the formatted order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Product $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_kp_order_line( $order_item ) {
		self::$product = $order_item->get_product();

		return array(
			'image_url'             => self::get_image( $order_item ),
			'merchant_data'         => self::get_merchant_data( $order_item ),
			'name'                  => self::get_name( $order_item ),
			'product_identifiers'   => self::get_identifiers( $order_item ),
			'product_url'           => self::get_url( $order_item ),
			'quantity'              => self::get_quantity( $order_item ),
			'quantity_unit'         => self::get_quantity_unit( $order_item ),
			'reference'             => self::get_reference( $order_item ),
			'tax_rate'              => self::get_tax_rate( $order_item ),
			'total_amount'          => self::get_total_amount( $order_item ),
			'total_discount_amount' => self::get_total_discount_amount( $order_item ),
			'total_tax_amount'      => self::get_total_tax_amount( $order_item ),
			'type'                  => self::get_type( $order_item ),
			'unit_price'            => self::get_unit_price( $order_item ),
			'subscription'          => self::get_subscription( $order_item ),
		);
	}

	/**
	 * Returns the product image url.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string|null
	 */
	public static function get_image( $order_item ) {
		$image_url = null;
		if ( self::$product ) {
			$image_url = wp_get_attachment_image_url( self::$product->get_image_id(), 'woocommerce_thumbnail' );

			if ( ! $image_url ) {
				$image_url = null;
			}
		}

		return apply_filters( 'wc_kp_image_url_order_item', $image_url, $order_item );
	}

	/**
	 * Get the product URL from the order item.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string|null
	 */
	public static function get_url( $order_item ) {
		$url = null;
		if ( self::$product ) {
			$url = self::$product->get_permalink();
		}

		return apply_filters( 'wc_kp_url_order_item', $url, $order_item );
	}

	/**
	 * Get the product reference (sku) of the order item.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_reference( $order_item ) {
		$item_reference = $order_item->get_id();
		if ( self::$product ) {
			if ( self::$product->get_sku() ) {
				$item_reference = self::$product->get_sku();
			} else {
				$item_reference = self::$product->get_id();
			}
		}

		return apply_filters( 'wc_kp_reference_order_item', substr( strval( $item_reference ), 0, 64 ), $order_item );
	}

	/**
	 * Get the total discount amount for any order line item.
	 *
	 * @param WC_Order_Item_Product $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_discount_amount( $order_item ) {
		return apply_filters( 'wc_kp_total_discount_amount_order_item', self::format_number( $order_item->get_subtotal() - $order_item->get_total() ) );
	}

	/**
	 * Get the product type, if its digital or physical.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_type( $order_item ) {
		$type = 'physical';

		if ( self::$product ) {
			if ( self::$product->is_virtual() || self::$product->is_downloadable() ) {
				$type = 'digital';
			}
		}
		return apply_filters( 'wc_kp_type_order_item', $type, $order_item );
	}
}
