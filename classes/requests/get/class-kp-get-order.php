<?php
/**
 * Get the Klarna order from the order management API.
 *
 * @package WC_Klarna_Payments/Classes/Request/Get
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Get_Order class.
 */
class KP_Get_Order extends KP_Requests_Get {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Get Klarna order';
		$this->request_filter = 'wc_klarna_payments_get_order_args';
		$klarna_order_id      = $this->arguments['klarna_order_id'];
		$this->endpoint       = "ordermanagement/v1/orders/{$klarna_order_id}";
	}
}
