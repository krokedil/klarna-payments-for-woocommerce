<?php
namespace Krokedil\Klarna\OrderManagement\Request\Post;

use Krokedil\Klarna\OrderManagement\KlarnaOrderManagement;
use Krokedil\Klarna\OrderManagement\OrderLines;
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
	 * @var KlarnaOrderManagement
	 */
	protected $order_management;

	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
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

			$lines_processor = new OrderLines( $this->order_id, 'capture' );
			$order_lines     = $lines_processor->order_lines();

			if ( isset( $order_lines ) && ! empty( $order_lines ) ) {
				$data = array_merge( $order_lines, $data );
			}
		}

		return apply_filters( 'kom_order_capture_args', $data, $this->order_id );
	}
}
