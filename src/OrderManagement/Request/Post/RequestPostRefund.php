<?php
namespace Krokedil\Klarna\OrderManagement\Request\Post;

use Krokedil\Klarna\OrderManagement\Request\RequestPost;
use Krokedil\Klarna\OrderManagement\KlarnaOrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for order refund
 */
class RequestPostRefund extends RequestPost {

	/**
	 * The Refund Reason
	 *
	 * @var string
	 */
	protected $refund_reason;

	/**
	 * The Refund Amount
	 *
	 * @var integer
	 */
	protected $refund_amount;

	/**
	 * The Return Fee
	 *
	 * @var array
	 */
	protected $return_fee;

	/**
	 * The Refund ID
	 *
	 * @var string
	 */
	protected $refund_id;

	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->log_title     = 'Refund Klarna order';
		$this->refund_reason = $arguments['refund_reason'];
		$this->refund_amount = $arguments['refund_amount'];
		$this->return_fee    = $arguments['return_fee'] ?? array();
		$this->refund_id     = $arguments['refund_id'] ?? '';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$data = array(
			'refunded_amount' => round( $this->refund_amount * 100 ),
			'description'     => $this->refund_reason,
		);

		// Get the original order number.
		$order        = wc_get_order( $this->order_id );
		$order_number = empty( $order ) ? $this->order_id : $order->get_order_number();

		// Add the order number and refund id if available.
		if ( ! empty( $this->refund_id ) ) {
			$data['reference'] = "{$order_number}|{$this->refund_id}";
		}

		$kp_order_data = new \KP_Order_Data( 'b2c', $this->order_id );
		$order_lines   = $kp_order_data->get_klarna_order_lines_array();
		if ( isset( $order_lines ) && ! empty( $order_lines ) ) {
			$data['order_lines'] = $order_lines;
		}

		return $data;
	}

	/**
	 * Returns the id of the refunded order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string
	 */
	public function get_refunded_order_id( $order_id ) {
		$order = wc_get_order( $order_id );

		/* Always retrieve the most recent (current) refund (index 0). */
		return $order->get_refunds()[0]->get_id();
	}
}
