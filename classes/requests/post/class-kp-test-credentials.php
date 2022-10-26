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
	}

	/**
	 * Overrides the default envinment setter, to set from the passed arguments instead.
	 *
	 * @return void
	 */
	public function set_environment() {
		$region     = $this->country_params['endpoint'] ?? ''; // Get the region from the country parameters, blank for EU.
		$playground = 'yes' == $this->arguments['testmode'] ? 'playground' : ''; // If testmode is enabled, add playground to the subdomain.
		$subdomain  = "api${region}.${playground}"; // Combine the string to one subdomain.

		$this->environment = "https://${subdomain}.klarna.com/"; // Return the full base url for the api.
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
			'purchase_country'  => strtoupper( $this->arguments['country'] ),
			'purchase_currency' => $this->country_params['currency'],
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
