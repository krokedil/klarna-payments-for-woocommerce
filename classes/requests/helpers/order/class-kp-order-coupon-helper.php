<?php
/**
 * Handles order line coupons for Klarna Payments from WooCommerce orders.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Order_Coupon_Helper class.
 *
 * Processes order coupon lines for Klarna Payments requests.
 */
class KP_Order_Coupon_Helper extends KP_Order_Helper {
	/**
	 * The WooCommerce product for the order line.
	 *
	 * @var WC_Product
	 */
	public static $product;

	/**
	 * Returns the formatted order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Coupon $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_kp_order_line( $order_item ) {
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
	 * Returns the product name from the order line.
	 *
	 * @param WC_Order_Item_Coupon $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_name( $order_item ) {
		return apply_filters( 'wc_kp_name_order_item', $order_item->get_code(), $order_item );
	}

	/**
	 * Returns the Reference of the order line.
	 *
	 * @param WC_Order_Item_Coupon $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_reference( $order_item ) {
		return apply_filters( 'wc_kp_name_order_item', $order_item->get_code(), $order_item );
	}

	/**
	 * Get the total amount for the order line.
	 *
	 * @param WC_Order_Item_Coupon $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_total_amount( $order_item ) {
		return apply_filters( 'wc_kp_total_amount_order_item', self::format_number( $order_item->get_discount() ), $order_item );
	}

	/**
	 * Get the total tax amount for the order line.
	 *
	 * @param WC_Order_Item_Coupon $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_total_tax_amount( $order_item ) {
		return apply_filters( 'wc_kp_total_tax_amount_order_item', self::format_number( $order_item->get_discount_tax() ), $order_item );
	}

	/**
	 * Get the unit price for the order line.
	 *
	 * @param WC_Order_Item_Coupon $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_unit_price( $order_item ) {
		return apply_filters( 'wc_kp_unit_price_order_item', self::format_number( $order_item->get_discount() ), $order_item );
	}

	/**
	 * Returns the type of the order item.
	 *
	 * @param WC_Order_Item_Coupon $order_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_type( $order_item ) {
		return apply_filters( 'wc_kp_type_order_item', 'discount', $order_item );
	}

	/**
	 * Get the tax rate for any order line item, except coupons.
	 *
	 * @param WC_Order_Item_Coupon $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_tax_rate( $order_item ) {
		return apply_filters( 'wc_kp_tax_rate_order_item', 0, $order_item );
	}
}
