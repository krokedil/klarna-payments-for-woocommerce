<?php
/**
 * Update Session request class.
 *
 * @package WC_Klarna_Payments/Classes/Post/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update Session request class.
 */
class KP_Create_HPP extends KP_Requests {
	/**
	 * Makes the request.
	 *
	 * @param string $session_id The Klarna Payment session id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $session_id, $order_id ) {
		$request_url  = $this->environment . 'hpp/v1/sessions';
		$request_args = apply_filters( 'wc_klarna_payments_create_hpp_args', $this->get_request_args( $session_id, $order_id ) );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log request.
		$log = KP_Logger::format_log( $session_id, 'POST', 'KP Create HPP', $request_args, $response, $code, $request_url );
		KP_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $session_id The Klarna Payment session id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_request_args( $session_id, $order_id ) {
		return array(
			'headers'    => array(
				'Authorization' => $this->calculate_auth(),
				'Content-Type'  => 'application/json',
			),
			'method'     => 'POST',
			'user-agent' => $this->user_agent,
			'body'       => $this->get_request_body( $session_id, $order_id ),
			'timeout'    => 10,
		);
	}

	/**
	 * Gets the request body for the API call.
	 *
	 * @param string $session_id The Klarna Payment session id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return string
	 */
	public function get_request_body( $session_id, $order_id ) {
		$order = wc_get_order( $order_id );

		$success_url = add_query_arg(
			array(
				'sid'                 => '{{session_id}}',
				'authorization_token' => '{{authorization_token}}',
			),
			$order->get_checkout_order_received_url()
		);

		return wp_json_encode(
			array(
				'payment_session_url' => $this->environment . 'payments/v1/sessions/' . $session_id,
				'merchant_urls'       => array(
					'success' => $success_url,
					'cancel'  => wc_get_checkout_url(),
					'back'    => wc_get_checkout_url(),
					'failure' => wc_get_checkout_url(),
					'error'   => wc_get_checkout_url(),
				),
			)
		);
	}
}
