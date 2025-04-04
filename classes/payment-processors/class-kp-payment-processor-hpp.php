<?php
class KP_Payment_Processor_HPP extends KP_Payment_Processor {
	/**
	 * Class constructor.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function __construct( $order ) {
		// Load any session data from the order.
		KP_WC()->session->set_session_data( $order );

		parent::__construct( $order );
	}

	/**
	 * Get the return for a successful Klarna Payments session.
	 *
	 * @return array The return data.
	 */
	public function get_success_return() {
		// Create a HPP url.
		$hpp = KP_WC()->api->create_hpp( $this->get_klarna_country(), $this->get_session_id(), $this->order->get_id() );

		if ( is_wp_error( $hpp ) ) {
			throw new WP_Exception( __( 'Failed to create a hosted payment page with Klarna. Please try again.', 'klarna-payments-for-woocommerce' ) );
		}

		// Set the customer data in result.
		return array(
			'result'   => 'success',
			'redirect' => $hpp['redirect_url'],
		);
	}
}