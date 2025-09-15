<?php
/**
 * Class for the request to create notifications in Klarna that will communicate with the store.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Create_Notifications class.
 */
class KP_Create_Notifications extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create Notifications';
		$this->request_filter = 'wc_klarna_payments_create_notifications_args';
		$this->endpoint       = 'v2/notification/webhooks';
	}

	/**
	 * Sets the environment.
	 *
	 * @param string $country The country code.
	 * @param array  $settings The settings array.
	 */
	protected function get_base_url( $country, $settings ) {
		$testmode     = wc_string_to_bool( $settings['testmode'] ?? 'no' ); // Get the testmode setting.
		$environment  = $testmode ? 'test' : '';

		return "https://api-global.{$environment}.klarna.com/";
	}

	/**
	 * Calculates the auth header for the request.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		return 'basic ' . $this->shared_secret;
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array(
			'url'            => $this->arguments['url'],
			'event_types'    => $this->arguments['event_types'],
			'event_version'  => $this->arguments['event_version'],
			'signing_key_id' => $this->arguments['signing_key_id'],
			'status'         => 'ENABLED',
		);
	}
}
