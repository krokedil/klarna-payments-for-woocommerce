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
	 * The WooCommerce order to be processed.
	 *
	 * @var WC_Order
	 */
	public static $order;

	/**
	 * Class constructor.
	 *
	 * @param WC_Order $order The WooCommerce order to be processed.
	 */
	public function __construct( $order ) {
		self::$order = $order;
	}

	/**
	 * Gets the KP order amount from a WooCommerce order.
	 *
	 * @return int
	 */
	public static function get_kp_order_amount() {
		return self::format_number( self::$order->get_total() );
	}

	/**
	 * Gets the KP order tax from a WooCommerce order.
	 *
	 * @return int
	 */
	public static function get_kp_order_tax_amount() {
		return self::format_number( self::$order->get_total_tax() );
	}

	/**
	 * Gets the KP Order lines from a WooCommerce order.
	 *
	 * @return array
	 */
	public static function get_kp_order_lines() {
		$order_lines = array();

		/**
		 * Process order item products.
		 *
		 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
		 */
		foreach ( self::$order->get_items() as $order_item ) {
			$order_lines[] = array_filter( KP_Order_Item_Helper::get_kp_order_line( $order_item ), 'KP_Order_Lines::remove_null' );
		}

		/**
		 * Process order item shipping.
		 *
		 * @var WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
		 */
		foreach ( self::$order->get_items( 'shipping' ) as $order_item ) {
			$order_lines[] = array_filter( KP_Order_Shipping_Helper::get_kp_order_line( $order_item ), 'KP_Order_Lines::remove_null' );
		}

		/**
		 * Process order item fee.
		 *
		 * @var WC_Order_Item_Fee $order_item WooCommerce order item fee.
		 */
		foreach ( self::$order->get_items( 'fee' ) as $order_item ) {
			$order_lines[] = array_filter( KP_Order_Fee_Helper::get_kp_order_line( $order_item ), 'KP_Order_Lines::remove_null' );
		}

		return apply_filters( 'wc_kp_order_lines_order', array_values( $order_lines ), self::$order );
	}

	/**
	 * Returns the product image url.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return string|null
	 */
	public static function get_image( $order_item ) {
		return apply_filters( 'wc_kp_image_url_order_item', null, $order_item );
	}

	/**
	 * Returns the merchant data.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_merchant_data( $order_item ) {
		return apply_filters( 'wc_kp_merchant_data_order_item', array(), $order_item );
	}

	/**
	 * Returns the product name from the order line.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_name( $order_item ) {
		return apply_filters( 'wc_kp_name_order_item', $order_item->get_name(), $order_item );
	}

	/**
	 * Returns the product identifiers.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return array
	 */
	public static function get_identifiers( $order_item ) {
		return apply_filters( 'wc_kp_identifiers_order_item', array(), $order_item );
	}

	/**
	 * Returns the product url.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return string|null
	 */
	public static function get_url( $order_item ) {
		return apply_filters( 'wc_kp_url_order_item', null, $order_item );
	}

	/**
	 * Returns the quantity of the order line.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_quantity( $order_item ) {
		return apply_filters( 'wc_kp_quantity_order_item', $order_item->get_quantity(), $order_item );
	}

	/**
	 * Returns the quantity type of the order line.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_quantity_unit( $order_item ) {
		return apply_filters( 'wc_kp_quantity_unit_order_item', 'pcs', $order_item );
	}

	/**
	 * Returns the Reference of the order line.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_reference( $order_item ) {
		return apply_filters( 'wc_kp_reference_order_item', $order_item->get_id(), $order_item );
	}

	/**
	 * Get the tax rate for any order line item, except coupons.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_tax_rate( $order_item ) {
		$tax_rate = 0;
		$taxes    = $order_item->get_taxes();
		if ( ! empty( $taxes['total'] ) ) {
			foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
				if ( $tax_amount > 0 ) {
					$tax_rate = WC_Tax::get_rate_percent_value( $tax_id ) * 100;
					break;
				}
			}
		}

		return apply_filters( 'wc_kp_tax_rate_order_item', $tax_rate, $order_item );
	}

	/**
	 * Get the total amount for any order line item, except coupons.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_amount( $order_item ) {
		return apply_filters( 'wc_kp_total_amount_order_item', self::format_number( $order_item->get_total() + $order_item->get_total_tax() ), $order_item );
	}

	/**
	 * Get the total discount amount for any order line item.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_discount_amount( $order_item ) {
		return apply_filters( 'wc_kp_total_discount_amount_order_item', 0, $order_item );
	}

	/**
	 * Returns the tax amount for any order line item, except coupons.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_tax_amount( $order_item ) {
		return apply_filters( 'wc_kp_total_tax_amount_order_item', self::format_number( $order_item->get_total_tax() ), $order_item );
	}

	/**
	 * Returns the type of the order item.
	 *
	 * @param WC_Order_Item $order_item The order item from WooCommerce.
	 * @return string
	 */
	public static function get_type( $order_item ) {
		return apply_filters( 'wc_kp_type_order_item', 'physical', $order_item );
	}

	/**
	 * Returns the unit price for any order line item, except coupons.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_unit_price( $order_item ) {
		return apply_filters( 'wc_kp_unit_price_order_item', self::format_number( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() ), $order_item );
	}

	/**
	 * Get the subscription object for the order line. NOT YET SUPPORTED.
	 *
	 * @param WC_Order_Item $order_item The WooCommerce order item.
	 * @return array
	 */
	public static function get_subscription( $order_item ) {
		return apply_filters( 'wc_kp_subscription_order_item', array(), $order_item );
	}
}
