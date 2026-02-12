<?php
namespace Krokedil\Klarna\OrderManagement\Request\Patch;

use Krokedil\Klarna\OrderManagement\Request\RequestPatch;
use Krokedil\Klarna\KlarnaOrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PATCH request class for order line updates.
 */
class RequestPatchUpdate extends RequestPatch {
	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->log_title = 'Update Klarna order lines';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/authorization';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$settings      = $this->order_management->settings->get_settings( $this->order_id );
		$customer_type = $settings['customer_type'] ?? 'b2c';
		$kp_order_data = new \KP_Order_Data( $customer_type, $this->order_id );
		$order_lines   = $kp_order_data->get_klarna_order_lines_array();
		$data          = array( 'order_lines' => $order_lines );
		return apply_filters( 'kom_order_update_args', $data, $this->order_id );
	}
}
