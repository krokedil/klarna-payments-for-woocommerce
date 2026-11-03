<?php
namespace Krokedil\Klarna\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility class for the YITH WooCommerce Gift Cards plugin.
 *
 * YITH keeps the gift cards on the order as meta only, so recalculating the
 * order totals (as the edit order page does on save) drops them from the total.
 *
 * @see https://wordpress.org/plugins/yith-woocommerce-gift-cards/
 */
class YITHGiftCards {

	/**
	 * Initialize static hooks.
	 *
	 * @return void
	 */
	public static function init() {

		if ( ! defined( 'YITH_YWGC_VERSION' ) ) {
				return;
		}

		// Before YITH's own handler at priority 10, which only adds the fee on the second recalculation.
		add_action( 'woocommerce_order_before_calculate_totals', array( __CLASS__, 'add_gift_cards_as_fee' ), 9, 2 );
	}

	/**
	 * Add the gift cards applied to a Klarna order as a negative fee before its totals are recalculated.
	 *
	 * @param bool               $and_taxes Whether taxes are recalculated too.
	 * @param \WC_Abstract_Order $order The order being recalculated.
	 * @return void
	 */
	public static function add_gift_cards_as_fee( $and_taxes, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Kept to match the hook.
		if ( ! $order instanceof \WC_Order || 'klarna_payments' !== $order->get_payment_method() ) {
			return;
		}

		$gift_cards = $order->get_meta( '_ywgc_applied_gift_cards' );
		if ( empty( $gift_cards ) || ! is_array( $gift_cards ) || $order->has_status( 'refunded' ) ) {
			return;
		}

		if ( ! empty( $order->get_meta( 'ywgc_gift_card_updated_as_fee' ) ) ) {
			return;
		}

		$amount = $order->get_meta( '_ywgc_applied_gift_cards_totals' );
		$amount = '' !== $amount ? (float) $amount : (float) array_sum( $gift_cards );

		$fee = new \WC_Order_Item_Fee();
		$fee->set_props(
			array(
				'name'       => 'Gift Card (' . implode( ', ', array_keys( $gift_cards ) ) . ')',
				'total'      => round( -1 * $amount, 2 ),
				'tax_status' => 'none',
			)
		);

		$order->add_item( $fee );
		$order->update_meta_data( 'ywgc_gift_card_updated_as_fee', '1' );
	}
}
