<?php
namespace Krokedil\Klarna;

use Krokedil\Klarna\Compatibility\FluidCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility class.
 * Handles compatibility with third-party plugins.
 */
class Compatibility {

	/**
	 * Register compatibility integrations.
	 *
	 * @return void
	 */
	public static function register() {
		// Initialize Fluid Checkout compatibility.
		FluidCheckout::init();
	}
}
