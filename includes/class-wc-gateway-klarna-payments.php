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
	 * Turns on logging.
	 *
	 * @var string
	 */
	public $logging = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'klarna_payments';
		$this->method_title         = __( 'Klarna Payments', 'woocommerce-gateway-klarna-payments' );
		$this->method_description   = __( 'Klarna Payments is our umbrella name for Klarna\'s payment methods.', 'woocommerce-gateway-klarna-payments' );
		$this->has_fields           = true;
		$this->supports             = array(
			'products',
			'refunds',
			'add_payment_method',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->testmode               = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id            = $this->testmode ? $this->get_option( 'test_merchant_id' ) : $this->get_option( 'merchant_id' );
		$this->shared_secret          = $this->testmode ? $this->get_option( 'test_shared_secret' ) : $this->get_option( 'shared_secret' );
		$this->logging                = 'yes' === $this->get_option( 'logging' );

		if ( $this->testmode ) {
			$this->description .= ' ' . __( 'TEST MODE ENABLED.', 'woocommerce-gateway-klarna-payments' );
			$this->description  = trim( $this->description );

			$this->server_base = 'https://api-na.playground.klarna.com/';
		} else {
			$this->server_base = 'https://api-na.klarna.com/';
		}

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_footer', array( $this, 'klarna_payments_sdk' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'klarna_payments_session' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'add_klarna_payments_container' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_authorization_token' ) );
		add_action( 'woocommerce_after_order_notes', array( $this, 'add_authorization_token_field' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters( 'wc_gateway_klarna_payments_settings', array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				'label'       => __( 'Enable Klarna Payments', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Klarna Payments', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
				'default'     => __( 'Pay with Klarna Payments.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
				'default'     => __( 'Pay with Klarna Payments.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'test_merchant_id' => array(
				'title'       => __( 'Test merchant ID', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret' => array(
				'title'       => __( 'Test shared secret', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id' => array(
				'title'       => __( 'Live merchant ID', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret' => array(
				'title'       => __( 'Live shared secret', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode' => array(
				'title'       => __( 'Test mode', 'woocommerce-gateway-klarna-payments' ),
				'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging' => array(
				'title'       => __( 'Logging', 'woocommerce-gateway-klarna-payments' ),
				'label'       => __( 'Log debug messages', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		) );
	}


	/**
	 * Klarna Payments SDK.
	 *
	 * @access public
	 */
	public function klarna_payments_sdk() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		?>
		<script type="text/javascript" id="klarna-credit-lib-x">
		  /* <![CDATA[ */
		  (function(w,d) {
		    var url = "https://credit.klarnacdn.net/lib/v1/api.js";
		    n = d.createElement("script");
		    c = d.getElementById("klarna-credit-lib-x");
		    n.async = !0;
		    n.src = url + "?" + (new Date()).getTime();
		    c.parentNode.replaceChild(n, c);
		  })(this,document);
		  /* ]]> */
		</script>
		<?php
	}

	/**
	 * Check if Klarna Payments should be available
	 */
	public function is_available() {
		// Currently only available for US and UK.
		if ( in_array( WC()->customer->get_country(), array( 'US', 'GB' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Place Klarna Payments order, after authorization.
	 *
	 * Uses authorization token to place the order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Place order.
		$response = $this->place_order( $_POST['klarna_payments_authorization_token'] );

		// Process the response.
		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );

			if ( 'ACCEPTED' === $decoded->fraud_status ) {
				$order->payment_complete( $decoded->order_id );
				$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded->order_id );
				add_post_meta( $order_id, '_wc_klarna_payments_order_id', $decoded->order_id, true );
			} elseif ( 'PENDING' === $decoded->fraud_status ) {
				// Process pending here.
			}

			if ( true === $this->testmode ) {
				update_post_meta( $order_id, '_wc_klarna_payments_env', 'test' );
			} else {
				update_post_meta( $order_id, '_wc_klarna_payments_env', 'live' );
			}

			WC()->session->__unset( 'klarna_payments_session_id' );
			WC()->session->__unset( 'klarna_payments_client_token' );

			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		// Return failure if something went wrong.
		return array(
			'result'   => 'fail',
			'redirect' => '',
		);
	}

	/**
	 * Create Klarna Payments session.
	 *
	 * @TODO: Improve how session update/create is handled. Currently never updating, but it should if we already have a valid session to work with.
	 */
	public function klarna_payments_session() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$klarna_payments_params = array();
		$klarna_payments_params['testmode'] = $this->testmode;

		$order_lines_processor = new WC_Klarna_Payments_Order_Lines();
		$order_lines = $order_lines_processor->order_lines();

		// Create session on first Checkout page load.
		if ( ! is_ajax() ) {
			WC()->session->__unset( 'klarna_payments_session_id' );
			WC()->session->__unset( 'klarna_payments_client_token' );

			$request_url  = $this->server_base . 'credit/v1/sessions';
		} else {
			$request_url = $this->server_base . 'credit/v1/sessions/' . WC()->session->get( 'klarna_payments_session_id' );
			$klarna_payments_params['client_token'] = WC()->session->get( 'klarna_payments_client_token' );
		}

		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret ),
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'purchase_country'  => 'US',
				'purchase_currency' => 'USD',
				'locale'            => 'en-US',
				'order_amount'      => $order_lines['order_amount'],
				'order_tax_amount'  => $order_lines['order_tax_amount'],
				'order_lines'       => $order_lines['order_lines'],
			) ),
		);

		$response = wp_safe_remote_post( $request_url, $request_args );

		// Process the response.
		if ( 200 === $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );
			$klarna_payments_params['client_token'] = $decoded->client_token;

			WC()->session->set( 'klarna_payments_session_id', $decoded->session_id );
			WC()->session->set( 'klarna_payments_client_token', $decoded->session_id );
		}

		wp_localize_script( 'klarna_payments', 'klarna_payments_params', $klarna_payments_params );
	}

	/**
	 * Adds Klarna Payments container to checkout page.
	 */
	public function add_klarna_payments_container() {
		$this->description .= '<div id="klarna_container"></div>';
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @access public
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
		wp_enqueue_script( 'klarna_payments' );
	}

	/**
	 * Check posted data for authorization token.
	 *
	 * If authorization token is missing, we'll add error notice and bail.
	 * Authorization token field is added to the form in JavaScript, when Klarna.Credit.authorize is completed.
	 */
	public function check_authorization_token() {
		if ( ! $_POST['klarna_payments_authorization_token'] ) { // Input var okay.
			wc_add_notice( __( 'Could not create Klarna Payments authorization token.' ), 'error' );
		}
	}

	/**
	 * Places the order with Klarna
	 *
	 * @TODO: Ask about shipping phone and email. OK to use billing instead?
	 *
	 * @param string $auth_token Klarna Payments authorization token.
	 *
	 * @return array|WP_Error
	 */
	public function place_order( $auth_token ) {
		$order_lines_processor = new WC_Klarna_Payments_Order_Lines();
		$order_lines = $order_lines_processor->order_lines();

		$posted_data = $_POST; // Input var okay.

		$billing_address = array(
			'given_name' => $posted_data['billing_first_name'],
			'family_name' => $posted_data['billing_last_name'],
			'email' => $posted_data['billing_email'],
			'phone' => $posted_data['billing_phone'],
			// 'title' => 'Mr',
			'street_address' => $posted_data['billing_address_1'],
			'street_address2' => $posted_data['billing_address_2'],
			'postal_code' => $posted_data['billing_postcode'],
			'city' => $posted_data['billing_city'],
			'region' => $posted_data['billing_state'],
			'country' => $posted_data['billing_country'],
		);

		if ( ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only() ) {
			$shipping_address = array(
				'given_name' => $posted_data['shipping_first_name'],
				'family_name' => $posted_data['shipping_last_name'],
				'email' => $posted_data['billing_email'],
				'phone' => $posted_data['shipping_email'],
				// 'title' => 'Mr',
				'street_address' => $posted_data['shipping_address_1'],
				'street_address2' => $posted_data['shipping_address_2'],
				'postal_code' => $posted_data['shipping_postcode'],
				'city' => $posted_data['shipping_city'],
				'region' => $posted_data['shipping_state'],
				'country' => $posted_data['shipping_country'],
			);
		} else {
			$shipping_address = $billing_address;
		}

		$request_url  = $this->server_base . 'credit/v1/authorizations/' . $auth_token . '/order';
		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret ),
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'purchase_country'  => 'US',
				'purchase_currency' => 'USD',
				'locale'            => 'en-US',
				'billing_address'   => $billing_address,
				'shipping_address'   => $shipping_address,
				'order_amount'      => $order_lines['order_amount'],
				'order_tax_amount'  => $order_lines['order_tax_amount'],
				'order_lines'       => $order_lines['order_lines'],
			) ),
		);

		return wp_safe_remote_post( $request_url, $request_args );
	}

}