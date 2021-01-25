<?php
/**
 * Class for handling callbacks.
 *
 * @package WC_Klarna_Payments/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handling callbacks.
 */
class KP_Callbacks {
	public function __construct() {
		add_action( 'woocommerce_api_kp_wc_authorization', array( $this, 'authorization_cb' ) );
		add_action( 'kp_wc_authorization', array( $this, 'kp_wc_authorization' ), 10, 2 );
	}

	/**
	 * Process the authorization callback from Klarna.
	 * 
	 * @return void
	 */
	public function authorization_cb() {
		$data = json_decode( file_get_contents('php://input'), true );

		// If the data is empty, return.
		if ( empty( $data ) ) {
			return;
		}

		wp_schedule_single_event( time() + 10, 'kp_wc_authorization', array( $data ) );
	}

	/**
	 * Handle the authorization callback from Klarna. Maybe complete a order.
	 * 
	 * @return void
	 */
	public function kp_wc_authorization( $data ) {
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_kp_session_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'meta_value'  => $data['session_id'], // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'date_query' => array(
				array(
					'after'     => '5 minute ago',
					'column' => 'post_date',
				),
			),
		);

		$orders = get_posts( $query_args );

		if ( empty( $orders ) ) {
			return;
		}

		$auth_token = $data['authorization_token'];
		$order_id   = $orders[0];
		$order      = wc_get_order( $order_id );
		$country    = $order->get_billing_country();

		$request  = new KP_Place_Order( $order_id, $country );
		$response = $request->request( $auth_token );
		if ( is_wp_error( $response ) ) {
			// add order note here.
			return;
		}

		$fraud_status = $response['fraud_status'];
		switch ( $fraud_status ) {
			case 'ACCEPTED':
				// Add an order note here.
				kp_process_accepted( $order, $response );
				break;
			case 'PENDING':
			case 'REJECTED':
			default:
				// Add an order note here.
				kp_process_rejected( $order, $response );
				break;
		}

	}
}
new KP_Callbacks();
