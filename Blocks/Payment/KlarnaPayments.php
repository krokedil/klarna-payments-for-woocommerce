<?php //phpcs:ignore
/**
 * Klarna payments payment block for WooCommerce
 *
 * @package WooCommerce/Blocks
 */

namespace KlarnaPayments\Blocks\Payments;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class for KP Payment Blocks.
 */
class KlarnaPayments extends AbstractPaymentMethodType {
	/**
	 * Payment method name. Matches gateway ID.
	 *
	 * @var string
	 */
	protected $name = 'klarna_payments';

	/**
	 * Initializes the settings for the plugin.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_klarna_payments_settings', array() );
	}

	/**
	 * Method to register the paymnet blocks with WooCommerce.
	 *
	 * @return void
	 */
	public static function register() {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new static() );
			}
		);

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_style' ) );
	}

	/**
	 * Enqueues the style needed for the payment block.
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_register_style(
			'kp-checkout-block',
			plugins_url( 'build/klarna-payments-block.css', __FILE__ ),
			array(),
			WC_KLARNA_PAYMENTS_VERSION
		);

		wp_enqueue_style( 'kp-checkout-block' );
	}

	/**
	 * Checks if the payment method is active or not.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return 'yes' === $this->get_setting( 'enabled', 'no' );
	}

	/**
	 * Loads the payment method scripts.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$version      = WC_KLARNA_PAYMENTS_VERSION;
		$path         = plugins_url( 'build/klarna-payments-block.js', __FILE__ );
		$handle       = 'kp-checkout-block';
		$dependencies = array( 'wp-hooks' );

		wp_register_script( $handle, $path, $dependencies, $version, true );

		return array( 'kp-checkout-block' );
	}

	/**
	 * Gets the payment method data to load into the frontend.
	 *
	 * @return string
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'iconurl'     => apply_filters( 'kp_blocks_logo', WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/klarna-logo.svg' ),
		);
	}
}
