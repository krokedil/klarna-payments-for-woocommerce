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
		$assets_path    = dirname( __DIR__, 2 ) . '/build/payment.asset.php';
		if ( file_exists( $assets_path ) ) {
			$assets = require $assets_path;
			wp_register_script( 'klarna-payments-block', WC_KLARNA_PAYMENTS_PLUGIN_URL . '/blocks/build/payment.js', $assets['dependencies'], $assets['version'], true );
		}
	}

	/**
	 * Method to register the payment blocks with WooCommerce.
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
		return array( 'klarna-payments-block' );
	}

	/**
	 * Gets the payment method data to load into the frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$features = array();
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['klarna_payments'] ) ) {
			$gateway  = $gateways['klarna_payments'];
			$features = $gateway->supports;
		}

		return array(
			'title'            => 'Klarna',
			'description'      => $this->get_setting( 'description' ),
			'iconurl'          => apply_filters( 'kp_blocks_logo', WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/klarna-logo.svg' ),
			'orderbuttonlabel' => apply_filters( 'kp_blocks_order_button_label', __( 'Pay with Klarna', 'klarna-payments-for-woocommerce' ) ),
			'features'         => $features,
		);
	}
}
