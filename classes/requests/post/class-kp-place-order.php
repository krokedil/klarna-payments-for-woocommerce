<?php
/**
 * Class for the request to place a klarna order.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Place_Order class.
 */
class KP_Place_Order extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Place order';
		$this->request_filter = 'wc_klarna_payments_place_order_args';
		$auth_token           = $this->arguments['auth_token'];
		$this->endpoint       = "payments/v1/authorizations/{$auth_token}/order";
	}

	/**
	 * Adds the confirmation URL to the request body for Place order calls.
	 *
	 * @return array
	 */
	public function get_body() {
		$body = parent::get_body();

		$order                                 = wc_get_order( $this->arguments['order_id'] );
		$body['merchant_reference1'] 		   = $order->get_order_number();
		$body['merchant_reference2'] 		   = $order->get_id();
		$body['merchant_urls']['confirmation'] = $order->get_checkout_order_received_url();

		return $body;
	}
}
