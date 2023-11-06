<?php
use Krokedil\WooCommerce\Order\Order;

/**
 * Upsell the Klarna order from the order management API.
 *
 * @package WC_Klarna_Payments/Classes/Request/Patch
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Upsell_Order class.
 */
class KP_Upsell_Order extends KP_Requests_Patch {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$klarna_order_id = $arguments['klarna_order_id'];

		$this->log_title      = 'Upsell order';
		$this->request_filter = 'wc_klarna_payments_upsell_order_args';
		$this->endpoint       = "/ordermanagement/v1/orders/{$klarna_order_id}/authorization";
	}

	/**
	 * Returns a formatted Klarna order object.
	 *
	 * @return array
	 */
	public function get_body() {
		$order_id   = $this->arguments['order_id'];
		$order_data = new KP_Order_Data( '', $order_id );

		$order_lines = $order_data->get_klarna_order_lines_array();
		$order_total = $order_data->order_data->get_total();

		return array(
			'order_lines'  => empty( $order_lines ) ? null : $order_lines, // Null the values if they are empty force an error.
			'order_amount' => 0 === $order_total ? null : $order_total, // Null the values if they are empty force an error.
			'description'  => __( 'Upsell from thankyou page', 'klarna-upsell-for-woocommerce' ),
		);
	}
}
