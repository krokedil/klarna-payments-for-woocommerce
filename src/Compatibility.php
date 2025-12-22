<?php
namespace Krokedil\Klarna;

use Krokedil\Klarna\Compatibility\CompatibilityFluidCheckout;

/**
 * Compatibility class.
 * Handles compatibility with third-party plugins.
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Compatibility_Fluid_Checkout class.
 */
class Compatibility {

	/**
	 * Register compatibility integrations.
	 *
	 * @return void
	 */
	public static function register() {
		// Initialize Fluid Checkout compatibility.
		CompatibilityFluidCheckout::init();
	}
}
