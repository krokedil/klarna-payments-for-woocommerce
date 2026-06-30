<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Krokedil\Klarna\KlarnaExpressCheckout\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the integration with the WooCommerce cart blocks for the one step express checkout.
 *
 * @codeCoverageIgnore
 */
class OneStepBlocksIntegration implements IntegrationInterface {

	/**
	 * @inheritDoc
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'kec-one-step';
	}

	/**
	 * @inheritDoc
	 */
	public function get_script_data() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_script_handles() {
		return array( 'kec-one-step-block' );
	}

	/**
	 * @inheritDoc
	 */
	public function initialize() {
		$script_path = Assets::get_assets_path() . 'js/kec-one-step-block.js';
		wp_register_script(
			'kec-one-step-block',
			$script_path,
			array( 'wp-element', 'wp-i18n', 'wp-blocks', 'wc-blocks-registry', 'jquery' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);
	}
}
