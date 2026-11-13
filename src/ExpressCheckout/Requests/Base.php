<?php

namespace Krokedil\Klarna\ExpressCheckout\Requests;

use Krokedil\Klarna\Utilities\IntegrationMetadataUtility;

defined( 'ABSPATH' ) || exit;

/**
 * Base request class for KEC API requests.
 */
abstract class Base extends \KP_Requests {
	/**
	 * Get the base URL for the request.
	 *
	 * @param string $country  The country code.
	 * @param array  $settings The settings array.
	 *
	 * @return string
	 */
	protected function get_base_url( $country, $settings ) {
		$testmode    = wc_string_to_bool( $settings['testmode'] ?? 'no' );
		$environment = $testmode ? 'test' : '';

		return "https://api-global.{$environment}.klarna.com/";
	}

	/**
	 * Calculate the auth header for the request.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		return 'basic ' . $this->shared_secret;
	}

	/**
	 * Get the headers for the request.
	 *
	 * The integration metadata is sent without a session reference, since the KP session is unrelated to KEC.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		$headers = parent::get_request_headers();

		$headers[ IntegrationMetadataUtility::HEADER_NAME ] = IntegrationMetadataUtility::get_header_value();

		return $headers;
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	protected function get_request_body() {
		return array();
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		$body = $this->get_request_body();

		$args = array(
			'method'     => $this->method,
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		return $args;
	}
}
