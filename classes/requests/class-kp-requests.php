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
	 * @param int $order_id The WooCommerce order id.
	 */
	public function __construct( $order_id = false ) {
		$this->order_id = $order_id;
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
		$this->kp_settings    = get_option( 'woocommerce_klarna_payments_settings' );
		$this->iframe_options = new KP_IFrame( $this->kp_settings );
		$this->testmode       = ( 'yes' !== $this->kp_settings['testmode'] ) ? false : true;
		$this->user_agent     = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - WooCommerce: ' . WC()->version . ' - KP:' . WC_KLARNA_PAYMENTS_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
		$order_lines_class    = new KP_Order_Lines( kp_get_klarna_country() );
		$this->order_lines    = $order_lines_class->order_lines( $this->order_id );
		$this->set_credentials();
		$this->set_environment();
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
		// If response is a WP_Error, then return response.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check the status code.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = ' ';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				foreach ( json_decode( $response['body'], true )['error_messages'] as $error ) {
					$error_message = $error_message . ' ' . $error;
				}
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $response['response']['message'] . $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Sets Klarna credentials.
	 */
	public function set_credentials() {
		if ( $this->testmode ) {
			$this->merchant_id   = $this->kp_settings[ 'test_merchant_id_' . strtolower( kp_get_klarna_country() ) ];
			$this->shared_secret = $this->kp_settings[ 'test_shared_secret_' . strtolower( kp_get_klarna_country() ) ];
		} else {
			$this->merchant_id   = $this->kp_settings[ 'merchant_id_' . strtolower( kp_get_klarna_country() ) ];
			$this->shared_secret = $this->kp_settings[ 'shared_secret_' . strtolower( kp_get_klarna_country() ) ];
		}
	}

	/**
	 * Sets the environment.
	 */
	public function set_environment() {
		switch ( kp_get_klarna_country() ) {
			case 'US':
				$env_string = '-na';
				break;
			case 'CA':
				$env_string = '-na';
				break;
			case 'AU':
				$env_string = '-oc';
				break;
			case 'NZ':
				$env_string = '-oc';
				break;
			default:
				$env_string = '';
				break;
		}
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
		return 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth headers.
	}

	/**
	 * Formats the locale to match Klarnas api.
	 *
	 * @return string
	 */
	public function get_klarna_locale() {
		$locale = get_locale();
		// Format exceptions. For example. Finish is returned as fi from WordPress, needs to be formated to fi_fi.
		switch ( $locale ) {
			case 'fi':
				$locale = 'fi_fi';
				break;
			default:
				break;
		}
		return substr( str_replace( '_', '-', $locale ), 0, 5 );
	}
}
