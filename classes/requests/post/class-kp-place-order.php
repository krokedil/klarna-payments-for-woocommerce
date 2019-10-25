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
class KP_Place_Order extends KP_Requests {
	/**
	 * Makes the request.
	 *
	 * @param int    $order_id WooCommerce order id.
	 * @param string $auth_token Auth token from Klarna Payments.
	 * @return array
	 */
	public function request( $order_id, $auth_token ) {
		$request_url  = $this->environment . 'payments/v1/authorizations/' . $auth_token . '/order';
		$request_args = apply_filters( 'wc_klarna_payments_place_order_args', $this->get_request_args( $this->order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		$formated_response = $this->process_response( $response, $request_args, $request_url );

		return $formated_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers'    => array(
				'Authorization' => $this->calculate_auth(),
				'Content-Type'  => 'application/json',
			),
			'method'     => 'POST',
			'user-agent' => $this->user_agent,
			'body'       => $this->get_request_body( $order_id ),
		);
	}

	/**
	 * Gets the request body for the API call.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return string
	 */
	public function get_request_body( $order_id ) {
		$order = wc_get_order( $order_id );
		return wp_json_encode(
			apply_filters(
				'kp_wc_api_request_args',
				array(
					'purchase_country'    => $this->klarna_country,
					'purchase_currency'   => $order->get_currency(),
					'locale'              => get_locale_for_klarna_country(),
					'billing_address'     => '',
					'shipping_address'    => '',
					'order_amount'        => '',
					'order_tax_amount'    => '',
					'order_lines'         => '',
					'customer'            => get_klarna_customer(),
					'merchant_reference1' => $order->get_order_number(),
					'merchant_urls'       => array(
						'confirmation' => $order->get_checkout_order_received_url(),
						'notification' => get_home_url() . '/wc-api/WC_Gateway_Klarna_Payments/?order_id=' . $order_id,
					),
				),
				$order
			)
		);
	}
}
