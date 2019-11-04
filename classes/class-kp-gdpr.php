<?php
/**
 * Adds a privacy declaration text for Klarna Payments.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Compliance with European Union's General Data Protection Regulation.
 *
 * @class    WC_Klarna_GDPR
 * @version  1.0.0
 * @package  WC_Klarna_Payments/Classes
 * @category Class
 * @author   Krokedil
 */
class KP_GDPR {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'privacy_declarations' ) );
	}
	/**
	 * Privacy declarations.
	 *
	 * @return void
	 */
	public function privacy_declarations() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			// @codingStandardsIgnoreStart
			$content =
				__(
					'When you place an order in the webstore with Klarna Payments as the choosen payment method, ' .
					'information about the products in the order (namne, price, quantity, SKU) is sent to Klarna ' .
					'together with your billing and shipping address. Klarna then responds with a unique transaction ID.' .
					'This ID is stored in the order in WooCommerce for future reference.',
					'klarna-payments-for-woocommerce'
				);
			// @codingStandardsIgnoreEnd
			wp_add_privacy_policy_content(
				'Klarna Payments for WooCommerce',
				wp_kses_post( wpautop( $content ) )
			);
		}
	}
}
new KP_GDPR();
