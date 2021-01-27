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
class KP_Place_Order extends KP_Requests {
	/**
	 * Makes the request.
	 *
	 * @param string $auth_token Auth token from Klarna Payments.
	 * @return array
	 */
	public function request( $auth_token ) {
		$request_url  = $this->environment . 'payments/v1/authorizations/' . $auth_token . '/order';
		$request_args = apply_filters( 'wc_klarna_payments_place_order_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );
		$body         = json_decode( wp_remote_retrieve_body( $response ), true );
		$order_id     = isset( $body['order_id'] ) ? $body['order_id'] : '';

		// Log request.
		$log = KP_Logger::format_log( $order_id, 'POST', 'KP Place Order', $request_args, $response, $code );
		KP_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		update_post_meta( $this->order_id, '_wc_klarna_environment', $this->testmode ? 'test' : 'live' );
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
		$order = wc_get_order( $this->order_id );
		return wp_json_encode(
			apply_filters(
				'kp_wc_api_request_args',
				array(
					'purchase_country'    => $this->country,
					'purchase_currency'   => $order->get_currency(),
					'locale'              => $this->get_klarna_locale(),
					'billing_address'     => KP_Customer_Data::get_billing_address( $this->order_id, $this->kp_settings['customer_type'] ),
					'shipping_address'    => KP_Customer_Data::get_shipping_address( $this->order_id, $this->kp_settings['customer_type'] ),
					'order_amount'        => $this->order_lines['order_amount'],
					'order_tax_amount'    => $this->order_lines['order_tax_amount'],
					'order_lines'         => $this->order_lines['order_lines'],
					'customer'            => get_klarna_customer( $this->kp_settings['customer_type'] ),
					'merchant_reference1' => $order->get_order_number(),
					'merchant_urls'       => array(
						'confirmation' => $order->get_checkout_order_received_url(),
						'notification' => get_home_url() . '/wc-api/WC_Gateway_Klarna_Payments/?order_id=' . $this->order_id,
					),
				),
				$order
			)
		);
	}
}
