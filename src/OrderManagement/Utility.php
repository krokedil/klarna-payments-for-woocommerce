<?php
namespace Krokedil\Klarna\OrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Utility class.
 */
class Utility {
	/**
	 * Equivalent to WP's get_the_ID() with HPOS support.
	 *
	 * @return int|false The order ID or false.
	 */
	public static function get_the_ID() {
		$hpos_enabled = self::is_hpos_enabled();
		$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
		if ( empty( $order_id ) ) {
			return false;
		}

		return $order_id;
	}

	/**
	 * Whether HPOS is enabled.
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled() {
		// CustomOrdersTableController was introduced in WC 6.4.
		if ( class_exists( CustomOrdersTableController::class ) ) {
			return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
		}

		return false;
	}
}
