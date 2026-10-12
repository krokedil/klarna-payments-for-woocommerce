<?php
/**
 * Class stubs for PHPStan static analysis only.
 *
 * Declares WooCommerce internal classes the plugin guards with is_callable(), which php-stubs/woocommerce-stubs omits.
 *
 * @package WC_Klarna_Payments
 *
 * @phpcs:disable
 */

namespace Automattic\WooCommerce\Internal\Utilities;

class Users {
	/**
	 * @param int         $order_id       Order ID.
	 * @param string|null $supplied_email Supplied email.
	 * @param string      $context        Context in which we are checking the email.
	 * @return bool
	 */
	public static function should_user_verify_order_email( $order_id, $supplied_email = null, $context = 'view' ) {}
}
