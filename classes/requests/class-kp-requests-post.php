<?php
/**
 * Base class for all POST requests.
 *
 * @package WC_Klarna_Payments/Classes/Request
 */

defined( 'ABSPATH' ) || exit;

use Krokedil\WooCommerce\Cart\Cart;
use Krokedil\WooCommerce\Order\Order;

/**
 *  The main class for POST requests.
 */
abstract class KP_Requests_Post extends KP_Requests {

	/**
	 * KP_Requests_Post constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'POST';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		$body = $this->get_body();

		return apply_filters(
			$this->request_filter,
			array(
				'headers'    => $this->get_request_headers(),
				'user-agent' => $this->get_user_agent(),
				'method'     => $this->method,
				'timeout'    => apply_filters( 'wc_kp_request_timeout', 10 ),
				'body'       => wp_json_encode( apply_filters( 'kp_wc_api_request_args', $body ) ),
			)
		);
	}

	/**
	 * Returns the request helper for the request based on if we have a order id passed or not.
	 *
	 * @return \Krokedil\WooCommerce\OrderData
	 */
	public function get_order_data() {
		$config = array(
			'slug'         => 'kp',
			'price_format' => 'minor',
		);

		if ( $this->arguments['order_id'] ?? false && ! empty( $this->arguments['order_id'] ) ) {
			$order = wc_get_order( $this->arguments['order_id'] );
			return new Order( $order, $config );
		} else {
			return new Cart( WC()->cart, $config );
		}
	}

	/**
	 * Returns a formated Klarna order object.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_id      = $this->arguments['order_id'] ?? null;
		$customer_type = $this->arguments['customer_type'] ?? get_option( 'woocommerce_klarna_payments_settings', array( 'customer_type' => 'b2c' ) )['customer_type'];
		$order_data    = new KP_Order_Data( $customer_type, $order_id );

		return apply_filters(
			'kp_wc_api_request_args',
			$order_data->get_klarna_order_object( $this->iframe_options )
		);
	}
}
