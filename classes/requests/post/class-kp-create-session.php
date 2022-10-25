<?php
/**
 * Class for the request to create a session.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Create_Session class.
 */
class KP_Create_Session extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create session';
		$this->request_filter = 'wc_klarna_payments_create_session_args';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->environment . 'payments/v1/sessions';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array(
			'purchase_country'  => kp_get_klarna_country(),
			'purchase_currency' => get_woocommerce_currency(),
			'locale'            => $this->get_klarna_locale(),
			'order_amount'      => '', // $this->order_lines['order_amount'], - TODO
			'order_tax_amount'  => '', // $this->order_lines['order_tax_amount'], - TODO
			'order_lines'       => '', // $this->order_lines['order_lines'], - TODO
			'customer'          => get_klarna_customer( $this->kp_settings['customer_type'] ),
			'options'           => $this->iframe_options->get_kp_color_options(),
			'merchant_urls'     => array(
				'authorization' => home_url( '/wc-api/KP_WC_AUTHORIZATION' ),
			),
		);
	}
}
