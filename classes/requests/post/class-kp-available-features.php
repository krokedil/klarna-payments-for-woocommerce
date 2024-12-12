<?php
/**
 * Class for the request to create a Klarna feature configuration.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Available_Features class.
 */
class KP_Available_Features {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key API Key for authentication.
	 */
	public function __construct( $api_key ) {
		$this->endpoint = '';
		$this->api_key  = $api_key;
	}

	/**
	 * Send the request.
	 *
	 * @return array|WP_Error The response or WP_Error on failure.
	 */
	public function request() {
		$args = array(
			'headers' => $this->get_headers(),
			'body'    => wp_json_encode( $this->get_body() ),
			'method'  => 'POST',
		);

		$response = wp_remote_request( $this->endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	private function get_body() {
		return array(
			'installation_data' => array(
				'klarna_plugin_data' => array(
					'plugin_identifier' => '',
					'plugin_version'    => '',
				),
			),
		);
	}

	/**
	 * Get the headers for the request.
	 *
	 * @return array
	 */
	private function get_headers() {
		return array(
			'Authorization' => '',
			'Content-Type'  => 'application/json',
		);
	}
}
