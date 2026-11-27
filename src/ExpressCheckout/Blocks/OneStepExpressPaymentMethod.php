<?php

namespace Krokedil\Klarna\ExpressCheckout\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Krokedil\Klarna\ExpressCheckout\Assets;
use Krokedil\Klarna\ExpressCheckout\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the one-step KEC button in the express payment area of the checkout block.
 *
 * @codeCoverageIgnore
 */
class OneStepExpressPaymentMethod extends AbstractPaymentMethodType {
	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'kec_one_step';

	/**
	 * The KEC settings.
	 *
	 * @var Settings
	 */
	private $kec_settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $kec_settings The KEC settings.
	 */
	public function __construct( $kec_settings ) {
		$this->kec_settings = $kec_settings;
	}

	/**
	 * Register the script.
	 *
	 * @return void
	 */
	public function initialize() {
		wp_register_script(
			'kec-one-step-express',
			Assets::get_assets_path() . 'js/kec-one-step-express.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);
	}

	/**
	 * Whether the button should be available.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->kec_settings->is_enabled() && $this->kec_settings->show_on_checkout();
	}

	/**
	 * Get the script handles for the frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		return array( 'kec-one-step-express' );
	}

	/**
	 * Get the data passed to the frontend script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => __( 'Klarna Express Checkout', 'klarna-payments-for-woocommerce' ),
			'is_checkout' => is_checkout(),
		);
	}
}
