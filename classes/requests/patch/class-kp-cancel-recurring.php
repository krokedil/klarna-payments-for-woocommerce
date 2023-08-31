<?php
/**
 * Cancel KP recurring order.
 *
 * https://docs.klarna.com/klarna-payments/other-actions/cancel-a-customer-token/
 *
 * @package WC_Klarna_Payments/Classes/Requests/PATCH
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Create KP recurring order.
 */
class KP_Cancel_Recurring extends KP_Requests_Patch {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Cancel recurring order';
		$this->request_filter = 'wc_klarna_payments_cancel_recurring_order_args';
		$this->endpoint       = "/customer-token/v1/tokens/{$arguments['recurring_token']}/status";
	}


	/**
	 * Gets the request body.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_body() {
		return array(
			'status' => 'cancelled',
		);
	}
}
