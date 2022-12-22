<?php
/**
 * Handles order line shipping for Klarna Payments from WooCommerce cart.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Cart_Shipping_Helper class.
 *
 * Processes cart shipping lines for Klarna Payments requests.
 */
class KP_Cart_Shipping_Helper extends KP_Cart_Helper {
	/**
	 * Gets the KP cart line from a WooCommerce cart item.
	 *
	 * @param WC_Shipping_Rate $cart_item The WooCommerce cart item.
	 * @return array
	 */
	public static function get_kp_order_line( $cart_item ) {
		return array(
			'image_url'             => self::get_image( $cart_item ),
			'merchant_data'         => self::get_merchant_data( $cart_item ),
			'name'                  => self::get_name( $cart_item ),
			'product_identifiers'   => self::get_identifiers( $cart_item ),
			'product_url'           => self::get_url( $cart_item ),
			'quantity'              => self::get_quantity( $cart_item ),
			'quantity_unit'         => self::get_quantity_unit( $cart_item ),
			'reference'             => self::get_reference( $cart_item ),
			'tax_rate'              => self::get_tax_rate( $cart_item ),
			'total_amount'          => self::get_total_amount( $cart_item ),
			'total_discount_amount' => self::get_total_discount_amount( $cart_item ),
			'total_tax_amount'      => self::get_total_tax_amount( $cart_item ),
			'type'                  => self::get_type( $cart_item ),
			'unit_price'            => self::get_unit_price( $cart_item ),
			'subscription'          => self::get_subscription( $cart_item ),
		);
	}

	/**
	 * Returns the item name.
	 *
	 * @param WC_Shipping_Rate $cart_item The WooCommerce cart item.
	 * @return string
	 */
	public static function get_name( $cart_item ) {
		return apply_filters( 'wc_kp_name_cart_item', $cart_item->get_label(), $cart_item );
	}

	/**
	 * Returns the item reference.
	 *
	 * @param WC_Shipping_Rate $cart_item The WooCommerce cart item.
	 * @return string
	 */
	public static function get_reference( $cart_item ) {
		return apply_filters( 'wc_kp_reference_cart_item', $cart_item->get_id(), $cart_item );
	}

	/**
	 * Get the tax rate for any order line item.
	 *
	 * @param WC_Shipping_Rate $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_tax_rate( $cart_item ) {
		$item_tax_rate = 0;

		// Get the first key from the tax rates array.
		$taxes = $cart_item->get_taxes();

		if ( ! empty( $taxes ) ) {
			$tax_rate_id   = array_key_first( $taxes );
			$_tax          = new WC_Tax();
			$vat           = $_tax->get_rate_percent_value( $tax_rate_id );
			$item_tax_rate = round( $vat * 100 );
		}

		return apply_filters( 'wc_kp_tax_rate_cart_item', round( $item_tax_rate ), $cart_item );
	}

	/**
	 * Get the total amount for any order line item, except coupons.
	 *
	 * @param WC_Shipping_Rate $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_amount( $cart_item ) {
		return apply_filters( 'wc_kp_total_amount_cart_item', self::format_number( $cart_item->get_cost() + $cart_item->get_shipping_tax() ), $cart_item );
	}

	/**
	 * Returns the tax amount for any order line item, except coupons.
	 *
	 * @param WC_Shipping_Rate $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_tax_amount( $cart_item ) {
		return apply_filters( 'wc_kp_total_tax_amount_cart_item', self::format_number( $cart_item->get_shipping_tax() ), $cart_item );
	}

	/**
	 * Returns the item type.
	 *
	 * @param WC_Shipping_Rate $cart_item The WooCommerce cart item.
	 * @return string
	 */
	public static function get_type( $cart_item ) {
		return apply_filters( 'wc_kp_type_cart_item', 'shipping_fee', $cart_item );
	}

	/**
	 * Returns the unit price for any order line item, except coupons.
	 *
	 * @param WC_Shipping_Rate $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_unit_price( $cart_item ) {
		return apply_filters( 'wc_kp_unit_price_cart_item', self::format_number( $cart_item->get_cost() + $cart_item->get_shipping_tax() ), $cart_item );
	}
}
