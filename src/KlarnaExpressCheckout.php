<?php

namespace Krokedil\Klarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Krokedil\Klarna\KlarnaExpressCheckout\Settings;
use Krokedil\Klarna\KlarnaExpressCheckout\Session;
use Krokedil\Klarna\KlarnaExpressCheckout\Assets;
use Krokedil\Klarna\KlarnaExpressCheckout\AJAX;
use Krokedil\Klarna\KlarnaExpressCheckout\ClientTokenParser;
use Krokedil\Klarna\KlarnaExpressCheckout\WebhookSetup;
use Krokedil\Klarna\KlarnaExpressCheckout\OneStepCheckout;
use Krokedil\Klarna\KlarnaExpressCheckout\Blocks\OneStepBlocksIntegration;
use Krokedil\Klarna\KlarnaExpressCheckout\Api\Controllers\Notifications;

/**
 * Klarna Express Checkout class.
 *
 * The main class responsible for initializing the KEC integration.
 */
class KlarnaExpressCheckout {

	/**
	 * Reference to the Settings class.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Reference to the Session class.
	 *
	 * @var Session
	 */
	private $session;

	/**
	 * Reference to the Assets class.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Reference to the AJAX class.
	 *
	 * @var AJAX
	 */
	private $ajax;

	/**
	 * Reference to the ClientTokenParser class.
	 *
	 * @var ClientTokenParser
	 */
	private $client_token_parser;

	/**
	 * Reference to the WebhookSetup class.
	 *
	 * @var WebhookSetup
	 */
	private $webhook_setup;

	/**
	 * The ID of the payment button element.
	 *
	 * @var string
	 */
	private static $payment_button_id = 'kec-pay-button';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'kp_plugin_features_initialized', array( $this, 'init' ) );
	}

	/**
	 * Initialize the KEC integration.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! PluginFeatures::is_available( Features::KEC ) ) {
			return;
		}

		$this->settings            = new Settings( 'woocommerce_klarna_payments_settings' );
		$this->session             = new Session();
		$this->client_token_parser = new ClientTokenParser( $this->settings );
		$this->assets              = new Assets( $this->settings, kp_get_locale() );
		$this->ajax                = new AJAX( $this->client_token_parser );
		$this->webhook_setup       = new WebhookSetup( $this );

		$this->ajax->set_get_payload( array( $this, 'get_payload' ) );
		$this->ajax->set_finalize_callback( array( $this, 'finalize_callback' ) );

		add_action( 'init', array( $this, 'maybe_unhook_kp_actions' ), 15 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'add_kec_button' ), 31 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'setup_blocks_integration' ) );
		add_filter( 'wc_klarna_payments_supports', array( $this, 'maybe_add_pay_button_support' ) );
		add_filter( 'http_headers_useragent', array( $this, 'add_to_useragent' ) );

		OneStepCheckout::register_hooks( $this->settings->get_kec_flow() );

		// Register the API controller for handling notifications.
		Notifications::register_controller();
	}

	/**
	 * Get the ID of the payment button element.
	 *
	 * @return string
	 */
	public static function get_payment_button_id() {
		return self::$payment_button_id;
	}

	/**
	 * Maybe unhook Klarna Payments actions when a KEC client token is present.
	 *
	 * @return void
	 */
	public function maybe_unhook_kp_actions() {
		$client_token = Session::get_client_token();
		if ( empty( $client_token ) ) {
			return;
		}

		if ( ! function_exists( 'KP_WC' ) ) {
			return;
		}

		$kp_session  = KP_WC()->session;
		$kp_checkout = KP_WC()->checkout;
		$kp_gateway  = WC()->payment_gateways()->get_available_payment_gateways()['klarna_payments'] ?? null;

		if ( empty( $kp_session ) || empty( $kp_checkout ) || empty( $kp_gateway ) ) {
			return;
		}

		remove_action( 'woocommerce_after_calculate_totals', array( $kp_session, 'get_session' ), 999999 );
		remove_filter( 'woocommerce_update_order_review_fragments', array( $kp_checkout, 'add_token_fragment' ) );
		remove_action( 'woocommerce_review_order_before_submit', array( $kp_checkout, 'html_client_token' ) );
		remove_action( 'woocommerce_pay_order_before_submit', array( $kp_checkout, 'html_client_token' ) );
		remove_action( 'wc_get_template', array( $kp_gateway, 'override_kp_payment_option' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_kp_scripts' ), 15 );
	}

	/**
	 * Add the KEC payment button to the product page.
	 *
	 * @return void
	 */
	public function add_kec_button() {
		global $product;

		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		if ( 'cart' === $this->settings->get_placements() ) {
			return;
		}

		if ( did_action( 'woocommerce_single_product_summary' ) > 1 ) {
			return;
		}

		if ( ! $product->is_in_stock() ) {
			return;
		}

		?>
		<div id="kec-pay-button"></div>
		<?php
	}

	/**
	 * Dequeue the Klarna Payments scripts when KEC is handling the checkout.
	 *
	 * @return void
	 */
	public function dequeue_kp_scripts() {
		wp_dequeue_script( 'klarna_payments' );
	}

	/**
	 * Set up the WooCommerce Blocks integration for one-step checkout.
	 *
	 * @return void
	 */
	public function setup_blocks_integration() {
		$flow = $this->settings->get_kec_flow();
		if ( 'one_step' !== $flow || ! PluginFeatures::is_available( Features::KEC_ONE_STEP ) ) {
			return;
		}

		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) || ! version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), '4.4.0', '>' ) ) {
			return;
		}

		/**
		 * Filter the compatible blocks for Klarna Express Checkout.
		 */
		$compatible_blocks = apply_filters( 'kec_compatible_blocks', array( 'cart', 'mini-cart' ) );
		foreach ( $compatible_blocks as $block_name ) {
			add_action(
				"woocommerce_blocks_{$block_name}_block_registration",
				function ( $integration_registry ) {
					$integration_registry->register( new OneStepBlocksIntegration() );
				}
			);
		}
	}

	/**
	 * Maybe add pay button support to the gateway based on KEC settings.
	 *
	 * @param array $supports The gateway supports array.
	 *
	 * @return array
	 */
	public function maybe_add_pay_button_support( $supports ) {
		if ( is_cart() && WC()->cart && ! WC()->cart->needs_payment() ) {
			return $supports;
		}

		$placement = $this->settings->get_placements();
		if ( $this->settings->is_enabled() && 'product' !== $placement ) {
			$supports[] = 'pay_button';
		}

		return $supports;
	}

	/**
	 * Append KEC version to the HTTP user agent string.
	 *
	 * @param string $user_agent The current user agent string.
	 *
	 * @return string
	 */
	public function add_to_useragent( $user_agent ) {
		if ( strpos( $user_agent, 'KP' ) !== false ) {
			$user_agent .= ' KEC/' . WC_KLARNA_PAYMENTS_VERSION;
		}

		return $user_agent;
	}

	/**
	 * Get the Session class.
	 *
	 * @return Session
	 */
	public function session() {
		return $this->session;
	}

	/**
	 * Get the Assets class.
	 *
	 * @return Assets
	 */
	public function assets() {
		return $this->assets;
	}

	/**
	 * Get the AJAX class.
	 *
	 * @return AJAX
	 */
	public function ajax() {
		return $this->ajax;
	}

	/**
	 * Get the ClientTokenParser class.
	 *
	 * @return ClientTokenParser
	 */
	public function client_token_parser() {
		return $this->client_token_parser;
	}

	/**
	 * Get the Settings class.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get the WebhookSetup class.
	 *
	 * @return WebhookSetup
	 */
	public function webhook_setup() {
		return $this->webhook_setup;
	}

	/**
	 * Get the KP_Order_Data helper for the current cart.
	 *
	 * @return \KP_Order_Data
	 */
	public function get_order_data_helper() {
		$settings          = get_option( 'woocommerce_klarna_payments_settings', array() );
		$customer_type     = klarna_get_customer_type( $settings['customer_type'] ?? 'b2c' );
		$order_data_helper = new \KP_Order_Data( $customer_type );

		return $order_data_helper;
	}

	/**
	 * Get the payload for the Klarna Express Checkout two-step flow.
	 *
	 * @return array
	 */
	public function get_payload() {
		$order_data_helper = $this->get_order_data_helper();

		return array(
			'purchase_country'  => kp_get_klarna_country(),
			'purchase_currency' => get_woocommerce_currency(),
			'locale'            => kp_get_locale(),
			'order_amount'      => $order_data_helper->order_data->get_total(),
			'order_tax_amount'  => $order_data_helper->order_data->get_total_tax(),
			'order_lines'       => $order_data_helper->get_klarna_order_lines_array(),
		);
	}

	/**
	 * Finalize the order after KEC two-step authorization.
	 *
	 * @param string $auth_token The authorization token from Klarna.
	 * @param string $order_id   The WooCommerce order ID.
	 * @param string $order_key  The WooCommerce order key.
	 *
	 * @throws \Exception If the order cannot be found, verified, or placed.
	 *
	 * @return array
	 */
	public function finalize_callback( $auth_token, $order_id, $order_key ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			/* translators: [customer-facing]. */
			throw new \Exception( __( 'The order could not be found.', 'klarna-payments-for-woocommerce' ) ); // phpcs:ignore
		}

		if ( $order->get_order_key() !== $order_key ) {
			/* translators: [customer-facing]. */
			throw new \Exception( __( 'Order verification failed.', 'klarna-payments-for-woocommerce' ) ); // phpcs:ignore
		}

		$place_order_response = KP_WC()->api->place_order( kp_get_klarna_country(), $auth_token, $order_id );

		if ( is_wp_error( $place_order_response ) ) {
			throw new \Exception( $place_order_response->get_error_message() ); // phpcs:ignore
		}

		$fraud_status = $place_order_response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				kp_process_accepted( $order, $place_order_response );
				kp_unset_session_values();
				return array(
					'result'   => 'success',
					'redirect' => $place_order_response['redirect_url'],
				);
			case 'PENDING':
				kp_process_pending( $order, $place_order_response );
				kp_unset_session_values();
				return array(
					'result'   => 'success',
					'redirect' => $place_order_response['redirect_url'],
				);
			case 'REJECTED':
				kp_process_rejected( $order, $place_order_response );
				kp_unset_session_values();
				return array(
					'result'   => 'error',
					'redirect' => $order->get_cancel_order_url_raw(),
				);
			default:
				kp_unset_session_values();
				return array(
					'result'   => 'error',
					'redirect' => $order->get_cancel_order_url_raw(),
				);
		}
	}
}
