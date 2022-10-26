<?php
/**
 * Handles order lines for Klarna Payments from WooCommerce orders.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Order_Shipping_Helper class.
 *
 * Processes order shipping lines for Klarna Payments requests.
 */
class KP_Order_Shipping_Helper extends KP_Order_Helper {
	/**
	 * The WooCommerce product for the order line.
	 *
	 * @var WC_Product
	 */
	public static $product;

	/**
	 * Returns the formatted order line for Klarna payments.
	 *
	 * @param WC_Order_Item_Shipping $order_item The order item from WooCommerce.
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
	 * Get the product reference (sku) of the order item.
	 *
	 * @param WC_Order_Item_Shipping $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_reference( $order_item ) {
		$method_id   = $order_item->get_method_id();
		$instance_id = $order_item->get_instance_id();

		$shipping_reference = empty( $instance_id ) ? $method_id : $method_id . ':' . $instance_id;

		return apply_filters( 'wc_kp_reference_order_item', $shipping_reference, $order_item );
	}

	/**
	 * Returns the type of the order item.
	 *
	 * @param WC_Order_Item_Shipping $order_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_type( $order_item ) {
		return apply_filters( 'wc_kp_type_order_item', 'shipping_fee', $order_item );
	}
}
