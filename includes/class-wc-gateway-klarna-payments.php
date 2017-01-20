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

	private $client_token;

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
		}

		// Add Klarna Payments container.
		$this->description .= '<div id="klarna_container"></div>';

		// Hooks.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_footer', array( $this, 'klarna_payments_sdk' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'klarna_payments_session' ) );
		// add_action( 'woocommerce_after_order_notes', array( $this, 'add_authorization_token_field' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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
	 * Adds hidden field to checkout form.
	 */
	public function add_authorization_token_field() {
		woocommerce_form_field(
			'klarna_payments_authorization_token',
			array(
				'type'          => 'text',
				'class'         => array( 'hidden' ),
				'label'         => __( 'Fill in this field' ),
				'placeholder'   => __( 'Enter something' ),
			)
		);
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

		$klarna_payments_session = WC()->session->get( 'klarna_payments_session' );
		$client_token = $klarna_payments_session['client_token'];

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

		<!--
		<script type="text/javascript">
			jQuery( function( $ ) {
				'use strict';

				$( document.body ).on( 'updated_checkout', function() {
					var klarnaLoadedInterval = setInterval(function () {
						console.log("test interval");
						if (Klarna.Credit && !Klarna.Credit.initialized) {
							Klarna.Credit.init({
								client_token: "<?php echo esc_attr( $client_token ); ?>"
							});

							Klarna.Credit.load({
								container: "#klarna_container"
							}, function (res) {
								console.debug(res);
							})
						}
					}, 100);

					var klarnaLoadedTimeout = setTimeout(function () {
						clearInterval(klarnaLoadedInterval);
					}, 3000);
				});

				// @TODO: As soon as I fire Klarna.Credit.authorize, I lose form data and can't add to it
				var checkout_form = $( 'form.checkout' );
				var return_value = false;
				var token = '';

				checkout_form.on( 'checkout_place_order', function() {
					if ($('input[name="payment_method"]:checked').val() != 'klarna_payments') {
						return true;
					}

					console.log('first_check')
					checkout_form.append('<input type="hidden" name="m_prevent_submit" value="1">');

					Klarna.Credit.authorize({
						purchase_country: "US",
						purchase_currency: "USD",
						locale: "en-US",
						billing_address: {
							given_name: "John",
							family_name: "Doe",
							email: "john@doe.com",
							title: "Mr",
							street_address: "Lombard St 10",
							street_address2: "Apt 214",
							postal_code: "90210",
							city: "Beverly Hills",
							region: "CA",
							phone: "0333444555",
							country: "US"
						},
						shipping_address: {
							given_name: "John",
							family_name: "Doe",
							email: "john@doe.com",
							title: "Mr",
							street_address: "Lombard St 10",
							street_address2: "Apt 214",
							postal_code: "90210",
							city: "Beverly Hills",
							region: "CA",
							phone: "0333444555",
							country: "US"
						}
					}, function (res) {
						console.debug('res', res);

						/**
						 * res structure:
						 * { authorization_token: "b4bd3423-24e3", approved: true, show_form: true }
						 *
						 * Possible outcomes:
						 *  1. approved: true
						 *     - store authorization token in hidden form field, so it can be processed on process_payment
						 *     - token is valid for 60 minutes
						 *     - return true
						 *  2. approved: false, show_form: true
						 *     - something in the form is missing, a field left empty etc.
						 *  3. approved: false, show_form: false
						 *     - game over, hide Klarna Payments, use another payment method
						 */
						if (res.approved) {
							// $('form.woocommerce-checkout #klarna_payments_authorization_token').val(res.authorization_token);
							checkout_form.append('<input type="hidden" name="m_prevent_submit_2" value="1">');
							// checkout_form.append('<input type="hidden" name="klarna_payments_authorization_token" value="' + res.authorization_token + '">');
							token = res.authorization_token;

							console.log('flow1')
							return_value = true;
						} else {
							if (res.show_form) {
								checkout_form.append('<input type="hidden" name="m_prevent_submit_3" value="1">');
								console.log('flow2')
								return_value = true;
							} else {
								checkout_form.append('<input type="hidden" name="m_prevent_submit_4" value="1">');
								console.log('flow3')
								return_value = false;
							}
						}
					});

					console.log('return_value', return_value)
					checkout_form.append('<input type="hidden" name="klarna_payments_authorization_token" value="' + token + '">')
					checkout_form.append('<input type="hidden" name="blah_blah" value="meh">');
					return return_value;
				});
			});
		</script>
		-->
		<?php
	}

	/**
	 * Check if Klarna Payments should be available
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Place Klarna Payments order, after authorization
	 *
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Place order.
		// $this->place_order();

		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Create Klarna Payments session.
	 *
	 * @TODO: Improve how session update/create is handled.
	 * Check cart hash before sending update.
	 */
	public function klarna_payments_session() {
		$request_url  = 'https://api-na.playground.klarna.com/credit/v1/sessions';
		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'purchase_country'  => 'US',
				'purchase_currency' => 'USD',
				'locale'            => 'en-US',
				'order_amount'      => 9999,
				'order_tax_amount'  => 0,
				'order_lines'       => array(
					array(
						'type'                  => 'physical',
						'reference'             => '19-402-USA',
						'name'                  => 'Battery Power Pack',
						'quantity'              => 1,
						'unit_price'            => 9999,
						'tax_rate'              => 0,
						'total_amount'          => 9999,
						'total_discount_amount' => 0,
						'total_tax_amount'      => 0,
					),
				),
			) ),
		);

		// Change URL if we're updating Klarna Payments session.
		if ( WC()->session->get( 'klarna_payments_session' ) ) {
			$klarna_payments_session = WC()->session->get( 'klarna_payments_session' );

			if (
				isset( $klarna_payments_session['timestamp'] ) &&
				time() - $klarna_payments_session['timestamp'] > 48 * 60 * 60 &&
				null !== $klarna_payments_session['session_id'] &&
				null !== $klarna_payments_session['client_token']
			) {
				if ( md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total ) === $klarna_payments_session['cart_key]'] ) {
					return;
				}

				$request_url .= '/' . $klarna_payments_session['session_id'];
			}
		}

		$response = wp_safe_remote_post( $request_url, $request_args );

		// Response body is empty on update.
		if ( '' !== $response['body'] ) {
			$decoded = json_decode( $response['body'] );
			wp_localize_script( 'klarna_payments', 'klarna_payments_params', array( 'client_token' => $decoded->client_token ) );

			WC()->session->set( 'klarna_payments_session', array(
				'session_id'   => $decoded->session_id,
				'client_token' => $decoded->client_token,
				'timestamp'    => time(),
				'cart_hash'    => md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total ),
			) );
		}
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function enqueue_scripts() {
		if ( ! is_cart() && ! is_checkout() ) {
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

}