<?php
namespace Krokedil\Klarna;

use Krokedil\Klarna\Compatibility\FluidCheckout;
use Krokedil\Klarna\Compatibility\YITHGiftCards;

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

		// Initialize YITH Gift Cards compatibility.
		YITHGiftCards::init();
	}
}
