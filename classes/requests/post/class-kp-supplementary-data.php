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
class KP_Supplementary_Data extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		// TODO: Send the API key in the Authorization header.
		$this->log_title      = 'Send supplementary data';
		$this->request_filter = 'wc_klarna_payments_supplementary_data_args';
		$this->endpoint       = 'payments/v2/supplementary-data';

		add_filter(
			'wc_klarna_payments_supplementary_data_args',
			function( $request ) {
				$request['headers']['Authorization'] = 'sk_test_51GreKDI5H4ar61xXSYn6UMBuvxLH4HnWzHktoZEEsRjy2Rx3n3gMZHsKK0TFav7WpXHDX2Vixefv0pA3LFLPRYZR00vrZOoEzN';

				return $request;
			}
		);
	}

	/**
	 * Adds the confirmation URL to the request body for Place order calls.
	 *
	 * @return array
	 */
	public function get_body() {
		$body = parent::get_body();

		$merchant_reference = WC()->session->get( 'kp_supplementary_data_id' );
		if ( empty( $merchant_reference ) ) {
			$merchant_reference[] = kp_generate_unique_id();
			WC()->session->set( 'kp_supplementary_data_id', $merchant_reference );
		}

		$pending_order_id = WC()->session->get( 'order_awaiting_payment' );
		if ( $pending_order_id ) {
			$pending_order = wc_get_order( $pending_order_id );
			if ( strpos( $pending_order->get_payment_method(), 'klarna' ) ) {
				$mollie = $pending_order->get_meta( '_mollie_order_id' );
				if ( ! empty( $mollie ) ) {
					$merchant_reference[] = $mollie;
				}
			}
		}

		if ( isset( $this->arguments['order_number'], $this->arguments['transaction_id'] ) ) {
			$order_id           = $this->arguments['order_number'];
			$transaction_id     = $this->arguments['transaction_id'];
			$order              = wc_get_order( $order_id );
			$merchant_reference = array_merge( $merchant_reference, array( $order->get_order_number(), $transaction_id ) );
		}

		$allowed_keys = array(
			'name',
			'product_url',
			'quantity',
			'total_amount',
			'total_tax_amount',
			'unit_price',
			'product_identifier',
		);

		foreach ( $body['order_lines'] as $index => $order_line ) {
			$body['order_lines'][ $index ]['product_reference'] = $body['order_lines'][ $index ]['reference'];
			foreach ( $order_line as $key => $value ) {
				if ( ! in_array( $key, $allowed_keys, true ) ) {
					unset( $body['order_lines'][ $index ][ $key ] );
				}
			}
		}

		$body = array(
			'merchant_references' => array( $merchant_reference ),
			'content_type'        => 'vnd.klarna.supplementary-data.v1',
			'content'             => array(
				'order_lines' => $body['order_lines'],
			),
		);

		return $body;
	}
}
