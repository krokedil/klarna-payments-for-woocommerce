<?php
/**
 * Class for the request to update a session.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Update_Session class.
 */
class KP_Update_Session extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Update session';
		$this->request_filter = 'wc_klarna_payments_update_session_args';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$session_id = $this->arguments['session_id'];
		return $this->environment . "payments/v1/sessions/${session_id}";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$helper = $this->get_helper();
		return array(
			'purchase_country'  => kp_get_klarna_country(),
			'purchase_currency' => get_woocommerce_currency(),
			'locale'            => kp_get_locale(),
			'order_amount'      => $helper::get_kp_order_amount(),
			'order_tax_amount'  => $helper::get_kp_order_tax_amount(),
			'order_lines'       => $helper::get_kp_order_lines(),
			'customer'          => get_klarna_customer( $this->settings['customer_type'] ),
			'options'           => $this->iframe_options->get_kp_color_options(),
			'merchant_urls'     => array(
				'authorization' => home_url( '/wc-api/KP_WC_AUTHORIZATION' ),
			),
		);
	}
}
