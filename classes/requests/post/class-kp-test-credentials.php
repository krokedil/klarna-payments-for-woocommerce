<?php
/**
 * Class for the testing credentials requests.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Test_Credentials class.
 */
class KP_Test_Credentials extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Test credentials';
		$this->request_filter = 'wc_klarna_payments_create_session_args';
		$this->endpoint       = 'payments/v1/sessions';
	}

	/**
	 * Overrides the default set_credentials method to use the once passed from arguments.
	 *
	 * @return void
	 */
	public function set_credentials() {
		$this->merchant_id   = $this->arguments['username'];
		$this->shared_secret = $this->arguments['password'];
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$country_data = KP_Form_Fields::$kp_form_auto_countries[ strtolower( $this->arguments['country'] ?? '' ) ] ?? null;

		return array(
			'purchase_country'  => strtoupper( $this->arguments['country'] ),
			'purchase_currency' => $country_data['currency'],
			'locale'            => 'en-US',
			'order_amount'      => 100,
			'order_lines'       => array(
				array(
					'name'         => 'Test credentials Product',
					'quantity'     => 1,
					'total_amount' => 100,
					'unit_price'   => 100,
				),
			),
		);
	}
}
