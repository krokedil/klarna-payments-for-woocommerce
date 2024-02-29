<?php
/**
 * Class for sending supplementary data to Klarna.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Supplementary_Data class.
 */
class KP_Send_Supplementary_Data extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Send supplementary data';
		$this->request_filter = 'wc_klarna_payments_send_supplementary_data_args';
		$this->endpoint       = 'payments/v2/supplementary-data';
	}

	/**
	 * Adds the confirmation URL to the request body for Place order calls.
	 *
	 * @return array
	 */
	public function get_body() {
		$body = parent::get_body();

		// Generate a unique ID for identifying this session.
		$merchant_reference = WC()->session->get( KP_Supplementary_Data::$session_key );
		if ( empty( $merchant_reference ) ) {
			$merchant_reference[] = KP_Supplementary_Data::generate_unique_id();
			WC()->session->set( KP_Supplementary_Data::$session_key, $merchant_reference );
		}

		// Check if it is a resumable WC order. This can happen if the customer returns to the checkout page if they don't finalize the purchase.
		$pending_order_id = WC()->session->get( 'order_awaiting_payment' );
		if ( $pending_order_id ) {
			$pending_order = wc_get_order( $pending_order_id );
			if ( strpos( $pending_order->get_payment_method(), 'klarna' ) ) {
				$payment_specific_order_id = $pending_order->get_meta( '_mollie_order_id' );
				if ( empty( $payment_specific_order_id ) ) {
					$payment_specific_order_id = $pending_order->get_meta( '_payment_intent_id' );
				}

				if ( ! empty( $payment_specific_order_id ) ) {
					$merchant_reference[] = $payment_specific_order_id;
				}
			}
		}

		// If a WC order has been created, retrieve the payment order ID.
		if ( isset( $this->arguments['order_number'], $this->arguments['transaction_id'] ) ) {
			$order_id       = $this->arguments['order_number'];
			$transaction_id = $this->arguments['transaction_id'];
			$order          = wc_get_order( $order_id );

			// Retrieve the payment ID from Mollie.
			$payment_specific_order_id = $order->get_meta( '_mollie_order_id' );
			if ( empty( $payment_specific_order_id ) ) {
				// If it is not Mollie, it must be Stripe. We only support these two as of now.
				$payment_specific_order_id = $order->get_meta( '_payment_intent_id' );
			}

			// If $transaction_id is set, we can skip retrieving specific payment order ID. Check if that exists first.
			$transaction_id     = ! empty( $transaction_id ) ? $transaction_id : $payment_specific_order_id;
			$merchant_reference = array_merge( $merchant_reference, array( $order->get_order_number(), $transaction_id ) );
		}

		// Order line fields that Klarna's supplementary data API supports.
		$allowed_keys = array(
			'name',
			'product_url',
			'quantity',
			'total_amount',
			'total_tax_amount',
			'unit_price',
			'product_identifier',
		);

		// Delete the order line fields that are not required. Refer to $allowed_keys.
		foreach ( $body['order_lines'] as $index => $order_line ) {
			$body['order_lines'][ $index ]['product_reference'] = $body['order_lines'][ $index ]['reference'];
			foreach ( $order_line as $key => $value ) {
				if ( ! in_array( $key, $allowed_keys, true ) ) {
					unset( $body['order_lines'][ $index ][ $key ] );
				}
			}
		}

		$body = array(
			'merchant_references' => array_unique( $merchant_reference ),
			'content_type'        => 'vnd.klarna.supplementary-data.v1',
			'content'             => array(
				'order_lines' => $body['order_lines'],
			),
		);

		return $body;
	}
}
