<?php
/**
 * Create KP recurring order.
 *
 * https://docs.klarna.com/api/customertoken/#operation/createOrder
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create KP recurring order.
 */
class KP_Create_Recurring extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create recurring order';
		$this->request_filter = 'wc_klarna_payments_create_recurring_order_args';
		$this->endpoint       = "/customer-token/v1/tokens/{$arguments['recurring_token']}/order";
	}


	/**
	 * Gets the request body.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	protected function get_body() {
		$customer_type = $this->settings['customer_type'] ?? 'b2c';
		$order_data    = new KP_Order_Data( $customer_type, $this->arguments['order_id'] );
		$order         = wc_get_order( $this->arguments['order_id'] );
		$klarna_order  = $order_data->get_klarna_order_object( $this->iframe_options );

		$request_body = array(
			'purchase_currency'   => $klarna_order['purchase_currency'],
			'order_amount'        => $klarna_order['order_amount'],
			'order_lines'         => $klarna_order['order_lines'],
			'order_tax_amount'    => $klarna_order['order_tax_amount'],
			'merchant_reference1' => $order->get_order_number(),
			'merchant_reference2' => $order->get_id(),
		);

		return $request_body;
	}
}
