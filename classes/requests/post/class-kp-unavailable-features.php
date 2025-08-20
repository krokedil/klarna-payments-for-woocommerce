<?php
/**
 * Class for the request to create a Klarna feature configuration.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Unavailable_Features class.
 */
class KP_Unavailable_Features extends KP_Requests_Post {
	/**
	 * The request ID.
	 *
	 * @var string
	 */
	private $request_id;

	/**
	 * The mode.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * The API password.
	 *
	 * @var string
	 */
	private $api_password;

	/**
	 * Constructor.
	 *
	 * @param array $arguments The request arguments.
	 *
	 * @return void
	 */
	public function __construct( $arguments ) {
		$this->api_password = $arguments['api_password'];
		$this->mode         = $arguments['mode'];
		$this->request_id   = $arguments['request_id'];
		parent::__construct( $arguments );
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {

		if ( 'test' === $this->mode ) {
			return "https://api-global.test.klarna.com/v2/plugins/{$this->request_id}/features";
		}

		return "https://api-global.klarna.com/v2/plugins/{$this->request_id}/features";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array(
			'installation_data' => array(
				'platform_data'      => array(
					'platform_name'        => 'woocommerce',
					'platform_version'     => WC()->version,
					'platform_plugin_name' => 'woocommerce_klarna_plugin',
				),
				'klarna_plugin_data' => array(
					'plugin_identifier' => 'klarna:plugins:woocommerce:klarna-plugin',
					'plugin_version'    => WC_KLARNA_PAYMENTS_VERSION,
				),
				'store_data'         => array(
					'store_urls' => array(
						get_site_url(),
					),
				),
			),
		);
	}

	/**
	 * Calculates the auth header for the request.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		return 'basic ' . $this->api_password;
	}

	/**
	 * Gets the error message from the Klarna payments response.
	 *
	 * @param array $response
	 * @return WP_Error
	 */
	public function get_error_message( $response ) {
		$error_message = '';
		// Get the error messages.
		if ( null !== json_decode( $response['body'], true ) ) {
			$error_message = $response['body'];
		}
		$code          = wp_remote_retrieve_response_code( $response );
		$error_message = empty( $error_message ) ? $response['response']['message'] : $error_message;
		return new WP_Error( $code, $error_message );
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param array|\WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request URL.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url ) {
		$this->arguments['api_password'] = '[REDACTED]';
		parent::log_response( $response, $request_args, $request_url );
	}
}
