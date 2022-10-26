<?php
/**
 * Handles order line items for Klarna Payments from WooCommerce cart.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Cart_Item_Helper class.
 *
 * Processes cart item lines for Klarna Payments requests.
 */
class KP_Cart_Item_Helper extends KP_Cart_Helper {

	/**
	 * The WooCommerce product from the cart item.
	 *
	 * @var WC_Product
	 */
	protected static $product;

	/**
	 * Gets the KP cart line from a WooCommerce cart item.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return array
	 */
	public static function get_kp_order_line( $cart_item ) {
		// Set the WooCommerce product from the cart item.
		if ( $cart_item['variation_id'] ) {
			self::$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			self::$product = wc_get_product( $cart_item['product_id'] );
		}

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
	 * Returns the product image url.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return string|null
	 */
	public static function get_image( $cart_item ) {
		$image_url = null;
		if ( self::$product ) {
			$image_url = wp_get_attachment_image_url( self::$product->get_image_id(), 'woocommerce_thumbnail' );
		}

		if ( ! $image_url ) {
			$image_url = null;
		}

		return apply_filters( 'wc_kp_image_url_cart_item', $image_url, $cart_item );
	}

	/**
	 * Returns the item name.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return string
	 */
	public static function get_name( $cart_item ) {
		$name = $cart_item['data']->get_name();

		return apply_filters( 'wc_kp_name_cart_item', $name, $cart_item );
	}

	/**
	 * Returns the product url.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return string|null
	 */
	public static function get_url( $cart_item ) {
		$url = null;
		if ( self::$product ) {
			$url = self::$product->get_permalink();
		}

		return apply_filters( 'wc_kp_url_cart_item', $url, $cart_item );
	}

	/**
	 * Returns the item quantity.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return int
	 */
	public static function get_quantity( $cart_item ) {
		$quantity = $cart_item['quantity'];

		return apply_filters( 'wc_kp_quantity_cart_item', $quantity, $cart_item );
	}

	/**
	 * Returns the item reference.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return string
	 */
	public static function get_reference( $cart_item ) {
		$item_reference = $cart_item['data']->get_id();
		if ( self::$product ) {
			if ( self::$product->get_sku() ) {
				$item_reference = self::$product->get_sku();
			} else {
				$item_reference = self::$product->get_id();
			}
		}
		return apply_filters( 'wc_kp_reference_cart_item', $item_reference, $cart_item );
	}

	/**
	 * Get the tax rate for any order line item.
	 *
	 * @param array $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_tax_rate( $cart_item ) {
		if ( self::$product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			$_tax      = new WC_Tax();
			$tmp_rates = $_tax->get_rates( self::$product->get_tax_class() );
			$vat       = array_shift( $tmp_rates );
			if ( isset( $vat['rate'] ) ) {
				$item_tax_rate = round( $vat['rate'] * 100 );
			} else {
				$item_tax_rate = 0;
			}
		} else {
			$item_tax_rate = 0;
		}
		return apply_filters( 'wc_kp_tax_rate_cart_item', round( $item_tax_rate ), $cart_item );
	}

	/**
	 * Get the total amount for any order line item, except coupons.
	 *
	 * @param array $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_amount( $cart_item ) {
		$total_amount = $cart_item['line_total'] + $cart_item['line_tax'];
		return apply_filters( 'wc_kp_total_amount_cart_item', self::format_number( $total_amount ), $cart_item );
	}

	/**
	 * Get the total discount amount for any order line item.
	 *
	 * @param array $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_discount_amount( $cart_item ) {
		$total_discount_amount = $cart_item['line_subtotal'] - $cart_item['line_total'];
		return apply_filters( 'wc_kp_total_discount_amount_cart_item', self::format_number( $total_discount_amount ), $cart_item );
	}

	/**
	 * Returns the tax amount for any order line item, except coupons.
	 *
	 * @param array $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_total_tax_amount( $cart_item ) {
		$total_tax_amount = $cart_item['line_tax'];
		return apply_filters( 'wc_kp_total_tax_amount_cart_item', self::format_number( $total_tax_amount ), $cart_item );
	}

	/**
	 * Returns the item type.
	 *
	 * @param array $cart_item The WooCommerce cart item.
	 * @return string
	 */
	public static function get_type( $cart_item ) {
		$type = 'physical';

		if ( self::$product ) {
			if ( self::$product->is_virtual() || self::$product->is_downloadable() ) {
				$type = 'digital';
			}
		}

		return apply_filters( 'wc_kp_type_cart_item', $type, $cart_item );
	}

	/**
	 * Returns the unit price for any order line item, except coupons.
	 *
	 * @param array $cart_item The order item from WooCommerce.
	 * @return int
	 */
	public static function get_unit_price( $cart_item ) {
		$unit_price = ( $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] ) / $cart_item['quantity'];
		return apply_filters( 'wc_kp_unit_price_cart_item', self::format_number( $unit_price ), $cart_item );
	}
}
