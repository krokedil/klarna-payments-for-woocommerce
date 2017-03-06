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
	 * Klarna country.
	 *
	 * @var string
	 *
	 * @TODO: Change this when EU is also available.
	 */
	public $shop_country = 'US';

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
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'klarna_payments';
		$this->method_title         = __( 'Klarna Payments', 'woocommerce-gateway-klarna-payments' );
		$this->method_description   = __( 'Klarna Payments is our umbrella name for Klarna\'s payment methods.', 'woocommerce-gateway-klarna-payments' );
		$this->has_fields           = true;
		$this->supports             = apply_filters( 'wc_klarna_payments_supports', array( 'products' ) ); // Make this filterable.

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description', '' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->testmode               = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id            = $this->testmode ? $this->get_option( 'test_merchant_id_us' ) : $this->get_option( 'merchant_id_us', '' ); // @TODO: Test if live credentials are pulled when needed.
		$this->shared_secret          = $this->testmode ? $this->get_option( 'test_shared_secret_us' ) : $this->get_option( 'shared_secret_us', '' );
		$this->logging                = 'yes' === $this->get_option( 'logging' );

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

		if ( $this->testmode ) {
			$this->description .= ' ' . __( '<p>TEST MODE ENABLED.</p>', 'woocommerce-gateway-klarna-payments' );
			$this->description  = trim( $this->description );

			$this->server_base = 'https://api-na.playground.klarna.com/';
		} else {
			$this->server_base = 'https://api-na.klarna.com/';
		}

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_head', array( $this, 'klarna_payments_session' ), 10, 1 );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'klarna_payments_session_ajax_update' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_authorization_token' ) );
		add_action( 'woocommerce_api_wc_gateway_klarna_payments', array( $this, 'notification_listener' ) );
		add_filter( 'wc_klarna_payments_create_session_args', array( $this, 'iframe_options' ) );
		if ( '' !== $this->background ) {
			add_action( 'wp_head', array( $this, 'iframe_background' ) );
		}
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
			'test_merchant_id_us' => array(
				'title'       => __( 'Test merchant ID (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_us' => array(
				'title'       => __( 'Test shared secret (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_us' => array(
				'title'       => __( 'Live merchant ID (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_us' => array(
				'title'       => __( 'Live shared secret (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'prescreen' => array(
				'title'       => __( 'Prescreen', 'woocommerce-gateway-klarna-payments' ),
				'label'       => __( 'Enable Prescreen (US merchants only)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'default'     => 'no',
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

			'iframe_options' => array(
				'title' => 'Iframe settings',
				'type'  => 'title',
			),
			'background' => array(
				'title'       => 'Background',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_button' => array(
				'title'       => 'Button color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_button_text' => array(
				'title'       => 'Button text color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox' => array(
				'title'       => 'Checkbox color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox_checkmark' => array(
				'title'       => 'Checkbox checkmark color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_header' => array(
				'title'       => 'Header color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_link' => array(
				'title'       => 'Link color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_border' => array(
				'title'       => 'Border color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_border_selected' => array(
				'title'       => 'Selected border color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_text' => array(
				'title'       => 'Text color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_details' => array(
				'title'       => 'Details color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_text_secondary' => array(
				'title'       => 'Secondary text color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'radius_border' => array(
				'title'       => 'Border radius (px)',
				'type'        => 'number',
				'default'     => '',
				'desc_tip'    => true,
			),
		) );
	}

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		

		$icon_html = '<a style="font-size: .83em" onclick="javascript:window.open(\'https://www.klarna.com/us/pay-over-time\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;" target="_blank" href="https://www.klarna.com/us/pay-over-time" title="What is Klarna?"><img src="https://cdn.klarna.com/1.0/shared/image/generic/logo/en_us/basic/black.png?width=68" alt="Klarna" /></a>';

		$icon_html .= '<a href="https://www.klarna.com/us/pay-over-time" class="about_paypal" onclick="javascript:window.open(\'https://www.klarna.com/us/pay-over-time\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">What is Klarna?</a>';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Check if Klarna Payments should be available
	 */
	public function is_available() {
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

		// Need to calculate these here, because WooCommerce hasn't done it yet.
		WC()->cart->calculate_fees();
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		if ( '' === WC()->customer->get_country() ) {
			$purchase_country = $this->shop_country;
		} else {
			$purchase_country = WC()->customer->get_country();
		}

		$klarna_payments_params = array();
		$klarna_payments_params['testmode'] = $this->testmode;

		$order_lines_processor = new WC_Klarna_Payments_Order_Lines( $this->shop_country );
		$order_lines = $order_lines_processor->order_lines();
		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret ),
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( apply_filters( 'wc_klarna_payments_session_request_body', array(
				'purchase_country'  => $purchase_country,
				'purchase_currency' => 'USD',
				'locale'            => 'en-US',
				'order_amount'      => $order_lines['order_amount'],
				'order_tax_amount'  => $order_lines['order_tax_amount'],
				'order_lines'       => $order_lines['order_lines'],
			) ) ),
		);

		if ( WC()->session->get( 'klarna_payments_session_id' ) ) { // Check if we have session ID.
			// Try to update the session, if it fails try to create new session.
			$update_request_url = $this->server_base . 'credit/v1/sessions/' . WC()->session->get( 'klarna_payments_session_id' );
			$update_response = $this->update_session_request( $update_request_url, $request_args );

			if ( is_wp_error( $update_response ) ) { // If update session failed try to create new session.
				WC()->session->__unset( 'klarna_payments_session_id' );
				WC()->session->__unset( 'klarna_payments_client_token' );

				$create_request_url = $this->server_base . 'credit/v1/sessions';
				$create_response = $this->create_session_request( $create_request_url, $request_args );

				if ( is_wp_error( $create_response ) ) { // Create failed, make Klarna Payments unavailable.
					$this->session_error = $create_response;
					wc_add_notice( 'Could not create Klarna session, please refresh the page to try again', 'error' );

					WC()->session->__unset( 'klarna_payments_session_id' );
					WC()->session->__unset( 'klarna_payments_client_token' );
				} else { // Store session ID and client token in WC session.
					WC()->session->set( 'klarna_payments_session_id', $create_response->session_id );
					WC()->session->set( 'klarna_payments_client_token', $create_response->client_token );
				}
			}
		} else {
			// If we dont have a session already, create one now.
			$create_request_url = $this->server_base . 'credit/v1/sessions';
			$create_response = $this->create_session_request( $create_request_url, $request_args );

			if ( is_wp_error( $create_response ) ) { // If update session failed try to create new session.
				$this->session_error = $create_response;
				wc_add_notice( 'Could not create Klarna session, please refresh the page to try again', 'error' );

				WC()->session->__unset( 'klarna_payments_session_id' );
				WC()->session->__unset( 'klarna_payments_client_token' );
			} else {
				WC()->session->set( 'klarna_payments_session_id', $create_response->session_id );
				WC()->session->set( 'klarna_payments_client_token', $create_response->client_token );
			}
		}

		// If we have a client token now, initialize Klarna Credit.
		if ( WC()->session->get( 'klarna_payments_client_token' ) ) {
			?>
			<script>
				window.klarnaInitData = {client_token: "<?php echo esc_attr(WC()->session->get('klarna_payments_client_token')); ?>"};
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
		if ( is_ajax() && WC()->session->get( 'klarna_payments_session_id' ) ) { // On AJAX update_checkout, just try to update the session.
			// Need to calculate these here, because WooCommerce hasn't done it yet.
			WC()->cart->calculate_fees();
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();

			if ( '' === WC()->customer->get_country() ) {
				$purchase_country = $this->shop_country;
			} else {
				$purchase_country = WC()->customer->get_country();
			}

			$order_lines_processor = new WC_Klarna_Payments_Order_Lines( $this->shop_country );
			$order_lines = $order_lines_processor->order_lines();
			$request_args = array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret ),
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( array(
					'purchase_country'  => $purchase_country,
					'purchase_currency' => 'USD',
					'locale'            => 'en-US',
					'order_amount'      => $order_lines['order_amount'],
					'order_tax_amount'  => $order_lines['order_tax_amount'],
					'order_lines'       => $order_lines['order_lines'],
				) ),
			);

			// Try to update the session, if it fails try to create new session.
			$update_request_url = $this->server_base . 'credit/v1/sessions/' . WC()->session->get( 'klarna_payments_session_id' );
			$update_response = $this->update_session_request( $update_request_url, $request_args );

			if ( is_wp_error( $update_response ) ) { // If update session failed try to create new session.
				$this->session_error = $update_response;
				wc_add_notice( 'Could not update Klarna session, please refresh the page to try again', 'error' );

				WC()->session->__unset( 'klarna_payments_session_id' );
				WC()->session->__unset( 'klarna_payments_client_token' );
			}
		}
	}

	/**
	 * Create Klarna Payments session.
	 *
	 * @param string $request_url  Klarna request URL.
	 * @param array  $request_args Klarna request arguments.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function create_session_request( $request_url, $request_args ) {
		// Make it filterable.
		$request_args = apply_filters( 'wc_klarna_payments_create_session_args', $request_args );

		$response = wp_safe_remote_post( $request_url, $request_args );
		$decoded = json_decode( $response['body'] );

		if ( 200 === $response['response']['code'] ) {
			return $decoded;
		} else {
			return new WP_Error( $response['response']['code'], $response['response']['message'] );
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

		$response = wp_safe_remote_post( $request_url, $request_args );
		$decoded = json_decode( $response['body'] );

		if ( 204 === $response['response']['code'] ) {
			return $decoded;
		} else {
			return new WP_Error( $response['response']['code'], $response['response']['message'] );
		}
	}

	/**
	 * Adds Klarna Payments container to checkout page.
	 */
	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		echo '<div><a style="font-size: .83em" onclick="javascript:window.open(\'https://www.klarna.com/us/pay-over-time\',\'WIKlarna\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;" target="_blank" href="https://www.klarna.com/us/pay-over-time" title="What is Klarna?">What is Klarna?</a></div>';
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
		$klarna_payments_params = array();
		$klarna_payments_params['testmode'] = $this->testmode;
		wp_localize_script( 'klarna_payments', 'klarna_payments_params', $klarna_payments_params );
		wp_enqueue_script( 'klarna_payments' );
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
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Place order.
		$response = $this->place_order( $order_id, $_POST['klarna_payments_authorization_token'] );

		// Process the response.
		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );

			if ( 'ACCEPTED' === $decoded->fraud_status ) {
				$order->payment_complete( $decoded->order_id );
				$order->add_order_note( 'Payment via Klarna Payments, order ID: ' . $decoded->order_id );
				add_post_meta( $order_id, '_wc_klarna_order_id', $decoded->order_id, true );

				do_action( 'wc_klarna_payments_accepted', $order_id, $decoded );
				do_action( 'wc_klarna_accepted', $order_id, $decoded );
			} elseif ( 'PENDING' === $decoded->fraud_status ) {
				$order->update_status( 'on-hold', 'Klarna order is under review, order ID: ' . $decoded->order_id );
				add_post_meta( $order_id, '_wc_klarna_order_id', $decoded->order_id, true );

				do_action( 'wc_klarna_payments_pending', $order_id, $decoded );
				do_action( 'wc_klarna_pending', $order_id, $decoded );
			} elseif ( 'REJECTED' === $decoded->fraud_status ) {
				$order->update_status( 'on-hold', 'Klarna order was rejected.' );

				do_action( 'wc_klarna_payments_rejected', $order_id, $decoded );
				do_action( 'wc_klarna_rejected', $order_id, $decoded );

				return array(
					'result'   => 'failure',
					'redirect' => '',
					'messages' => '<div class="woocommerce-error">Klarna payment rejected</div>',
				);
			}

			if ( true === $this->testmode ) {
				update_post_meta( $order_id, '_wc_klarna_environment', 'us-test' );
			} else {
				update_post_meta( $order_id, '_wc_klarna_environment', 'us-live' );
			}

			WC()->session->__unset( 'klarna_payments_session_id' );
			WC()->session->__unset( 'klarna_payments_client_token' );

			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} else {
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			} else {
				$error_message = 'Klarna error failed. ' . $response['response']['code'] . ' - ' . $response['response']['message'] . '.';
			}

			wc_add_notice( $error_message, 'error' );

			// Return failure if something went wrong.
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}
	}

	/**
	 * Places the order with Klarna
	 *
	 * @param int    $order_ir   WooCommerce order ID.
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

		if ( ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only() ) {
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
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret ),
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'purchase_country'    => 'US',
				'purchase_currency'   => 'USD',
				'locale'              => 'en-US',
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
	 * @param int      $order_id WooCommerce order ID.
	 * @param null|int $amount   Refund amount.
	 * @param string   $reason   Reason for refund.
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
			$decoded_body = json_decode( $request_args['body'] );
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

}
