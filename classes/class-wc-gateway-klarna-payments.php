<?php
/**
 * Klarna Payment Gateway class file.
 *
 * @package WC_Klarna_Payments/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		$this->method_title       = __( 'Klarna Payments', 'klarna-payments-for-woocommerce' );
		$this->method_description = __( 'Get the flexibility to pay over time with Klarna!', 'klarna-payments-for-woocommerce' );
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
			)
		); // Make this filterable.

		$base_location      = wc_get_base_location();
		$this->shop_country = $base_location['country'];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title         = $this->get_option( 'title' );
		$this->enabled       = $this->get_option( 'enabled' );
		$this->customer_type = $this->get_option( 'customer_type', 'b2c' );
		$this->testmode      = 'yes' === $this->get_option( 'testmode' );

		// What is Klarna link.
		$this->hide_what_is_klarna  = 'yes' === $this->get_option( 'hide_what_is_klarna' );
		$this->float_what_is_klarna = 'yes' === $this->get_option( 'float_what_is_klarna' );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_klarna_payments', array( $this, 'notification_listener' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
		add_filter( 'wc_get_template', array( $this, 'override_kp_payment_option' ), 10, 3 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
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
					$link_style = 'style="float: right; margin-right:10px; font-size: .83em;"';
				} else {
					$link_style = '';
				}

				$what_is_klarna_text = __( 'What is Klarna?', 'klarna-payments-for-woocommerce' );
				$link_url            = 'https://www.klarna.com';

				// Change text for Germany.
				$locale = get_locale();
				if ( stripos( $locale, 'de' ) !== false ) {
					$what_is_klarna_text = 'Was ist Klarna?';
				}
				$icon_html .= '<a ' . $link_style . ' href="' . $link_url . '" onclick="window.open(\'' . $link_url . '\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . $what_is_klarna_text . '</a>';
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
		ob_start();
		parent::admin_options();
		$parent_options = ob_get_contents();
		ob_end_clean();
		KP_Settings_Saved::maybe_show_errors();
		KP_Banners::settings_sidebar( $parent_options );
	}

	/**
	 * Check country and currency
	 *
	 * Fired before create session and update session, and inside is_available.
	 *
	 * @param WC_Order|bool $order The WooCommerce order.
	 */
	public function country_currency_check( $order = false ) {
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

		// Check that the credentials are set for the current country in KP.
		$prefix        = $this->testmode ? 'test_' : '';
		$merchant_id   = $this->get_option( "{$prefix}merchant_id_{$country}" );
		$shared_secret = $this->get_option( "{$prefix}shared_secret_{$country}" );
		if ( empty( $merchant_id ) || empty( $shared_secret ) ) {
			kp_unset_session_values();
			return new WP_Error( 'country', "No credentials found for {$country}" );
		}

		// Check the countrys currency against the current curreny.
		$country_values    = KP_Form_Fields::$kp_form_auto_countries[ $country ];
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

		if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order_id ) ) {
			return $this->process_subscription( $order );
		}
		// Check if the order was created using WooCommerce blocks.
		if ( kp_is_wc_blocks_order( $order ) ) {
			return $this->process_blocks_order( $order );
		}

		return $this->process_checkout_order( $order );
	}

	/**
	 * Create a session for Klarna Hosted Payment Page, and redirect the customer there.
	 *
	 * @param mixed $order The WooCommerce order.
	 * @return array
	 */
	private function process_subscription( $order ) {
		return $this->process_blocks_order( $order );
	}

	/**
	 * Process WooCommerce checkout order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array
	 */
	private function process_checkout_order( $order ) {
		// Load any session data that we might have. Pass null instead of order identifier to load session from WC()->session.
		KP_WC()->session->set_session_data( null );

		$order_id          = $order->get_id();
		$klarna_country    = KP_WC()->session->get_klarna_session_country( $order );
		$klarna_session_id = KP_WC()->session->get_klarna_session_id();

		if ( empty( $klarna_country ) || empty( $klarna_session_id ) ) {
			return array(
				'result'   => 'error',
				'messages' => array(
					__( 'Failed to get required data from the Klarna session. Please try again.', 'klarna-payments-for-woocommerce' ),
				),
			);
		}

		// Set the order meta data.
		$environment = $this->testmode ? 'test' : 'live';
		$order->add_meta_data( '_wc_klarna_environment', $environment, true );
		$order->add_meta_data( '_wc_klarna_country', $klarna_country, true );
		$order->add_meta_data( '_kp_session_id', $klarna_session_id, true );

		// Save the order.
		$order->save();

		$order_data = new KP_Order_Data( $this->customer_type );
		$customer   = $order_data->get_klarna_customer_object();

		// Return success without redirect URL since our script handles the return instead of WooCommerce.
		return array(
			'result'    => 'success',
			'order_id'  => $order_id,
			'addresses' => array(
				'billing'  => $customer['billing'],
				'shipping' => $customer['shipping'],
			),
		);
	}

	/**
	 * Process WooCommerce blocks order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array
	 */
	private function process_blocks_order( $order ) {
		// Create a session for the order.
		$session = KP_WC()->session->get_session( $order );

		// Check for any errors.
		if ( is_wp_error( $session ) ) {
			return array(
				'result'   => 'error',
				'redirect' => '#',
				'message'  => __( 'Failed to create a session with Klarna. Please try again.', 'klarna-payments-for-woocommerce' ),
			);
		}

		$session_id     = KP_WC()->session->get_klarna_session_id();
		$klarna_country = kp_get_klarna_country( $order );

		// Create a HPP url.
		$hpp = KP_WC()->api->create_hpp( $klarna_country, $session_id, $order->get_id() );

		// Set the order meta data.
		$environment = $this->testmode ? 'test' : 'live';
		$order->add_meta_data( '_wc_klarna_environment', $environment, true );
		$order->add_meta_data( '_wc_klarna_country', $klarna_country, true );
		$order->add_meta_data( '_kp_session_id', $session_id, true );

		// Save the order.
		$order->save();

		if ( is_wp_error( $hpp ) ) {
			return array(
				'result'   => 'error',
				'redirect' => '#',
				'message'  => __( 'Failed to create a hosted payment page with Klarna. Please try again.', 'klarna-payments-for-woocommerce' ),
			);
		}

		return array(
			'result'   => 'success',
			'redirect' => $hpp['redirect_url'],
		);
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
}

/**
 * Adds the Klarna Payments Gateway to WooCommerce
 *
 * @param  array $methods All registered payment methods.
 * @return array $methods All registered payment methods.
 */
function add_kp_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Klarna_Payments';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_kp_gateway' );
