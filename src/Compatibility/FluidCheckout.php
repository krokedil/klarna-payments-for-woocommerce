<?php
namespace Krokedil\Klarna\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility class for the Fluid Checkout for WooCommerce plugin.
 *
 * @see https://wordpress.org/plugins/fluid-checkout/
 */
class FluidCheckout {

	/**
	 * Selector for Fluid Checkout's place order button.
	 *
	 * @var string
	 */
	private static $fc_submit_selector = '.fc-place-order-button';

	/**
	 * Initialize static hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// If Fluid Checkout is not active, bail.
		if ( ! class_exists( 'FluidCheckout' ) ) {
			return;
		}

		add_filter( 'wc_kp_checkout_params', array( __CLASS__, 'add_fc_submit_selector' ), 10, 1 );
	}

	/**
	 * Add Fluid Checkout's place order button selector to the submit order selectors.
	 *
	 * @param array $params Klarna Payments checkout parameters.
	 * @return array Modified Klarna Payments checkout parameters.
	 */
	public static function add_fc_submit_selector( $params ) {

		if ( ! in_array( self::$fc_submit_selector, $params['submit_button_selectors'], true ) ) {
			$params['submit_button_selectors'][] = self::$fc_submit_selector;
		}

		return $params;
	}
}
