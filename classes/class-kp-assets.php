<?php
/**
 * Used for inserting JavaScript and CSS files, conditionally.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KP_Assets class.
 */
class KP_Assets {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		/* Klarna Express Checkout (aka Express Button). */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_express_button' ) );
		add_action( 'script_loader_tag', array( $this, 'express_button_script_tag' ), 10, 2 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'express_button_placement' ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'express_button_placement' ), 15 );
	}

	/**
	 * Conditionally enqueue the scripts and styles required for Express Button.
	 *
	 * @return void
	 */
	public function enqueue_express_button() {

		if ( ! apply_filters( 'kp_enable_express_button', false ) ) {
			return;
		}

		$kp_settings = get_option( 'woocommerce_klarna_payments_settings' );
		if ( 'yes' !== $kp_settings['express_enabled'] || 'yes' !== $kp_settings['enabled'] ) {
			return;
		}

		/* If there is not corresponding MID for the customer's country, we'll abort. */
		$purchase_country = strtolower( kp_get_klarna_country() );
		$mode             = ( 'yes' === $kp_settings['testmode'] ) ? 'test_' : '';
		if ( empty( $kp_settings[ $mode . 'merchant_id_' . $purchase_country ] ) || empty( $kp_settings[ $mode . 'shared_secret_' . $purchase_country ] ) ) {
			return;
		}

		$this->enqueue_express_button_scripts();
		$this->enqueue_express_button_styles();

	}

	/**
	 * Add extra attributes to the Klarna script tag.
	 *
	 * @param string $tag The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @return string
	 */
	public function express_button_script_tag( $tag, $handle ) {

		if ( 'klarna_express_button_library' !== $handle ) {
			return $tag;
		}

		$kp_settings = get_option( 'woocommerce_klarna_payments_settings' );

		/* If there is no corresponding MID for the customer's country set, we'll abort. */
		$purchase_country = strtolower( kp_get_klarna_country() );
		$mode             = ( 'yes' === $kp_settings['testmode'] ) ? 'test_' : '';
		$merchant_id      = esc_attr( preg_replace( '/_.*$/', '', $kp_settings[ $mode . 'merchant_id_' . $purchase_country ] ) );
		if ( empty( $merchant_id ) || empty( $kp_settings[ $mode . 'shared_secret_' . $purchase_country ] ) ) {
			return $tag;
		}

		$environment = ( 'yes' === $kp_settings['testmode'] ) ? 'playground' : 'production';

		return str_replace( ' src', " data-id='{$merchant_id}' data-environment='{$environment}' async src", $tag );
	}

	/**
	 * Prepend the Express Button before the 'Proceed to checkout' button.
	 *
	 * @return void
	 */
	public function express_button_placement() {
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings' );

		/* We're guaranteed to be on the cart page, so we don't have to check for is_cart. */
		if ( 'yes' !== $kp_settings['express_enabled'] || 'yes' !== $kp_settings['enabled'] ) {
			return;
		}

		/* If there is no corresponding MID for the customer's country set, we'll abort. */
		$purchase_country = strtolower( kp_get_klarna_country() );
		$mode             = ( 'yes' === $kp_settings['testmode'] ) ? 'test_' : '';
		if ( empty( $kp_settings[ $mode . 'merchant_id_' . $purchase_country ] ) || empty( $kp_settings[ $mode . 'shared_secret_' . $purchase_country ] ) ) {
			return;
		}

		$country_code = strtoupper( $purchase_country );
		$locale       = esc_attr( preg_replace( '/-.*/', "-{$country_code}", kp_get_locale() ) );

		$supported_countries = array(
			'US',
			'CA',
			'GB',
			'FR',
			'PL',
			'NL',
			'BE',
			'IE',
			'ES',
			'IT',
			'PT',
			'AT',
			'DE',
			'DK',
			'AU',
			'NZ',
		);

		if ( ! in_array( $country_code, $supported_countries, true ) ) {
			return;
		}

		$theme = esc_attr( $kp_settings['express_data_theme'] );
		$shape = esc_attr( $kp_settings['express_data_shape'] );
		$label = esc_attr( $kp_settings['express_data_label'] );

		/* This is the supported button size (refer to Klarna documentation for Express Button). */
		$style = '';
		if ( is_cart() ) {
			$width  = intval( $kp_settings['express_data_width'] );
			$width  = ( 145 <= $width && 500 >= $width ) ? $width : '';
			$height = intval( $kp_settings['express_data_height'] );
			$height = ( 35 <= $width && 60 >= $height ) ? $height : '';

			if ( ! empty( $width ) ) {
				$style .= esc_attr( "width:{$width}px;" );
			}
			if ( ! empty( $height ) ) {
				$style .= esc_attr( "height:{$height}px;" );
			}
		} else {
			/* The custom button sizes should not apply to the mini-cart, instead we use the following: */
			$style .= 'width:100%;';
		}

		// phpcs:ignore -- The variables are already escaped.
		echo "<klarna-express-button data-locale='$locale' data-theme='$theme' data-shape='$shape' data-label='$label'" . ( ! empty( $style ) ? "style='$style'" : '' ) . '></klarna-express-button>';
	}

	/**
	 * The scripts required for Express Button (also see _styles).
	 *
	 * @return void
	 */
	private function enqueue_express_button_scripts() {

		// phpcs:ignore -- The version should NOT be added.
		wp_register_script( 'klarna_express_button_library', 'https://x.klarnacdn.net/express-button/v1/lib.js', array(), null, false );
		wp_enqueue_script( 'klarna_express_button_library' );

		wp_register_script(
			'klarna_express_button',
			plugins_url( 'assets/js/klarna-express-button.js', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array( 'klarna_express_button_library' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);

		$klarna_payments_express_button_params = array(
			'express_button_url'   => WC_AJAX::get_endpoint( 'kp_wc_express_button' ),
			'express_button_nonce' => wp_create_nonce( 'kp_wc_express_button' ),
		);
		wp_localize_script( 'klarna_express_button', 'klarna_payments_express_button_params', $klarna_payments_express_button_params );
		wp_enqueue_script( 'klarna_express_button' );
	}

	/**
	 * The styles required for Express Button (also see _scripts).
	 *
	 * @return void
	 */
	private function enqueue_express_button_styles() {

		wp_register_style(
			'klarna_express_button_styles',
			plugins_url( 'assets/css/klarna-express-button.css', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array(),
			WC_KLARNA_PAYMENTS_VERSION,
		);

		wp_enqueue_style( 'klarna_express_button_styles' );

	}

}

new KP_Assets();
