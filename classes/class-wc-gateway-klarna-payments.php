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
	public $allowed_currencies = array( 'USD', 'GBP', 'SEK', 'NOK', 'EUR', 'DKK', 'CHF', 'CAD', 'AUD', 'NZD' );

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'klarna_payments';
		$this->method_title       = __( 'Klarna Payments', 'klarna-payments-for-woocommerce' );
		$this->method_description = __( 'Get the flexibility to pay over time with Klarna!', 'klarna-payments-for-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = apply_filters( 'wc_klarna_payments_supports', array( 'products' ) ); // Make this filterable.

		$base_location      = wc_get_base_location();
		$this->shop_country = $base_location['country'];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title         = $this->get_option( 'title' );
		$this->enabled       = $this->get_option( 'enabled' );
		$this->customer_type = $this->get_option( 'customer_type' );

		// What is Klarna link.
		$this->hide_what_is_klarna  = 'yes' === $this->get_option( 'hide_what_is_klarna' );
		$this->float_what_is_klarna = 'yes' === $this->get_option( 'float_what_is_klarna' );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_api_wc_gateway_klarna_payments', array( $this, 'notification_listener' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
		add_filter( 'wc_get_template', array( $this, 'override_kp_payment_option' ), 10, 3 );
		add_action( 'klarna_payments_template', 'kp_maybe_create_session' );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = include WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/kp-form-fields.php';
	}

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon_width = '39';
		$icon_html  = '<img src="' . $this->icon . '" alt="Klarna" style="max-width:' . $icon_width . 'px"/>';
		if ( ! $this->hide_what_is_klarna ) {
			// If default WooCommerce CSS is used, float "What is Klarna link like PayPal does it".
			if ( $this->float_what_is_klarna ) {
				$link_style = 'style="float: right; line-height: 52px; font-size: .83em;"';
			} else {
				$link_style = '';
			}

			$what_is_klarna_text = 'What is Klarna?';
			$link_url            = 'https://www.klarna.com';

			// Change text for Germany.
			$locale = get_locale();
			if ( stripos( $locale, 'de' ) !== false ) {
				$what_is_klarna_text = 'Was ist Klarna?';
			}
			$icon_html .= '<a ' . $link_style . ' href="' . $link_url . '" onclick="window.open(\'' . $link_url . '\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . $what_is_klarna_text . '</a>';
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
	 */
	public function country_currency_check() {
		// Check if allowed currency.
		if ( ! in_array( get_woocommerce_currency(), $this->allowed_currencies, true ) ) {
			kp_unset_session_values();

			return new WP_Error( 'currency', 'Currency not allowed for Klarna Payments' );
		}

		// If US, check if USD used.
		if ( 'USD' === get_woocommerce_currency() ) {
			if ( 'US' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'USD must be used for US purchases' );
			}
		}

		// If GB, check if GBP used.
		if ( 'GBP' === get_woocommerce_currency() ) {
			if ( 'GB' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'GBP must be used for GB purchases' );
			}
		}

		// If SE, check if SEK used.
		if ( 'SEK' === get_woocommerce_currency() ) {
			if ( 'SE' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'SEK must be used for SE purchases' );
			}
		}

		// If NO, check if NOK used.
		if ( 'NOK' === get_woocommerce_currency() ) {
			if ( 'NO' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'NOK must be used for NO purchases' );
			}
		}

		// If DK, check if DKK used.
		if ( 'DKK' === get_woocommerce_currency() ) {
			if ( 'DK' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'DKK must be used for DK purchases' );
			}
		}

		// If CH, check if CHF used.
		if ( 'CHF' === get_woocommerce_currency() ) {
			if ( 'CH' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'CHF must be used for CH purchases' );
			}
		}

		// If EUR country, check if EUR used.
		if ( 'EUR' === get_woocommerce_currency() ) {
			if ( ! in_array( kp_get_klarna_country(), array( 'AT', 'DE', 'NL', 'FI', 'ES', 'IT', 'BE', 'FR' ), true ) ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'EUR must be used for AT, DE, NL, FI, ES, IT, BE, FR purchases' );
			}
		}

		// If CAD country, check if CAD used.
		if ( 'CAD' === get_woocommerce_currency() ) {
			if ( 'CA' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'CAD must be used for CA purchases' );
			}
		}

		// If AUD country, check if AUD used.
		if ( 'AUD' === get_woocommerce_currency() ) {
			if ( 'AU' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'AUD must be used for AU purchases' );
			}
		}

		// If AUD country, check if AUD used.
		if ( 'NZD' === get_woocommerce_currency() ) {
			if ( 'NZ' !== kp_get_klarna_country() ) {
				kp_unset_session_values();

				return new WP_Error( 'currency', 'NZD must be used for NZ purchases' );
			}
		}

		return true;
	}

	/**
	 * Check if Klarna Payments should be available
	 */
	public function is_available() {
		if ( ! is_checkout() ) {
			return false;
		}
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}

		// Check country and currency.
		if ( is_wp_error( $this->country_currency_check() ) ) {
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
		}

		return $located;
	}

	/**
	 * Create Klarna Payments session request.
	 *
	 * @param string $request_url  Klarna request URL.
	 * @param array  $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function create_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_create_session_args', $request_args );

		$response      = wp_safe_remote_post( $request_url, $request_args );
		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$session_id    = isset( $response_body['session_id'] ) ? $response_body['session_id'] : null;

		// Log the request.
		$log = WC_Klarna_Payments::format_log( $session_id, 'POST', 'Klarna Payments create session request.', $request_args, $response_body, $code );
		WC_Klarna_Payments::log( $log );

		if ( is_array( $response ) ) {
			if ( 200 === $code ) {
				$decoded = json_decode( $response['body'] );

				return $decoded;
			} else {
				return new WP_Error( $code, $response['body'] );
			}
		} else {
			return new WP_Error( 'kp_create_session', 'Could not create Klarna Payments session.' );
		}
	}

	/**
	 * Update Klarna Payments session.
	 *
	 * @param string $request_url  Klarna request URL.
	 * @param array  $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function update_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_update_session_args', $request_args );

		$response      = wp_safe_remote_post( $request_url, $request_args );
		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		WC_Klarna_Payments::log( 'Klarna Payments update session request. Status Code: ' . $code . ' Response: ' . stripslashes_deep( wp_json_encode( $response_body ) ) );

		if ( is_array( $response ) ) {
			if ( 204 === $code ) {
				return true;
			} else {
				return new WP_Error( $code, $response['body'] );
			}
		} else {
			return new WP_Error( 'kp_update_session', 'Could not update Klarna Payments session.' );
		}
	}

	/**
	 * Adds Klarna Payments container to checkout page.
	 */
	public function payment_fields() {
		echo '<div id="' . esc_html( $this->id ) . '_container" class="klarna_payments_container" data-payment_method_category="' . esc_html( $this->id ) . '"></div>';
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || is_order_received_page() || is_cart() ) {
			return;
		}

		// Maybe create KP Session.
		if ( $this->is_available() ) {
			kp_maybe_create_session();
		}

		wp_register_script(
			'klarna_payments',
			plugins_url( 'assets/js/klarna-payments.js', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array( 'jquery' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);

		$default_kp_checkout_fields = array(
			'billing_given_name'       => '#billing_first_name',
			'billing_family_name'      => '#billing_last_name',
			'billing_email'            => '#billing_email',
			'billing_phone'            => '#billing_phone',
			'billing_country'          => '#billing_country',
			'billing_region'           => '#billing_state',
			'billing_postal_code'      => '#billing_postcode',
			'billing_city'             => '#billing_city',
			'billing_street_address'   => '#billing_address_1',
			'billing_street_address2'  => '#billing_address_2',
			'billing_company'          => '#billing_company',
			'shipping_given_name'      => '#shipping_first_name',
			'shipping_family_name'     => '#shipping_last_name',
			'shipping_country'         => '#shipping_country',
			'shipping_region'          => '#shipping_state',
			'shipping_postal_code'     => '#shipping_postcode',
			'shipping_city'            => '#shipping_city',
			'shipping_street_address'  => '#shipping_address_1',
			'shipping_street_address2' => '#shipping_address_2',
		);

		// Localize the script.
		$klarna_payments_params                           = array();
		$klarna_payments_params['testmode']               = $this->get_option( 'testmode' );
		$klarna_payments_params['customer_type']          = $this->get_option( 'customer_type' );
		$klarna_payments_params['remove_postcode_spaces'] = ( apply_filters( 'wc_kp_remove_postcode_spaces', false ) ) ? 'yes' : 'no';
		$klarna_payments_params['ajaxurl']                = admin_url( 'admin-ajax.php' );
		$klarna_payments_params['place_order_url']        = WC_AJAX::get_endpoint( 'kp_wc_place_order' );
		$klarna_payments_params['place_order_nonce']      = wp_create_nonce( 'kp_wc_place_order' );
		$klarna_payments_params['auth_failed_url']        = WC_AJAX::get_endpoint( 'kp_wc_auth_failed' );
		$klarna_payments_params['auth_failed_nonce']      = wp_create_nonce( 'kp_wc_auth_failed' );
		$klarna_payments_params['update_session_url']     = WC_AJAX::get_endpoint( 'kp_wc_update_session' );
		$klarna_payments_params['update_session_nonce']   = wp_create_nonce( 'kp_wc_update_session' );
		$klarna_payments_params['client_token']           = WC()->session->get( 'klarna_payments_client_token' );

		wp_localize_script( 'klarna_payments', 'klarna_payments_params', $klarna_payments_params );
		wp_enqueue_script( 'klarna_payments' );

		wp_register_script( 'klarnapayments', 'https://x.klarnacdn.net/kp/lib/v1/api.js', null, null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		wp_enqueue_script( 'klarnapayments' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Admin page hook.
	 *
	 * @hook admin_enqueue_scripts
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );
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
	 * Place Klarna Payments order, after authorization.
	 *
	 * Uses authorization token to place the order.
	 *
	 * @TODO: Set customer payment method as KP.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_payment( $order_id ) {
		$response = array(
			'order_id'  => $order_id,
			'addresses' => array(
				'billing'  => KP_Customer_Data::get_billing_address( $order_id, $this->customer_type ),
				'shipping' => KP_Customer_Data::get_shipping_address( $order_id, $this->customer_type ),
			),
			'time'      => time(),
		);
		update_post_meta( $order_id, '_wc_klarna_country', kp_get_klarna_country() );

		// Add #kp hash to checkout url so we can do a finalize call to Klarna.
		return array(
			'result'   => 'success',
			'redirect' => '#kp=' . base64_encode( wp_json_encode( $response ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to hide some data from the frontend.
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
	 * @param array $order WooCommerce order.
	 * @param array $klarna_place_order_response The Klarna place order response.
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
