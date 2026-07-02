<?php

namespace Krokedil\Klarna\ExpressCheckout\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Krokedil\Klarna\ExpressCheckout\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the integration with the WooCommerce cart blocks for the one step express checkout.
 *
 * @codeCoverageIgnore
 */
class OneStepBlocksIntegration implements IntegrationInterface {

	/**
	 * Get the script handles used in the editor.
	 *
	 * @return array
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Get the unique name for this integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kec-one-step';
	}

	/**
	 * Get data passed to the integration script.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array();
	}

	/**
	 * Get the script handles registered by this integration.
	 *
	 * @return array
	 */
	public function get_script_handles() {
		return array( 'kec-one-step-block' );
	}

	/**
	 * Register scripts and assets for the integration.
	 *
	 * @return void
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
