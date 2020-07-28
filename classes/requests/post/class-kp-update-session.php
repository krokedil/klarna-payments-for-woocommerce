<?php
/**
 * Update Session request class.
 *
 * @package Payer_B2B/Classes/Put/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update Session request class.
 */
class KP_Update_Session extends KP_Requests {
	/**
	 * Makes the request.
	 *
	 * @return array
	 */
	public function request() {
		$request_url  = $this->environment . 'payments/v1/sessions/' . WC()->session->get( 'klarna_payments_session_id' );
		$request_args = apply_filters( 'wc_klarna_payments_update_session_args', $this->get_request_args() );

		// Check if we need to update.
		if ( WC()->session->get( 'kp_update_md5' ) && WC()->session->get( 'kp_update_md5' ) === md5( wp_json_encode( $request_args ) ) ) {
			return false;
		}
		WC()->session->set( 'kp_update_md5', md5( wp_json_encode( $request_args ) ) );

		$response   = wp_remote_request( $request_url, $request_args );
		$code       = wp_remote_retrieve_response_code( $response );
		$body       = json_decode( wp_remote_retrieve_body( $response ), true );
		$session_id = isset( $body['session_id'] ) ? $body['session_id'] : '';

		// Log request.
		$log = KP_Logger::format_log( $session_id, 'POST', 'KP Update Session', $request_args, $response, $code );
		KP_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers'    => array(
				'Authorization' => $this->calculate_auth(),
				'Content-Type'  => 'application/json',
			),
			'method'     => 'POST',
			'user-agent' => $this->user_agent,
			'body'       => $this->get_request_body(),
			'timeout'    => 10,
		);
	}

	/**
	 * Gets the request body for the API call.
	 *
	 * @return string
	 */
	public function get_request_body() {
		return wp_json_encode(
			array(
				'purchase_country'  => kp_get_klarna_country(),
				'purchase_currency' => get_woocommerce_currency(),
				'locale'            => $this->get_klarna_locale(),
				'order_amount'      => $this->order_lines['order_amount'],
				'order_tax_amount'  => $this->order_lines['order_tax_amount'],
				'order_lines'       => $this->order_lines['order_lines'],
				'customer'          => get_klarna_customer( $this->kp_settings['customer_type'] ),
				'options'           => $this->iframe_options->get_kp_color_options(),
			)
		);
	}
}
