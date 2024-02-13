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

		// TODO: Store the ID to 'kp_session_data'.
		$merchant_reference = WC()->session->get( 'kp_supplementary_data_id' );
		if ( empty( $merchant_reference ) ) {
			$merchant_reference = kp_generate_unique_id();
			WC()->session->set( 'kp_supplementary_data_id', $merchant_reference );
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
