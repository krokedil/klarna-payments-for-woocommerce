<?php
/**
 * Class for the request to create a HPP order.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Create_HPP class.
 */
class KP_Create_HPP extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create HPP';
		$this->request_filter = 'wc_klarna_payments_create_hpp_args';
		$this->endpoint       = 'hpp/v1/sessions';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$base_url    = $this->config['base_url'];
		$session_id  = $this->arguments['session_id'];
		$order       = wc_get_order( $this->arguments['order_id'] );
		$success_url = add_query_arg(
			array(
				'sid'                 => '{{session_id}}',
				'authorization_token' => '{{authorization_token}}',
			),
			$order->get_checkout_order_received_url()
		);

		return array(
			'payment_session_url' => "{$base_url}payments/v1/sessions/$session_id",
			'merchant_urls'       => array(
				'success' => $success_url,
				'cancel'  => wc_get_checkout_url(), // TODO - Handle messages?
				'back'    => wc_get_checkout_url(),
				'failure' => wc_get_checkout_url(), // TODO - Handle messages?
				'error'   => wc_get_checkout_url(), // TODO - Handle messages?
			),
		);
	}
}
