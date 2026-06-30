<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout;

use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;

defined( 'ABSPATH' ) || exit;

/**
 * Assets class for Klarna Express Checkout.
 */
class Assets {
	/**
	 * The path to the assets directory.
	 *
	 * @var string
	 */
	private $assets_path;

	/**
	 * Settings class instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The locale to use for the KEC integration.
	 *
	 * @var string|bool
	 */
	private $locale;

	/**
	 * Assets constructor.
	 *
	 * @param Settings    $settings The KEC settings instance.
	 * @param string|bool $locale   The locale to use. Defaults to browser locale.
	 */
	public function __construct( $settings, $locale = false ) {
		$this->assets_path = self::get_assets_path();
		$this->settings    = $settings;
		$this->locale      = $locale;

		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_one_step_type' ), 10, 2 );
	}

	/**
	 * Get the URL to the assets folder.
	 *
	 * @return string
	 */
	public static function get_assets_path() {
		return plugin_dir_url( __DIR__ ) . 'assets/';
	}

	/**
	 * Register all KEC scripts and styles.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'kec-cart', "{$this->assets_path}css/kec-cart.css", array(), WC_KLARNA_PAYMENTS_VERSION );

		wp_register_style( 'kec-admin', "{$this->assets_path}css/kec-admin.css", array(), WC_KLARNA_PAYMENTS_VERSION );
		wp_register_script( 'kec-admin', "{$this->assets_path}js/kec-admin.js", array( 'jquery' ), WC_KLARNA_PAYMENTS_VERSION, true );

		wp_register_script( 'kec-cart', "{$this->assets_path}js/kec-cart.js", array( 'jquery', 'klarnapayments' ), WC_KLARNA_PAYMENTS_VERSION, true );
		wp_register_script( 'kec-checkout', "{$this->assets_path}js/kec-checkout.js", array( 'jquery', 'klarnapayments' ), WC_KLARNA_PAYMENTS_VERSION, true );
		wp_register_script_module( '@klarna/kec-one-step', "{$this->assets_path}js/kec-one-step.js", array( '@klarna/network_session_token' ), WC_KLARNA_PAYMENTS_VERSION );
	}

	/**
	 * Enqueue the appropriate KEC scripts and styles for the current page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$two_step_available = PluginFeatures::is_available( Features::KEC_TWO_STEP );
		$one_step_available = PluginFeatures::is_available( Features::KEC_ONE_STEP );

		if ( ! $one_step_available && ! $two_step_available ) {
			return;
		}

		$flow = $this->settings->get_kec_flow();

		if ( is_cart() || is_product() ) {
			if ( 'one_step' === $flow && $one_step_available ) {
				$this->enqueue_one_step_assets();
			} else {
				$this->enqueue_cart_assets();
			}
		} elseif ( is_checkout() && $two_step_available ) {
			$this->enqueue_checkout_assets();
		}
	}

	/**
	 * Enqueue KEC admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style( 'kec-admin' );
		wp_enqueue_script( 'kec-admin' );
	}

	/**
	 * Add the type="module" attribute to the one-step script tag.
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 *
	 * @return string
	 */
	public function add_one_step_type( $tag, $handle ) {
		if ( 'kec-one-step' !== $handle ) {
			return $tag;
		}

		return str_replace( '<script', '<script type="module"', $tag );
	}

	/**
	 * Enqueue scripts and styles for the cart and product pages (two-step flow).
	 *
	 * @return void
	 */
	private function enqueue_cart_assets() {
		$is_product_page = is_product();
		$product         = $is_product_page ? wc_get_product( get_the_ID() ) : null;
		$client_id       = $this->settings->get_credentials_secret();

		if ( empty( $client_id ) ) {
			return;
		}

		$cart_params = array(
			'ajax'            => array(
				'get_payload'   => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_get_payload' ),
					'nonce'  => wp_create_nonce( 'kec_get_payload' ),
					'method' => 'POST',
				),
				'auth_callback' => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_auth_callback' ),
					'nonce'  => wp_create_nonce( 'kec_auth_callback' ),
					'method' => 'POST',
				),
				'set_cart'      => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_set_cart' ),
					'nonce'  => wp_create_nonce( 'kec_set_cart' ),
					'method' => 'POST',
				),
			),
			'is_product_page' => $is_product_page,
			'product'         => $is_product_page ? array(
				'id'   => $product->get_id(),
				'type' => $product->get_type(),
			) : null,
			'client_id'       => $client_id,
			'theme'           => $this->settings->get_theme(),
			'shape'           => $this->settings->get_shape(),
			'locale'          => $this->locale,
		);

		wp_localize_script( 'kec-cart', 'kec_cart_params', $cart_params );
		wp_enqueue_style( 'kec-cart' );
		wp_enqueue_script( 'kec-cart' );
	}

	/**
	 * Enqueue scripts and styles for the checkout page (two-step flow).
	 *
	 * @return void
	 */
	private function enqueue_checkout_assets() {
		$client_token = Session::get_client_token();

		if ( empty( $client_token ) ) {
			return;
		}

		$checkout_params = array(
			'ajax'         => array(
				'get_payload'       => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_get_payload' ),
					'nonce'  => wp_create_nonce( 'kec_get_payload' ),
					'method' => 'POST',
				),
				'checkout'          => array(
					'url'    => \WC_AJAX::get_endpoint( 'checkout' ),
					'method' => 'POST',
				),
				'finalize_callback' => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_finalize_callback' ),
					'nonce'  => wp_create_nonce( 'kec_finalize_callback' ),
					'method' => 'POST',
				),
			),
			'client_token' => $client_token,
		);

		wp_localize_script( 'kec-checkout', 'kec_checkout_params', $checkout_params );
		wp_enqueue_script( 'kec-checkout' );
	}

	/**
	 * Enqueue scripts and styles for the one-step checkout flow.
	 *
	 * @return void
	 */
	public function enqueue_one_step_assets() {
		$client_id = $this->settings->get_credentials_secret();

		if ( empty( $client_id ) ) {
			return;
		}

		$amount = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;

		if ( is_product() ) {
			$product = wc_get_product( get_the_ID() );
			$amount  = $product ? wc_get_price_including_tax( $product ) : 0;
		}

		$one_step_params = array(
			'ajax'                => array(
				'get_initiate_body'      => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_one_step_get_initiate_body' ),
					'nonce'  => wp_create_nonce( 'kec_one_step_get_initiate_body' ),
					'method' => 'POST',
				),
				'shipping_change'        => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_one_step_shipping_address_change' ),
					'nonce'  => wp_create_nonce( 'kec_one_step_shipping_address_change' ),
					'method' => 'POST',
				),
				'shipping_option_change' => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_one_step_shipping_option_changed' ),
					'nonce'  => wp_create_nonce( 'kec_one_step_shipping_option_changed' ),
					'method' => 'POST',
				),
				'finalize_order'         => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_one_step_finalize_order' ),
					'nonce'  => wp_create_nonce( 'kec_one_step_finalize_order' ),
					'method' => 'POST',
				),
			),
			'client_id'           => $client_id,
			'testmode'            => $this->settings->is_testmode(),
			'theme'               => $this->settings->get_theme(),
			'shape'               => $this->settings->get_shape(),
			'locale'              => $this->locale,
			'currency'            => get_woocommerce_currency(),
			'amount'              => intval( floatval( $amount ) * 100 ),
			'source'              => is_cart() ? 'cart' : ( is_product() ? get_the_ID() : 'unknown' ),
			'is_variable_product' => is_product() && isset( $product ) && $product && $product->is_type( 'variable' ),
		);

		\KP_Assets::register_module_data( $one_step_params, '@klarna/kec-one-step' );
		wp_enqueue_script_module( '@klarna/kec-one-step' );
		wp_enqueue_style( 'kec-cart' );
	}
}
