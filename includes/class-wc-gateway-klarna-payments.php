<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Klarna_Payments extends WC_Payment_Gateway {

	/**
	 * Client token retrieved from Klarna when session is created.
	 *
	 * @var string
	 */
	public $client_token;

	/**
	 * Sets Klarna Payments in test mode.
	 *
	 * @var string
	 */
	public $testmode = 'no';

	/**
	 * Klarna payments environment (US or EU).
	 *
	 * @var string
	 */
	public $environment = '';

	/**
	 * Klarna payments server base url.
	 *
	 * @var string
	 */
	public $server_base = '';

	/**
	 * Klarna merchant ID.
	 *
	 * @var string
	 */
	public $merchant_id = '';

	/**
	 * Klarna shared secret.
	 *
	 * @var string
	 */
	public $shared_secret = '';

	/**
	 * Shop base country.
	 *
	 * @var string
	 */
	public $shop_country;

	/**
	 * Klarna purchase country.
	 *
	 * @var string
	 */
	public $klarna_country;

	/**
	 * Allowed currencies
	 *
	 * @var array
	 */
	public $allowed_currencies = array( 'USD', 'GBP', 'SEK', 'NOK', 'EUR', 'DKK' );

	/**
	 * Turns on logging.
	 *
	 * @var WC_Logger
	 */
	public $logging = false;

	/**
	 * Klarna Payments create session error.
	 *
	 * @var bool|WP_Error
	 */
	public $session_error = false;

	/**
	 * Klarna Payments iframe background.
	 *
	 * @var string
	 */
	public $background;

	/**
	 * Klarna Payments iframe button color.
	 *
	 * @var string
	 */
	public $color_button;

	/**
	 * Klarna Payments iframe button text color.
	 *
	 * @var string
	 */
	public $color_button_text;

	/**
	 * Klarna Payments iframe checkbox color.
	 *
	 * @var string
	 */
	public $color_checkbox;

	/**
	 * Klarna Payments iframe checkbox checkmark color.
	 *
	 * @var string
	 */
	public $color_checkbox_checkmark;

	/**
	 * Klarna Payments iframe header color.
	 *
	 * @var string
	 */
	public $color_header;

	/**
	 * Klarna Payments iframe link color.
	 *
	 * @var string
	 */
	public $color_link;

	/**
	 * Klarna Payments iframe border color.
	 *
	 * @var string
	 */
	public $color_border;

	/**
	 * Klarna Payments iframe selected border color.
	 *
	 * @var string
	 */
	public $color_border_selected;

	/**
	 * Klarna Payments iframe text color.
	 *
	 * @var string
	 */
	public $color_text;

	/**
	 * Klarna Payments iframe details color.
	 *
	 * @var string
	 */
	public $color_details;

	/**
	 * Klarna Payments iframe secondary text color.
	 *
	 * @var string
	 */
	public $color_text_secondary;

	/**
	 * Klarna Payments radius border.
	 *
	 * @var string
	 */
	public $radius_border;

	/**
	 * Float What is Klarna? link in checkout page.
	 *
	 * @var string
	 */
	public $float_what_is_klarna;

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
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description', '' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->testmode    = 'yes' === $this->get_option( 'testmode' );
		$this->logging     = 'yes' === $this->get_option( 'logging' );

		$this->set_klarna_country();
		$this->set_environment();
		$this->set_credentials();

		// Iframe options.
		$this->background               = $this->get_option( 'background' );
		$this->color_button             = $this->get_option( 'color_button' );
		$this->color_button_text        = $this->get_option( 'color_button_text' );
		$this->color_checkbox           = $this->get_option( 'color_checkbox' );
		$this->color_checkbox_checkmark = $this->get_option( 'color_checkbox_checkmark' );
		$this->color_header             = $this->get_option( 'color_header' );
		$this->color_link               = $this->get_option( 'color_link' );
		$this->color_border             = $this->get_option( 'color_border' );
		$this->color_border_selected    = $this->get_option( 'color_border_selected' );
		$this->color_text               = $this->get_option( 'color_text' );
		$this->color_details            = $this->get_option( 'color_details' );
		$this->color_text_secondary     = $this->get_option( 'color_text_secondary' );
		$this->radius_border            = $this->get_option( 'radius_border' );

		// What is Klarna link.
		$this->float_what_is_klarna = 'yes' === $this->get_option( 'float_what_is_klarna' );

		$env_string = 'US' === $this->klarna_country ? '-na' : '';
		if ( $this->testmode ) {
			$this->description .= ' ' . __( '<p>TEST MODE ENABLED.</p>', 'klarna-payments-for-woocommerce' );
			$this->description = trim( $this->description );

			$this->server_base = 'https://api' . $env_string . '.playground.klarna.com/';
		} else {
			$this->server_base = 'https://api' . $env_string . '.klarna.com/';
		}

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_action( 'wp_head', array( $this, 'klarna_payments_session' ), 10, 1 );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'klarna_payments_session_ajax_update' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_authorization_token' ) );
		add_action( 'woocommerce_api_wc_gateway_klarna_payments', array( $this, 'notification_listener' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'address_notice' ) );
		add_filter( 'wc_klarna_payments_create_session_args', array( $this, 'iframe_options' ) );
		if ( '' !== $this->background ) {
			add_action( 'wp_head', array( $this, 'iframe_background' ) );
		}
	}

	/**
	 * Sets Klarna environment based on country and testmode.
	 */
	public function set_environment() {
		if ( $this->testmode ) {
			$this->environment = 'test';
		} else {
			$this->environment = 'live';
		}
	}

	/**
	 * Sets Klarna credentials.
	 */
	public function set_credentials() {
		if ( $this->testmode ) {
			$this->merchant_id   = $this->get_option( 'test_merchant_id_' . strtolower( $this->klarna_country ) );
			$this->shared_secret = $this->get_option( 'test_shared_secret_' . strtolower( $this->klarna_country ) );
		} else {
			$this->merchant_id   = $this->get_option( 'merchant_id_' . strtolower( $this->klarna_country ), '' );
			$this->shared_secret = $this->get_option( 'shared_secret_' . strtolower( $this->klarna_country ), '' );
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		include_once( WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/klarna-payments-admin-form-fields.php' );
		$this->form_fields = Klarna_Payments_Form_Fields::fields();
	}

	/**
	 * Get gateway title.
	 *
	 * @access public
	 * @return string
	 */
	public function get_title() {
		switch ( $this->klarna_country ) {
			case 'US' :
				if ( '' !== $this->get_option( 'title_us' ) ) {
					$this->title = $this->get_option( 'title_us' );
				}
				break;
			case 'GB' :
				if ( '' !== $this->get_option( 'title_gb' ) ) {
					$this->title = $this->get_option( 'title_gb' );
				}
				break;
			case 'SE' :
				if ( '' !== $this->get_option( 'title_se' ) ) {
					$this->title = $this->get_option( 'title_se' );
				}
				break;
			case 'NO' :
				if ( '' !== $this->get_option( 'title_no' ) ) {
					$this->title = $this->get_option( 'title_no' );
				}
				break;
			case 'FI' :
				if ( '' !== $this->get_option( 'title_fi' ) ) {
					$this->title = $this->get_option( 'title_fi' );
				}
				break;
			case 'DK' :
				if ( '' !== $this->get_option( 'title_dk' ) ) {
					$this->title = $this->get_option( 'title_dk' );
				}
				break;
			case 'NL' :
				if ( '' !== $this->get_option( 'title_nl' ) ) {
					$this->title = $this->get_option( 'title_nl' );
				}
				break;
			case 'AT' :
				if ( '' !== $this->get_option( 'title_at' ) ) {
					$this->title = $this->get_option( 'title_at' );
				}
				break;
			case 'DE' :
				if ( '' !== $this->get_option( 'title_de' ) ) {
					$this->title = $this->get_option( 'title_de' );
				}
				break;
		} // End switch().

		return apply_filters( 'woocommerce_gateway_title', $this->title, $this->id );
	}

	/**
	 * Get gateway description.
	 *
	 * @access public
	 * @return string
	 */
	public function get_description() {
		switch ( $this->klarna_country ) {
			case 'US' :
				if ( '' !== $this->get_option( 'description_us' ) ) {
					$this->description = $this->get_option( 'description_us' );
				}
				break;
			case 'GB' :
				if ( '' !== $this->get_option( 'description_gb' ) ) {
					$this->description = $this->get_option( 'description_gb' );
				}
				break;
			case 'SE' :
				if ( '' !== $this->get_option( 'description_se' ) ) {
					$this->description = $this->get_option( 'description_se' );
				}
				break;
			case 'NO' :
				if ( '' !== $this->get_option( 'description_no' ) ) {
					$this->description = $this->get_option( 'description_no' );
				}
				break;
			case 'FI' :
				if ( '' !== $this->get_option( 'description_fi' ) ) {
					$this->description = $this->get_option( 'description_fi' );
				}
				break;
			case 'DK' :
				if ( '' !== $this->get_option( 'description_dk' ) ) {
					$this->description = $this->get_option( 'description_dk' );
				}
				break;
			case 'NL' :
				if ( '' !== $this->get_option( 'description_nl' ) ) {
					$this->description = $this->get_option( 'description_nl' );
				}
				break;
			case 'AT' :
				if ( '' !== $this->get_option( 'description_at' ) ) {
					$this->description = $this->get_option( 'description_at' );
				}
				break;
			case 'DE' :
				if ( '' !== $this->get_option( 'description_de' ) ) {
					$this->description = $this->get_option( 'description_de' );
				}
				break;
		} // End switch().

		return apply_filters( 'woocommerce_gateway_description', $this->description, $this->id );
	}

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		// If default WooCommerce CSS is used, float "What is Klarna link like PayPal does it".
		if ( $this->float_what_is_klarna ) {
			$link_style = 'style="float: right; line-height: 52px; font-size: .83em;"';
		} else {
			$link_style = '';
		}

		$what_is_klarna_text = 'What is Klarna?';

		if ( 'us' === strtolower( $this->klarna_country ) ) {
			$link_url = 'https://www.klarna.com/us/business/what-is-klarna';
		} elseif ( 'at' === strtolower( $this->klarna_country ) || 'de' === strtolower( $this->klarna_country ) ) {
			$link_url = 'https://www.klarna.com';
		} else {
			$link_url = 'https://www.klarna.com/uk/what-we-do';
		}

		// Change text for Germany
		$locale = get_locale();
		if ( stripos( $locale, 'de' ) !== false ) {
			$what_is_klarna_text = 'Was ist Klarna?';
		}

		$icon_html = '<img src="https://cdn.klarna.com/1.0/shared/image/generic/logo/en_us/basic/logo_black.png?width=75" alt="Klarna" />';
		$icon_html .= '<a ' . $link_style . ' href="' . $link_url . '" onclick="window.open(\'' . $link_url . '\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . $what_is_klarna_text . '</a>';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Check country and currency
	 *
	 * Fired before create session and update session, and inside is_available.
	 */
	public function country_currency_check() {
		// Check if allowed currency.
		if ( ! in_array( get_woocommerce_currency(), $this->allowed_currencies, true ) ) {
			$this->unset_session_values();

			return new WP_Error( 'currency', 'Currency not allowed for Klarna Payments' );
		}

		// If US, check if USD used.
		if ( 'USD' === get_woocommerce_currency() ) {
			if ( 'US' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'USD must be used for US purchases' );
			}
		}

		// If GB, check if GBP used.
		if ( 'GBP' === get_woocommerce_currency() ) {
			if ( 'GB' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'GBP must be used for GB purchases' );
			}
		}

		// If SE, check if SEK used.
		if ( 'SEK' === get_woocommerce_currency() ) {
			if ( 'SE' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'SEK must be used for SE purchases' );
			}
		}

		// If NO, check if NOK used.
		if ( 'NOK' === get_woocommerce_currency() ) {
			if ( 'NO' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'NOK must be used for NO purchases' );
			}
		}

		// If DK, check if DKK used.
		if ( 'DKK' === get_woocommerce_currency() ) {
			if ( 'DK' !== $this->klarna_country ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'DKK must be used for DK purchases' );
			}
		}

		// If EUR country, check if EUR used.
		if ( 'EUR' === get_woocommerce_currency() ) {
			if ( ! in_array( $this->klarna_country, array( 'AT', 'DE', 'NL', 'FI' ), true ) ) {
				$this->unset_session_values();

				return new WP_Error( 'currency', 'EUR must be used for AT, DE, NL and FI purchases' );
			}
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

		$this->set_klarna_country();
		$this->set_credentials();

		// Check credentials.
		if ( '' === $this->merchant_id || '' === $this->shared_secret ) {
			return false;
		}

		// Check country and currency.
		if ( is_wp_error( $this->country_currency_check() ) ) {
			return false;
		}

		// Check if there was a session error.
		if ( is_wp_error( $this->session_error ) ) {
			return false;
		}

		if ( '' === $this->merchant_id || '' === $this->shared_secret ) {
			return false;
		}

		return true;
	}

	/**
	 * Create Klarna Payments session.
	 *
	 * @hook wp_head
	 */
	public function klarna_payments_session() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		// See if KP is available for country / currency combo.
		if ( ! $this->is_available() ) {
			return;
		}

		// Need to calculate these here, because WooCommerce hasn't done it yet.
		WC()->cart->calculate_fees();
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$order_lines_processor = new WC_Klarna_Payments_Order_Lines( $this->shop_country );
		$order_lines           = $order_lines_processor->order_lines();
		$request_args          = array(
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) ),
				'Content-Type'  => 'application/json',
			),
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - KP:' . WC_KLARNA_PAYMENTS_VERSION,
			'body'       => wp_json_encode( apply_filters( 'wc_klarna_payments_session_request_body', array(
				'purchase_country'  => $this->klarna_country,
				'purchase_currency' => get_woocommerce_currency(),
				'locale'            => $this->get_locale_for_klarna_country(),
				'order_amount'      => $order_lines['order_amount'],
				'order_tax_amount'  => $order_lines['order_tax_amount'],
				'order_lines'       => $order_lines['order_lines'],
			) ) ),
		);

		if ( WC()->session->get( 'klarna_payments_session_id' ) && ( WC()->checkout->get_value( 'billing_country' ) === WC()->session->get( 'klarna_payments_session_country' ) ) ) { // Check if we have session ID and country has not changed.
			// Try to update the session, if it fails try to create new session.
			$update_request_url = $this->server_base . 'credit/v1/sessions/' . WC()->session->get( 'klarna_payments_session_id' );
			$update_response    = $this->update_session_request( $update_request_url, $request_args );

			if ( is_wp_error( $update_response ) ) { // If update session failed try to create new session.
				$this->unset_session_values();
				$this->create_session( $request_args );
			}
		} else {
			$this->create_session( $request_args );
		}

		// If we have a client token now, initialize Klarna Credit.
		if ( WC()->session->get( 'klarna_payments_client_token' ) ) {
			?>
			<script>
				window.klarnaInitData = {client_token: "<?php echo esc_attr( WC()->session->get( 'klarna_payments_client_token' ) ); ?>"};
				window.klarnaAsyncCallback = function () {
					Klarna.Credit.init(klarnaInitData);
				};
			</script>
			<script src="https://credit.klarnacdn.net/lib/v1/api.js" async></script>
			<?php
		}
	}


	/**
	 * Update Klarna session on AJAX update_checkout.
	 */
	public function klarna_payments_session_ajax_update() {
		if ( ! $this->country_currency_check() ) {
			return;
		}

		if ( ! is_ajax() ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		// Need to calculate these here, because WooCommerce hasn't done it yet.
		WC()->cart->calculate_fees();
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$order_lines_processor = new WC_Klarna_Payments_Order_Lines( $this->shop_country );
		$order_lines           = $order_lines_processor->order_lines();
		$request_args          = array(
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) ),
				'Content-Type'  => 'application/json',
			),
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - KP:' . WC_KLARNA_PAYMENTS_VERSION,
			'body'       => wp_json_encode( array(
				'purchase_country'  => $this->klarna_country,
				'purchase_currency' => get_woocommerce_currency(),
				'locale'            => $this->get_locale_for_klarna_country(),
				'order_amount'      => $order_lines['order_amount'],
				'order_tax_amount'  => $order_lines['order_tax_amount'],
				'order_lines'       => $order_lines['order_lines'],
			) ),
		);

		// If billing country has changed we need a new session.
		if ( WC()->checkout->get_value( 'billing_country' ) !== WC()->session->get( 'klarna_payments_session_country' ) ) {
			$create_request_url = $this->server_base . 'credit/v1/sessions';
			$create_response    = $this->create_session_request( $create_request_url, $request_args );

			if ( is_wp_error( $create_response ) ) { // Create failed, make Klarna Payments unavailable.
				$this->session_error = $create_response;
				wc_add_notice( 'Could not create Klarna session, please refresh the page to try again', 'error' );
				$this->unset_session_values();
			} else { // Store session ID and client token in WC session.
				WC()->session->set( 'klarna_payments_session_id', $create_response->session_id );
				WC()->session->set( 'klarna_payments_client_token', $create_response->client_token );
				WC()->session->set( 'klarna_payments_session_country', WC()->checkout->get_value( 'billing_country' ) );
			}

			// If we have a client token now, initialize Klarna Credit.
			if ( WC()->session->get( 'klarna_payments_client_token' ) ) {
				?>
				<script>
					window.klarnaInitData = {client_token: "<?php echo esc_attr( WC()->session->get( 'klarna_payments_client_token' ) ); ?>"};
					window.klarnaAsyncCallback = function () {
						Klarna.Credit.init(klarnaInitData);
					};
				</script>
				<script src="https://credit.klarnacdn.net/lib/v1/api.js" async></script>
				<?php
			}
		} elseif ( WC()->session->get( 'klarna_payments_session_id' ) ) { // On AJAX update_checkout, just try to update the session, if Klarna country hasn't changed.
			$update_request_url = $this->server_base . 'credit/v1/sessions/' . WC()->session->get( 'klarna_payments_session_id' );
			$update_response    = $this->update_session_request( $update_request_url, $request_args );

			if ( is_wp_error( $update_response ) ) { // If update session failed try to create new session.
				$this->session_error = $update_response;
				wc_add_notice( 'Could not update Klarna session, please refresh the page to try again', 'error' );

				$this->unset_session_values();
			}
		} // End if().
	}

	/**
	 * Create Klarna Payments session.
	 *
	 * @param array $request_args Klarna request arguments.
	 */
	public function create_session( $request_args ) {
		$create_request_url = $this->server_base . 'credit/v1/sessions';
		$create_response    = $this->create_session_request( $create_request_url, $request_args );

		if ( is_wp_error( $create_response ) ) { // Create failed, make Klarna Payments unavailable.
			$this->session_error = $create_response;
			wc_add_notice( 'Could not create Klarna session, please refresh the page to try again', 'error' );
			$this->unset_session_values();
		} else { // Store session ID and client token in WC session.
			WC()->session->set( 'klarna_payments_session_id', $create_response->session_id );
			WC()->session->set( 'klarna_payments_client_token', $create_response->client_token );
			WC()->session->set( 'klarna_payments_session_country', $this->klarna_country );
		}
	}

	/**
	 * Create Klarna Payments session request.
	 *
	 * @param string $request_url Klarna request URL.
	 * @param array $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function create_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_create_session_args', $request_args );

		$response = wp_safe_remote_post( $request_url, $request_args );

		if ( is_array( $response ) ) {
			if ( 200 === $response['response']['code'] ) {
				$decoded = json_decode( $response['body'] );

				return $decoded;
			} else {
				return new WP_Error( $response['response']['code'], $response['response']['message'] );
			}
		} else {
			return new WP_Error( 'kp_create_session', 'Could not create Klarna Payments session.' );
		}
	}

	/**
	 * Update Klarna Payments session.
	 *
	 * @param string $request_url Klarna request URL.
	 * @param array $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function update_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_update_session_args', $request_args );

		$response = wp_safe_remote_post( $request_url, $request_args );

		if ( is_array( $response ) ) {
			if ( 204 === $response['response']['code'] ) {
				return true;
			} else {
				return new WP_Error( $response['response']['code'], $response['response']['message'] );
			}
		} else {
			return new WP_Error( 'kp_update_session', 'Could not update Klarna Payments session.' );
		}
	}

	/**
	 * Adds Klarna Payments container to checkout page.
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) );
		}

		echo '<div id="klarna_container" style="margin-top:1em;"></div>';
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_register_script(
			'klarna_payments',
			plugins_url( 'assets/js/klarna-payments.js', WC_KLARNA_PAYMENTS_MAIN_FILE ),
			array( 'jquery' ),
			WC_KLARNA_PAYMENTS_VERSION,
			true
		);

		// Localize the script.
		$klarna_payments_params             = array();
		$klarna_payments_params['testmode'] = $this->testmode;

		wp_localize_script( 'klarna_payments', 'klarna_payments_params', $klarna_payments_params );
		wp_enqueue_script( 'klarna_payments' );
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

		if ( ! isset( $_GET['section'] ) || 'klarna_payments' !== $_GET['section'] ) {
			return;
		}

		wp_enqueue_script(
			'klarna_payments_admin',
			plugins_url( 'assets/js/klarna-payments-admin.js', WC_KLARNA_PAYMENTS_MAIN_FILE )
		);
	}

	/**
	 * Check posted data for authorization token.
	 *
	 * If authorization token is missing, we'll add error notice and bail.
	 * Authorization token field is added to the form in JavaScript, when Klarna.Credit.authorize is completed.
	 *
	 * @param array $posted Posted data on WooCommerce checkout process.
	 *
	 * @hook woocommerce_after_checkout_validation
	 */
	public function check_authorization_token( $posted ) {
		if ( 'klarna_payments' !== $posted['payment_method'] ) {
			return;
		}

		if ( ! $_POST['klarna_payments_authorization_token'] ) { // Input var okay.
			wc_add_notice( __( 'Could not create Klarna Payments authorization token.' ), 'error' );
		}
	}

	/**
	 * Place Klarna Payments order, after authorization.
	 *
	 * Uses authorization token to place the order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_payment( $order_id ) {
		$auth_token = sanitize_text_field( $_POST['klarna_payments_authorization_token'] ); // Input var okay.

		$order    = wc_get_order( $order_id );
		$response = $this->place_order( $order_id, $auth_token ); // Place order.

		return $this->process_klarna_response( $response, $order );
	}

	/**
	 * Process Klarna Payments response.
	 *
	 * @param array    $response Klarna API response.
	 * @param WC_Order $order    WooCommerce order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_klarna_response( $response, $order ) {
		// Default the return array to failure.
		$return_val = array(
			'result'   => 'failure',
			'redirect' => '',
		);

		// Process the response.
		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );

			$fraud_status = $decoded->fraud_status;

			switch ( $fraud_status ) {
				case 'ACCEPTED':
					$return_val = $this->process_accepted( $order, $decoded );
					break;
				case 'PENDING':
					$return_val = $this->process_pending( $order, $decoded );
					break;
				case 'REJECTED':
					$return_val = $this->process_rejected( $order, $decoded );
					break;
			}

			update_post_meta( $order->get_id(), '_wc_klarna_environment', $this->environment );
			update_post_meta( $order->get_id(), '_wc_klarna_country', $this->klarna_country );

			$this->unset_session_values();
		} else {
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			} else {
				$error_message = 'Klarna error failed. ' . $response['response']['code'] . ' - ' . $response['response']['message'] . '.';
			}

			wc_add_notice( $error_message, 'error' );
			WC()->session->reload_checkout = true;
		} // End if().

		return $return_val;
	}

	/**
	 * Process accepted Klarna Payments order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param stdClass $decoded Klarna order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_accepted( $order, $decoded ) {
		$order->payment_complete( $decoded->order_id );
		$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded->order_id );
		update_post_meta( $order->get_id(), '_wc_klarna_order_id', $decoded->order_id, true );

		do_action( 'wc_klarna_payments_accepted', $order->get_id(), $decoded );
		do_action( 'wc_klarna_accepted', $order->get_id(), $decoded );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process pending Klarna Payments order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param stdClass $decoded Klarna order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_pending( $order, $decoded ) {
		$order->update_status( 'on-hold', 'Klarna order is under review, order ID: ' . $decoded->order_id );
		update_post_meta( $order->get_id(), '_wc_klarna_order_id', $decoded->order_id, true );
		update_post_meta( $order->get_id(), '_transaction_id', $decoded->order_id, true );

		do_action( 'wc_klarna_payments_pending', $order->get_id(), $decoded );
		do_action( 'wc_klarna_pending', $order->get_id(), $decoded );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process rejected Klarna Payments order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param stdClass $decoded Klarna order.
	 *
	 * @return array   $result  Payment result.
	 */
	public function process_rejected( $order, $decoded ) {
		$order->update_status( 'on-hold', 'Klarna order was rejected.' );

		do_action( 'wc_klarna_payments_rejected', $order->get_id(), $decoded );
		do_action( 'wc_klarna_rejected', $order->get_id(), $decoded );

		return array(
			'result'   => 'failure',
			'redirect' => '',
			'messages' => '<div class="woocommerce-error">Klarna payment rejected</div>',
		);
	}

	/**
	 * Places the order with Klarna
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @param string $auth_token Klarna Payments authorization token.
	 *
	 * @return array|WP_Error
	 */
	public function place_order( $order_id, $auth_token ) {
		$order                 = wc_get_order( $order_id );
		$order_lines_processor = new WC_Klarna_Payments_Order_Lines( $this->shop_country );
		$order_lines           = $order_lines_processor->order_lines();
		$posted_data           = $_POST; // Input var okay.

		$billing_address = array(
			'given_name'      => stripslashes( $posted_data['billing_first_name'] ),
			'family_name'     => stripslashes( $posted_data['billing_last_name'] ),
			'email'           => stripslashes( $posted_data['billing_email'] ),
			'phone'           => stripslashes( $posted_data['billing_phone'] ),
			'street_address'  => stripslashes( $posted_data['billing_address_1'] ),
			'street_address2' => stripslashes( $posted_data['billing_address_2'] ),
			'postal_code'     => stripslashes( $posted_data['billing_postcode'] ),
			'city'            => stripslashes( $posted_data['billing_city'] ),
			'region'          => stripslashes( $posted_data['billing_state'] ),
			'country'         => stripslashes( $posted_data['billing_country'] ),
		);

		if ( ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only() ) { // Input var okay.
			$shipping_address = array(
				'given_name'      => stripslashes( $posted_data['shipping_first_name'] ),
				'family_name'     => stripslashes( $posted_data['shipping_last_name'] ),
				'email'           => stripslashes( $posted_data['billing_email'] ),
				'phone'           => stripslashes( $posted_data['shipping_email'] ),
				'street_address'  => stripslashes( $posted_data['shipping_address_1'] ),
				'street_address2' => stripslashes( $posted_data['shipping_address_2'] ),
				'postal_code'     => stripslashes( $posted_data['shipping_postcode'] ),
				'city'            => stripslashes( $posted_data['shipping_city'] ),
				'region'          => stripslashes( $posted_data['shipping_state'] ),
				'country'         => stripslashes( $posted_data['shipping_country'] ),
			);
		} else {
			$shipping_address = $billing_address;
		}

		$request_url  = $this->server_base . 'credit/v1/authorizations/' . $auth_token . '/order';
		$request_args = array(
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) ),
				'Content-Type'  => 'application/json',
			),
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - KP:' . WC_KLARNA_PAYMENTS_VERSION,
			'body'       => wp_json_encode( array(
				'purchase_country'    => $this->klarna_country,
				'purchase_currency'   => get_woocommerce_currency(),
				'locale'              => $this->get_locale_for_klarna_country(),
				'billing_address'     => $billing_address,
				'shipping_address'    => $shipping_address,
				'order_amount'        => $order_lines['order_amount'],
				'order_tax_amount'    => $order_lines['order_tax_amount'],
				'order_lines'         => $order_lines['order_lines'],
				'merchant_reference1' => $order->get_order_number(),
				'merchant_urls'       => array(
					'confirmation' => $order->get_checkout_order_received_url(),
					'notification' => get_home_url() . '/wc-api/WC_Gateway_Klarna_Payments/?order_id=' . $order_id,
				),
			) ),
		);

		$response = wp_safe_remote_post( $request_url, $request_args );

		return $response;
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
	 * @param int $order_id WooCommerce order ID.
	 * @param null|int $amount Refund amount.
	 * @param string $reason Reason for refund.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'wc_klarna_payments_process_refund', false, $order_id, $amount, $reason );
	}

	/**
	 * Add display options to create session request.
	 *
	 * @param array $request_args Klarna create session request arguments.
	 *
	 * @return mixed
	 *
	 * @hook wc_klarna_payments_create_session_args
	 */
	public function iframe_options( $request_args ) {
		$options = array();

		if ( '' !== $this->color_button ) {
			$options['color_button'] = $this->color_button;
		}

		if ( '' !== $this->color_button_text ) {
			$options['color_button_text'] = $this->color_button_text;
		}

		if ( '' !== $this->color_checkbox ) {
			$options['color_checkbox'] = $this->color_checkbox;
		}

		if ( '' !== $this->color_checkbox_checkmark ) {
			$options['color_checkbox_checkmark'] = $this->color_checkbox_checkmark;
		}

		if ( '' !== $this->color_header ) {
			$options['color_header'] = $this->color_header;
		}

		if ( '' !== $this->color_link ) {
			$options['color_link'] = $this->color_link;
		}

		if ( '' !== $this->color_border ) {
			$options['color_border'] = $this->color_border;
		}

		if ( '' !== $this->color_border_selected ) {
			$options['color_border_selected'] = $this->color_border_selected;
		}

		if ( '' !== $this->color_text ) {
			$options['color_text'] = $this->color_text;
		}

		if ( '' !== $this->color_details ) {
			$options['color_details'] = $this->color_details;
		}

		if ( '' !== $this->color_text_secondary ) {
			$options['color_text_secondary'] = $this->color_text_secondary;
		}

		if ( '' !== $this->radius_border ) {
			$options['radius_border'] = $this->radius_border . 'px';
		}

		if ( ! empty( $options ) ) {
			$decoded_body          = json_decode( $request_args['body'] );
			$decoded_body->options = $options;

			$request_args['body'] = wp_json_encode( $decoded_body );
		}

		return $request_args;
	}

	/**
	 * Add <head> CSS for Klarna Payments iframe background.
	 *
	 * @hook wp_head
	 */
	public function iframe_background() {
		if ( '' !== $this->background ) {
			echo "<style type='text/css'>div#klarna_container { background: $this->background !important; padding: 10px; } div#klarna_container:empty { padding: 0; } </style>";
		}
	}

	/**
	 * Gets locale based on Klarna country.
	 *
	 * @return string
	 */
	public function get_locale_for_klarna_country() {
		switch ( $this->klarna_country ) {
			case 'AT':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-at';
				} else {
					$klarna_locale = 'de-at';
				}
				break;
			case 'DE':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-de';
				} else {
					$klarna_locale = 'de-de';
				}
				break;
			case 'DK':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-dk';
				} else {
					$klarna_locale = 'da-dk';
				}
				break;
			case 'FI':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-fi';
				} else {
					$klarna_locale = 'fi-fi';
				}
				break;
			case 'NL':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-nl';
				} else {
					$klarna_locale = 'nl-nl';
				}
				break;
			case 'NO':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-no';
				} else {
					$klarna_locale = 'nb-no';
				}
				break;
			case 'SE':
				if ( $this->site_has_english_locale() ) {
					$klarna_locale = 'en-se';
				} else {
					$klarna_locale = 'sv-se';
				}
				break;
			case 'GB':
				$klarna_locale = 'en-gb';
				break;
			case 'US':
				$klarna_locale = 'en-us';
				break;
			default:
				$klarna_locale = 'en-us';
		}

		return $klarna_locale;
	}

	/**
	 * Checks if site locale is english.
	 *
	 * @return bool
	 */
	public function site_has_english_locale() {
		return 'en_US' === get_locale() || 'en_GB' === get_locale();
	}

	/**
	 * Sets Klarna country.
	 */
	public function set_klarna_country() {
		if ( ! is_checkout() ) {
			return;
		}

		$this->klarna_country = apply_filters( 'wc_klarna_payments_country', WC()->customer->get_billing_country() );
	}

	/**
	 * Unsets Klarna Payments sessions values.
	 */
	public function unset_session_values() {
		WC()->session->__unset( 'klarna_payments_session_id' );
		WC()->session->__unset( 'klarna_payments_client_token' );
		WC()->session->__unset( 'klarna_payments_session_country' );
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

}
