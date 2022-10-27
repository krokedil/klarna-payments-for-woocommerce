<?php
/**
 * Handles order lines for Klarna Payments from WooCommerce carts.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Order_Helper class.
 *
 * Processes cart lines for Klarna Payments requests.
 */
class KP_Cart_Helper extends KP_Order_Lines {

	/**
	 * The WooCommerce cart to be processed.
	 *
	 * @var WC_Cart
	 */
	public static $cart;

	/**
	 * Class constructor.
	 *
	 * @param WC_Cart $cart The WooCommerce cart to be processed.
	 */
	public function __construct( $cart ) {
		self::$cart = $cart;
	}

	/**
	 * Gets the KP order amount from a WooCommerce cart.
	 *
	 * @return int
	 */
	public static function get_kp_order_amount() {
		return self::format_number( self::$cart->get_total( 'kp_total' ) );
	}

	/**
	 * Gets the KP order tax from a WooCommerce cart.
	 *
	 * @return int
	 */
	public static function get_kp_order_tax_amount() {
		return self::format_number( self::$cart->get_total_tax() );
	}

	/**
	 * Gets the KP Order lines from a WooCommerce order.
	 *
	 * @return array
	 */
	public static function get_kp_order_lines() {
		$order_lines = array();

		$cart_items = self::$cart->get_cart();

		// Get cart items.
		foreach ( $cart_items as $cart_item ) {
			$order_lines[] = array_filter( KP_Cart_Item_Helper::get_kp_order_line( $cart_item ), 'KP_Order_Lines::remove_null' );
		}

		/**
		 * Get cart fees.
		 *
		 * @var $cart_fees WC_Cart_Fees
		 */
		$cart_fees = WC()->cart->get_fees();
		foreach ( $cart_fees as $fee ) {
			$order_lines[] = array_filter( KP_Cart_Fee_Helper::get_kp_order_line( $fee ), 'KP_Order_Lines::remove_null' );
		}

		// Get cart shipping.
		if ( WC()->cart->needs_shipping() ) {
			$shipping_ids   = array_unique( WC()->session->get( 'chosen_shipping_methods' ) );
			$shipping_rates = WC()->shipping->get_packages()[0]['rates'] ?? array();
			foreach ( $shipping_ids as  $shipping_id ) {
				if ( $shipping_rates[ $shipping_id ] ?? false ) {
					$order_lines[] = array_filter( KP_Cart_Shipping_Helper::get_kp_order_line( $shipping_rates[ $shipping_id ] ), 'KP_Order_Lines::remove_null' );
				}
			}
		}

		return apply_filters( 'wc_kp_order_lines_cart', array_values( $order_lines ), self::$cart );
	}

	/**
	 * Returns the product image url.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return string|null
	 */
	public static function get_image( $cart_item ) {
		return apply_filters( 'wc_kp_image_url_cart_item', null, $cart_item );
	}

	/**
	 * Returns the merchant data.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_merchant_data( $cart_item ) {
		return apply_filters( 'wc_kp_merchant_data_cart_item', array(), $cart_item );
	}

	/**
	 * Returns the product name from the order line.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_name( $cart_item ) {
		return apply_filters( 'wc_kp_name_cart_item', null, $cart_item );
	}

	/**
	 * Returns the product identifiers.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_identifiers( $cart_item ) {
		return apply_filters( 'wc_kp_identifiers_cart_item', array(), $cart_item );
	}

	/**
	 * Returns the product url.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return string|null
	 */
	public static function get_url( $cart_item ) {
		return apply_filters( 'wc_kp_url_cart_item', null, $cart_item );
	}

	/**
	 * Returns the quantity of the order line.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_quantity( $cart_item ) {
		return apply_filters( 'wc_kp_quantity_cart_item', 1, $cart_item );
	}

	/**
	 * Returns the quantity type of the order line.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_quantity_unit( $cart_item ) {
		return apply_filters( 'wc_kp_quantity_unit_cart_item', 'pcs', $cart_item );
	}

	/**
	 * Returns the Reference of the order line.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_reference( $cart_item ) {
		return apply_filters( 'wc_kp_reference_cart_item', null, $cart_item );
	}

	/**
	 * Get the tax rate for any order line item, except coupons.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_tax_rate( $cart_item ) {
		return apply_filters( 'wc_kp_tax_rate_cart_item', 0, $cart_item );
	}

	/**
	 * Get the total amount for any order line item, except coupons.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_amount( $cart_item ) {
		return apply_filters( 'wc_kp_total_amount_cart_item', 0, $cart_item );
	}

	/**
	 * Get the total discount amount for any order line item.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_discount_amount( $cart_item ) {
		return apply_filters( 'wc_kp_total_discount_amount_cart_item', 0, $cart_item );
	}

	/**
	 * Returns the tax amount for any order line item, except coupons.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_tax_amount( $cart_item ) {
		return apply_filters( 'wc_kp_total_tax_amount_cart_item', 0, $cart_item );
	}

	/**
	 * Returns the type of the order item.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_type( $cart_item ) {
		return apply_filters( 'wc_kp_type_cart_item', 'physical', $cart_item );
	}

	/**
	 * Returns the unit price for any order line item, except coupons.
	 *
	 * @param array|object $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_unit_price( $cart_item ) {
		return apply_filters( 'wc_kp_unit_price_cart_item', 0, $cart_item );
	}

	/**
	 * Get the subscription object for the order line. NOT YET SUPPORTED.
	 *
	 * @param array|object|WC_Product|WC_Shipping_Rate $cart_item The WooCommerce order item.
	 * @return array
	 */
	public static function get_subscription( $cart_item ) {
		return apply_filters( 'wc_kp_subscription_cart_item', array(), $cart_item );
	}
}
