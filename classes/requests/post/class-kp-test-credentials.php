<?php
/**
 * Create KCO Order
 *
 * @package Klarna_Checkout/Classes/Request/Checkout/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create KP Order
 */
class KP_Test_Credentials {
	/**
	 * Makes the request.
	 *
	 * @param string $username The username to use.
	 * @param string $password The password to use.
	 * @param bool   $testmode If its test mode or not.
	 * @param array  $country The needed country params.
	 * @param string $cc The country code.
	 * @return array
	 */
	public function request( $username, $password, $testmode, $country, $cc ) {
		$request_url       = $this->get_test_endpoint( $testmode, $country['region'] ) . 'payments/v1/sessions';
		$request_args      = apply_filters( 'kp_wc_test_credentials', $this->get_request_args( $username, $password, $country, $cc ) );
		$response          = wp_remote_request( $request_url, $request_args );
		$code              = wp_remote_retrieve_response_code( $response );
		$formated_response = $this->process_response( $response, $request_args, $request_url );
		// Log the request.
		$log = KP_Logger::format_log( null, 'POST', 'KP test credentials', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		KP_Logger::log( $log );
		return $formated_response;
	}

	/**
	 * Gets the request body for the API call.
	 *
	 * @return string
	 * @param array  $country The needed country params.
	 * @param string $cc The country code.
	 */
	public function get_request_body( $country, $cc ) {
		return wp_json_encode(
			array(
				'purchase_country'  => $cc,
				'purchase_currency' => $country['currency'],
				'locale'            => 'en-US',
				'order_amount'      => 100,
				'order_lines'       => array(
					array(
						'name'         => 'Test credentials Product',
						'quantity'     => 1,
						'total_amount' => 100,
						'unit_price'   => 100,
					),
				),
			)
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $username The username to use.
	 * @param string $password The password to use.
	 * @param array  $country The needed country params.
	 * @param string $cc The country code.
	 * @return array
	 */
	protected function get_request_args( $username, $password, $country, $cc ) {
		return array(
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . htmlspecialchars_decode( $password ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth headers.
				'Content-Type'  => 'application/json',
			),
			'method'     => 'POST',
			'body'       => $this->get_request_body( $country, $cc ),
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - WooCommerce: ' . WC()->version . ' - KP:' . WC_KLARNA_PAYMENTS_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil',
			'timeout'    => 10,
		);
	}

	/**
	 * Gets the endpoint for the test.
	 *
	 * @param bool   $testmode If its test mode or not.
	 * @param string $endpoint The endpoint for the request.
	 */
	public function get_test_endpoint( $testmode, $endpoint ) {
		$country_string = '';
		if ( 'eu' !== $endpoint ) {
			$country_string = "-$endpoint";
		}
		$test_string = $testmode ? '.playground' : '';

		return 'https://api' . $country_string . $test_string . '.klarna.com/';
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
		// Check if response is a WP_Error, and return it back if it is.
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		// Check the status code, if its not between 200 and 299 then its an error.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$error_message = wp_json_encode( $response['response'] );
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $error_message, $response['body'] );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
