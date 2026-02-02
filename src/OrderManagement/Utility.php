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
		$hpos_enabled = kp_is_hpos_enabled();
		$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
		if ( empty( $order_id ) ) {
			return false;
		}

		return $order_id;
	}
}
