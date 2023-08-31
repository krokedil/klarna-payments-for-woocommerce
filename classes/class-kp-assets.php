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
		/* Klarna Payments scripts */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_script' ) );

		/* Klarna Express Checkout (aka Express Button). */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_express_button' ) );
		add_action( 'script_loader_tag', array( $this, 'express_button_script_tag' ), 10, 2 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'express_button_placement' ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'express_button_placement' ), 15 );
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_checkout_script() {
		// We do not need to enqueue scripts on the change subscription payment method page since we'll redirect the customer to Klarna's HPP.
		if ( ! kp_is_checkout_page() || KP_Subscription::is_change_payment_method() ) {
			return;
		}

		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );
		if ( 'yes' !== $settings['enabled'] ?? 'no' ) {
			return;
		}

		$klarna_payments_params = $this->get_checkout_params( $settings );

		wp_register_script(
			'klarna_payments',
			plugins_url( 'assets/js/klarna-payments.js', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array( 'jquery', 'wc-checkout', 'jquery-blockui' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);

		wp_localize_script( 'klarna_payments', 'klarna_payments_params', $klarna_payments_params );
		wp_enqueue_script( 'klarna_payments' );

		wp_register_script( 'klarnapayments', 'https://x.klarnacdn.net/kp/lib/v1/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_enqueue_script( 'klarnapayments' );
	}

	/**
	 * Get the params for the Klarna Payments checkout script.
	 *
	 * @param array $settings The Klarna Payments settings.
	 * @return array
	 */
	private function get_checkout_params( $settings ) {
		// Set needed variables for the order pay page handling.
		$pay_for_order = kp_is_order_pay_page();
		$key           = $pay_for_order ? filter_input( INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS ) : null;
		$order_id      = $pay_for_order ? wc_get_order_id_by_order_key( $key ) : null;

		$customer_type = $settings['customer_type'] ?? 'b2c';
		$order_data    = new KP_Order_Data( $customer_type );
		$customer      = $order_data->get_klarna_customer_object();

		// Create the params array.
		$klarna_payments_params = array(
			// Ajax URLS.
			'ajaxurl'                => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'place_order_url'        => WC_AJAX::get_endpoint( 'kp_wc_place_order' ),
			'place_order_nonce'      => wp_create_nonce( 'kp_wc_place_order' ),
			'auth_failed_url'        => WC_AJAX::get_endpoint( 'kp_wc_auth_failed' ),
			'auth_failed_nonce'      => wp_create_nonce( 'kp_wc_auth_failed' ),
			'update_session_url'     => WC_AJAX::get_endpoint( 'kp_wc_update_session' ),
			'update_session_nonce'   => wp_create_nonce( 'kp_wc_update_session' ),
			'log_to_file_url'        => WC_AJAX::get_endpoint( 'kp_wc_log_js' ),
			'log_to_file_nonce'      => wp_create_nonce( 'kp_wc_log_js' ),
			'submit_order'           => WC_AJAX::get_endpoint( 'checkout' ),
			// Params.
			'testmode'               => $settings['testmode'] ?? 'no',
			'customer_type'          => $customer_type,
			'remove_postcode_spaces' => ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? 'yes' : 'no',
			'client_token'           => KP_WC()->session->get_klarna_client_token(),
			'order_pay_page'         => $pay_for_order,
			'pay_for_order'          => $pay_for_order,
			'order_id'               => $order_id,
			'addresses'              => $pay_for_order ? array(
				'billing'  => $customer['billing'],
				'shipping' => $customer['shipping'],
			) : null,
		);

		// Return with filter incase some people want to modify the params.
		return apply_filters( 'wc_kp_checkout_params', $klarna_payments_params );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @hook admin_enqueue_scripts
	 */
	public function enqueue_admin_script( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( empty( $section ) || 'klarna_payments' !== $section ) {
			return;
		}

		wp_enqueue_script(
			'klarna_payments_admin',
			plugins_url( 'assets/js/klarna-payments-admin.js', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array(),
			WC_KLARNA_PAYMENTS_VERSION,
			false
		);
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
		if ( ! apply_filters( 'kp_enable_express_button', false ) ) {
			return $tag;
		}

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
		if ( ! apply_filters( 'kp_enable_express_button', false ) ) {
			return;
		}

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
		$locale       = esc_attr( apply_filters( 'kp_express_button_locale', kp_get_locale() ) );

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
		if ( ! apply_filters( 'kp_enable_express_button', false ) ) {
			return;
		}

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
		if ( ! apply_filters( 'kp_enable_express_button', false ) ) {
			return;
		}

		wp_register_style(
			'klarna_express_button_styles',
			plugins_url( 'assets/css/klarna-express-button.css', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array(),
			WC_KLARNA_PAYMENTS_VERSION
		);

		wp_enqueue_style( 'klarna_express_button_styles' );

	}

}

new KP_Assets();
