<?php
/**
 * Main request class
 *
 * @package WC_Klarna_Payments/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main request class
 */
class KP_Requests {
	/**
	 * Class constructor.
	 *
	 * @param int  $order_id The WooCommerce order id.
	 * @param bool $auth If the request is a auth or not.
	 */
	public function __construct( $order_id, $auth = false ) {
		$this->order_id = $order_id;
		$this->auth     = $auth;
		$this->set_environment_variables();
	}

	/**
	 * Returns headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return array(
			'Authorization' => $this->calculate_auth(),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Sets the environment.
	 *
	 * @return void
	 */
	public function set_environment_variables() {
		// Set variables.
		$this->kp_settings = get_option( 'woocommerce_payer_b2b_invoice_settings' );
		$this->testmode    = $this->kp_settings['testmode'];
		$this->set_klarna_country();
		$this->set_credentials();
		$this->environment();
	}

	/**
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// Check the status code.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$error         = json_decode( $response['body'], true );
				$error_message = $error_message . ' ' . $error['message'];
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $response['response']['message'] . $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Sets Klarna country.
	 */
	public function set_klarna_country() {
		if ( ! method_exists( 'WC_Customer', 'get_billing_country' ) ) {
				return;
		}
		if ( WC()->customer === null ) {
			return;
		}
		$this->klarna_country = apply_filters( 'wc_klarna_payments_country', WC()->customer->get_billing_country() );
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
	 * Sets the environment.
	 */
	public function set_environment() {
		$env_string = 'US' === $this->klarna_country ? '-na' : '';
		if ( $this->testmode ) {
			$this->environment = 'https://api' . $env_string . '.playground.klarna.com/';
		} else {
			$this->environment = 'https://api' . $env_string . '.klarna.com/';
		}
	}

	/**
	 * Calculates the auth needed for the different requests.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		return 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) );
	}
}
