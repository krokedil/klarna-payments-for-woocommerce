<?php
/**
 * Compatibility class for integrating with the Fluid Checkout for WooCommerce plugin.
 *
 * @see https://wordpress.org/plugins/fluid-checkout/
 * @package WC_Klarna_Payments/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Compatibility_Fluid_Checkout class.
 */
class KP_Compatibility_Fluid_Checkout {

	/**
	 * Selector for Fluid Checkout's place order button.
	 *
	 * @var string
	 */
	private $fc_submit_selector = '.fc-place-order-button';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// If Fluid Checkout is not active, bail.
		if ( ! class_exists( 'FluidCheckout' ) ) {
			return;
		}

		add_filter( 'wc_kp_checkout_params', array( $this, 'add_fc_submit_selector' ), 10, 1 );
	}

	/**
	 * Add Fluid Checkout's place order button selector to the submit order selectors.
	 *
	 * @param array $params Klarna Payments checkout parameters.
	 * @return array Modified Klarna Payments checkout parameters.
	 */
	public function add_fc_submit_selector( $params ) {

		if ( ! in_array( $this->fc_submit_selector, $params['submit_button_selectors'], true ) ) {
			$params['submit_button_selectors'][] = $this->fc_submit_selector;
		}

		return $params;
	}
}
