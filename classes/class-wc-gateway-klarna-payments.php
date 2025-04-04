<?php
/**
 * Klarna Payment Gateway class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KrokedilKlarnaPaymentsDeps\Krokedil\SettingsPage\SettingsPage;

/**
 * WC_Payment_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Klarna_Payments extends WC_Payment_Gateway {
	/**
	 * Allowed currencies
	 *
	 * @var array
	 */
	public $allowed_currencies = array( 'USD', 'GBP', 'SEK', 'NOK', 'EUR', 'DKK', 'CHF', 'CAD', 'AUD', 'NZD', 'MXN', 'PLN', 'CZK', 'RON', 'HUF' );

	/**
	 * Shop country. Country base location from WooCommerce.
	 *
	 * @var string
	 */
	public $shop_country;

	/**
	 * Customer type (b2b or b2c) based on settings.
	 *
	 * @var string
	 */
	public $customer_type;

	/**
	 * Bool if we should hide what is klarna or not.
	 *
	 * @var bool
	 */
	public $hide_what_is_klarna;

	/**
	 * Bool if we should float what is klarna or not.
	 *
	 * @var bool
	 */
	public $float_what_is_klarna;

	/**
	 * Bool if we should use test mode or not.
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'klarna_payments';
		$this->method_title       = __( 'Klarna for WooCommerce', 'klarna-payments-for-woocommerce' );
		$this->method_description = __( 'Supercharge your business with one single plugin for increased sales and enhanced shopping experiences.', 'klarna-payments-for-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = apply_filters(
			'wc_klarna_payments_supports',
			array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
				'upsell',
			)
		); // Make this filterable.

		$base_location      = wc_get_base_location();
		$this->shop_country = $base_location['country'];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title         = 'Klarna';
		$this->enabled       = $this->get_option( 'enabled' );
		$this->customer_type = $this->get_option( 'customer_type', 'b2c' );
		$this->testmode      = 'yes' === $this->get_option( 'testmode' );

		// What is Klarna link.
		$this->hide_what_is_klarna  = 'yes' === $this->get_option( 'hide_what_is_klarna' );
		$this->float_what_is_klarna = 'yes' === $this->get_option( 'float_what_is_klarna' );

		$this->pay_button_id = KrokedilKlarnaPaymentsDeps\Krokedil\KlarnaExpressCheckout\KlarnaExpressCheckout::get_payment_button_id();

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_klarna_payments', array( $this, 'notification_listener' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
		add_filter( 'wc_get_template', array( $this, 'override_kp_payment_option' ), 10, 3 );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		// Migrate any legacy settings we have.
		$this->form_fields = KP_Form_Fields::get_form_fields();
	}

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		if ( ! empty( $this->icon ) ) {
			$icon_width = '39';
			$icon_html  = '<img src="' . $this->icon . '" alt="Klarna" style="max-width:' . $icon_width . 'px"/>';
			if ( ! $this->hide_what_is_klarna ) {
				// If default WooCommerce CSS is used, float "What is Klarna link like PayPal does it".
				if ( $this->float_what_is_klarna ) {
					$link_css = 'style="float: right; margin-right:10px; font-size: .83em;"';
				} else {
					$link_css = '';
				}

				$what_is_klarna_text = __( 'What is Klarna?', 'klarna-payments-for-woocommerce' );
				$link_url            = 'https://www.klarna.com';

				// Change text for Germany.
				$locale = get_locale();
				if ( stripos( $locale, 'de' ) !== false ) {
					$what_is_klarna_text = 'Was ist Klarna?';
				}
				$icon_html .= '<a ' . $link_css . ' href="' . $link_url . '" onclick="window.open(\'' . $link_url . '\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . $what_is_klarna_text . '</a>';
			}
		} else {
			$icon_html = '<img src="' . WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/klarna-logo.svg" alt="Klarna" style="max-width:39px;"/>';
		}
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Add sidebar to the settings page.
	 */
	public function admin_options() {
		$args = $this->get_settings_page_args();

		if ( empty( $args ) ) {
			$this->settings_page_content();
			return;
		}

		$args['general_content'] = array( $this, 'settings_page_content' );
		$args['icon']            = WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/klarna-icon.svg';
		( SettingsPage::get_instance() )
			->set_plugin_name( 'Klarna' )
			->register_page( 'klarna_payments', $args, $this )
			->output( 'klarna_payments' );
	}

	/**
	 * Read the settings page arguments from remote or local storage.
	 * If the args are stored locally, they are fetched from the transient cache.
	 * If they are not available locally, they are fetched from the remote source and stored in the transient cache.
	 * If the remote source is not available, the function returns null, and default settings page will be used instead.
	 *
	 * @return array|null
	 */
	private function get_settings_page_args() {
		$args = get_transient( 'klarna_payments_settings_page_config' );
		if ( ! $args ) {
			$args = wp_remote_get( 'https://krokedil-settings-page-configs.s3.eu-north-1.amazonaws.com/main/configs/klarna-payments-for-woocommerce.json' );

			if ( is_wp_error( $args ) ) {
				KP_Logger::log( 'Failed to fetch Klarna Payments settings page config from remote source.' );
				return null;
			}

			$args = wp_remote_retrieve_body( $args );
			set_transient( 'klarna_payments_settings_page_config', $args, 60 * 60 * 24 ); // 24 hours lifetime.
		}

		return json_decode( $args, true );
	}

	/**
	 * Callable function for the general content for the settings page.
	 *
	 * @return void
	 */
	public function settings_page_content() {
		KP_Settings_Saved::maybe_show_errors();
		KP_Settings_Page::header_html();
		echo $this->generate_settings_html( $this->get_form_fields(), false ); // phpcs:ignore
	}

	/**
	 * Check country and currency
	 *
	 * Fired before create session and update session, and inside is_available.
	 *
	 * @param WC_Order|bool $order The WooCommerce order.
	 */
	public function country_currency_check( $order = false ) {
		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );
		// Check if allowed currency.
		if ( ! in_array( get_woocommerce_currency(), $this->allowed_currencies, true ) ) {
			kp_unset_session_values();
			return new WP_Error( 'currency', 'Currency not allowed for Klarna Payments' );
		}

		$klarna_country = kp_get_klarna_country( $order );
		$country        = strtolower( $klarna_country );

		if ( ! isset( KP_Form_Fields::$kp_form_auto_countries[ $country ] ) ) {
			kp_unset_session_values();
			return new WP_Error( 'country', "Country ({$country}) is not supported by Klarna Payments." );
		}

		$country_values = KP_Form_Fields::$kp_form_auto_countries[ $country ];
		$combined_eu    = 'yes' === ( isset( $settings['combine_eu_credentials'] ) ? $settings['combine_eu_credentials'] : 'no' );

		// If the country is a EU country, check if we should get the credentials from the EU settings.
		if ( $combined_eu && key_exists( $country, KP_Form_Fields::available_countries( 'eu' ) ) ) {
			$country = 'eu';
		}

		// Check that the credentials are set for the current country in KP.
		$prefix        = $this->testmode ? 'test_' : '';
		$merchant_id   = $this->get_option( "{$prefix}merchant_id_{$country}" );
		$shared_secret = $this->get_option( "{$prefix}shared_secret_{$country}" );

		if ( empty( $merchant_id ) || empty( $shared_secret ) ) {
			kp_unset_session_values();
			return new WP_Error( 'country', "No credentials found for {$country}" );
		}

		// Check the countrys currency against the current currency.
		$required_currency = $country_values['currency'];
		$country_name      = $country_values['name'];
		if ( get_woocommerce_currency() !== $required_currency ) {
			kp_unset_session_values();
			return new WP_Error( 'currency', "{$required_currency} must be used for {$country_name} purchases" );
		}

		return true;
	}

	/**
	 * Check if Klarna Payments should be available
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
		if ( in_array( 'general', $kp_unavailable_feature_ids ) ) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}

		$order = false;

		if ( kp_is_order_pay_page() ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );
		}

		// Check country and currency.
		if ( is_wp_error( $this->country_currency_check( $order ) ) ) {
			return false;
		}

		// Check the country against the available countries in the settings.
		if ( ! kp_is_country_available( kp_get_klarna_country( $order ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Override checkout form template if Klarna Checkout is the selected payment method.
	 *
	 * @param string $located Target template file location.
	 * @param string $template_name The name of the template.
	 * @param array  $args Arguments for the template.
	 * @return string
	 */
	public function override_kp_payment_option( $located, $template_name, $args ) {
		if ( is_checkout() ) {
			if ( 'checkout/payment-method.php' === $template_name ) {
				if ( 'klarna_payments' === $args['gateway']->id ) {
					$located = untrailingslashit( plugin_dir_path( __DIR__ ) ) . '/templates/klarna-payments-categories.php';
				}
			}

			// When changing subscription payment method, hide the payment fields as we'll redirect to Klarna's HPP, not one of the payment categories.
			if ( KP_Subscription::is_change_payment_method() ) {
				$this->has_fields = false;
			}
		}

		return $located;
	}

	/**
	 * Adds Klarna Payments container to checkout page.
	 */
	public function payment_fields() {
		echo '<div id="' . esc_html( $this->id ) . '_container" class="klarna_payments_container" data-payment_method_category="' . esc_html( $this->id ) . '"></div>';
	}

	/**
	 * Place Klarna Payments order, after authorization.
	 *
	 * Uses authorization token to place the order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array   $result  Payment result.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			$payment_processor = KP_Payment_Processor::get_processor( $order );
			return $payment_processor->process_payment();
		}
		catch (WP_Exception $e) {
			return array(
				'result'   => 'error',
				'messages' => array(
					$e->getMessage(),
				),
			);
		}
		catch (Exception $e) {
			return array(
				'result'   => 'error',
				'messages' => array(
					__( 'Failed to process the order. Please try again.', 'klarna-payments-for-woocommerce' ),
				),
			);
		}
	}

	/**
	 * Notification listener for Pending orders. This plugin doesn't handle pending orders, but it does allow Klarna
	 * Order Management plugin to hook in and process pending orders.
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/pending-orders
	 *
	 * @hook woocommerce_api_wc_gateway_klarna_payments
	 */
	public function notification_listener() {
		do_action( 'wc_klarna_notification_listener' );
	}

	/**
	 * This plugin doesn't handle order management, but it allows Klarna Order Management plugin to process refunds
	 * and then return true or false.
	 *
	 * @param int      $order_id WooCommerce order ID.
	 * @param null|int $amount Refund amount.
	 * @param string   $reason Reason for refund.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'wc_klarna_payments_process_refund', false, $order_id, $amount, $reason );
	}


	/**
	 * Adds can't edit address notice to KP EU orders.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 */
	public function address_notice( $order ) {
		if ( $this->id === $order->get_payment_method() ) {
			echo '<div style="margin: 10px 0; padding: 10px; border: 1px solid #B33A3A; font-size: 12px">Order address should not be changed and any changes you make will not be reflected in Klarna system.</div>';
		}
	}

	/**
	 * Set payment method title for order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param array    $klarna_place_order_response The Klarna place order response.
	 * @return void
	 * @todo Change it so that it dynamically gets information from Klarna.
	 */
	public function set_payment_method_title( $order, $klarna_place_order_response ) {
		$title         = $order->get_payment_method_title();
		$klarna_method = $klarna_place_order_response['authorized_payment_method']['type'];
		switch ( $klarna_method ) {
			case 'invoice':
				$klarna_method = 'Pay Later';
				break;
			case 'base_account':
				$klarna_method = 'Slice It';
				break;
			case 'direct_debit':
				$klarna_method = 'Direct Debit';
				break;
			default:
				$klarna_method = null;
		}
		if ( null !== $klarna_method ) {
			$new_title = $title . ' - ' . $klarna_method;
			$order->set_payment_method_title( $new_title );
		}
	}

	/**
	 * Check if upsell should be available for the Klarna order or not.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return bool
	 */
	public function upsell_available( $order_id ) {
		$order           = wc_get_order( $order_id );
		$country         = $order->get_meta( '_wc_klarna_country', true );
		$klarna_order_id = $order->get_meta( '_wc_klarna_order_id', true );

		if ( empty( $klarna_order_id ) ) {
			return false;
		}

		$klarna_order = KP_WC()->api->get_klarna_om_order( $country, $klarna_order_id );

		if ( is_wp_error( $klarna_order ) ) {
			return false;
		}

		// If the needed keys are not set, return false.
		if ( ! isset( $klarna_order['initial_payment_method'] ) || ! isset( $klarna_order['initial_payment_method']['type'] ) ) {
			return false;
		}

		// Set allowed payment methods for upsell based on country. https://developers.klarna.com/documentation/order-management/integration-guide/pre-delivery/#update-order-amount.
		$allowed_payment_methods = array( 'INVOICE', 'B2B_INVOICE', 'BASE_ACCOUNT', 'DIRECT_DEBIT' );
		switch ( $klarna_order['billing_address']['country'] ) {
			case 'AT':
			case 'DE':
			case 'DK':
			case 'FI':
			case 'FR':
			case 'NL':
			case 'NO':
			case 'SE':
				$allowed_payment_methods[] = 'FIXED_AMOUNT';
				break;
			case 'CH':
				$allowed_payment_methods = array();
				break;
		}

		return in_array( $klarna_order['initial_payment_method']['type'], $allowed_payment_methods, true );
	}

	/**
	 * Make an upsell request to Klarna.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 *
	 * @return bool|WP_Error
	 */
	public function upsell( $order_id, $upsell_uuid ) {
		$order           = wc_get_order( $order_id );
		$country         = $order->get_meta( '_wc_klarna_country', true );
		$klarna_order_id = $order->get_meta( '_wc_klarna_order_id', true );

		$klarna_upsell_order = KP_WC()->api->upsell_klarna_order( $country, $klarna_order_id, $order_id );

		if ( is_wp_error( $klarna_upsell_order ) ) {
			$error = new WP_Error( '401', __( 'Klarna did not accept the new order amount, the order has not been updated', 'klarna-payments-for-woocommerce' ) );
			return $error;
		}

		return true;
	}
}

/**
 * Adds the Klarna Payments Gateway to WooCommerce
 *
 * @param  array $methods All registered payment methods.
 * @return array $methods All registered payment methods.
 */
function add_kp_gateway( $methods ) { // phpcs:ignore
	$methods[] = 'WC_Gateway_Klarna_Payments';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_kp_gateway' );
