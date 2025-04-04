<?php
class KP_Payment_Processor_KP extends KP_Payment_Processor {
	/**
	 * Class constructor.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function __construct( $order ) {
		parent::__construct( $order );

		// Load any session data that we might have. Pass null instead of order identifier to load session from WC()->session.
		KP_WC()->session->set_session_data( null );
	}

	/**
	 * Get the return for a successful Klarna Payments session.
	 *
	 * @return array The return data.
	 */
	public function get_success_return() {
		$result = parent::get_success_return();

		$order_data = new KP_Order_Data( $this->get_customer_type() );
		$customer   = $order_data->get_klarna_customer_object();

		// Set the customer data in result.
		$result['addresses'] = array(
			'billing'  => $customer['billing'],
			'shipping' => $customer['shipping'],
		);

		return $result;
	}
}