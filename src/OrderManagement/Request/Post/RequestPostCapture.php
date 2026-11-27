<?php
namespace Krokedil\Klarna\OrderManagement\Request\Post;

use Krokedil\Klarna\OrderManagement;
use Krokedil\Klarna\OrderManagement\Request\RequestPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for order capture
 */
class RequestPostCapture extends RequestPost {
	/**
	 * The order management instance.
	 *
	 * @var OrderManagement
	 */
	protected $order_management;

	/**
	 * The captured amount sent to Klarna, in minor units. Null until the body is built.
	 *
	 * @var int|null
	 */
	protected $captured_amount = null;

	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management The order management instance.
	 * @param array           $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->log_title        = 'Capture Klarna order';
		$this->order_management = $order_management;
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		// If force full capture is enabled, set to true.
		$settings                 = $this->order_management->settings->get_settings( $this->order_id );
		$force_capture_full_order = ( isset( $settings['kom_force_full_capture'] ) && 'yes' === $settings['kom_force_full_capture'] ) ? true : false;
		$order                    = wc_get_order( $this->order_id );

		// If force capture is enabled, send the full remaining authorized amount.
		$data = array(
			'captured_amount' => ( $force_capture_full_order ) ? $this->klarna_order->remaining_authorized_amount : round( $order->get_total() * 100, 0 ),
		);

		// Don't add order lines if we are forcing a full order capture.
		if ( ! $force_capture_full_order ) {
			$customer_type = $settings['customer_type'] ?? 'b2c';
			$kp_order_data = new \KP_Order_Data( $customer_type, $this->order_id );
			$order_lines   = $kp_order_data->get_klarna_order_lines_array();
			if ( isset( $order_lines ) && ! empty( $order_lines ) ) {
				$data['order_lines'] = $order_lines;
			}
		}

		/**
		 * Filters the request body sent to Klarna when capturing an order.
		 *
		 * @link https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-order-capture-request
		 * @param array $data The capture request body, including the captured amount and order lines.
		 * @param int   $order_id The WooCommerce order ID.
		 */
		$data = apply_filters( 'kom_order_capture_args', $data, $this->order_id );

		// Read back after the filter, so the order note quotes what Klarna was actually sent.
		$this->captured_amount = isset( $data['captured_amount'] ) && is_numeric( $data['captured_amount'] ) ? (int) round( (float) $data['captured_amount'] ) : null;

		return $data;
	}

	/**
	 * Get the captured amount sent to Klarna.
	 *
	 * @return int|null The amount in minor units, or null if the request has not been built or sent no amount.
	 */
	public function get_captured_amount() {
		return $this->captured_amount;
	}
}
