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
	 * The API username (merchant ID) that old-format API passwords are paired with.
	 *
	 * @var string
	 */
	private $api_username;

	/**
	 * Constructor.
	 *
	 * @param array $arguments The request arguments.
	 *
	 * @return void
	 */
	public function __construct( $arguments ) {
		$this->api_password = $arguments['api_password'];
		$this->api_username = $arguments['api_username'] ?? '';
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
	 * Overrides the default credential resolution.
	 *
	 * This request authenticates with the API password from the arguments, and produces the capability data
	 * the resolver reads, so it must not call the resolver itself.
	 *
	 * @param array $arguments The request arguments.
	 *
	 * @return null
	 */
	protected function resolve_credentials( $arguments ) {
		return null;
	}

	/**
	 * Calculates the auth header for the request.
	 *
	 * New API keys (prefixed klarna_) carry the credential themselves and are sent as is. Older keys must be
	 * paired with the username, like every other request to Klarna, or the plugins API responds with a 401.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		$api_password = htmlspecialchars_decode( (string) $this->api_password, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 );

		if ( 0 === strpos( $api_password, 'klarna_' ) || empty( $this->api_username ) ) {
			return 'basic ' . $api_password;
		}

		return 'Basic ' . base64_encode( $this->api_username . ':' . $api_password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth headers.
	}

	/**
	 * Gets the error message from the Klarna payments response.
	 *
	 * @param array $response The response from the request.
	 *
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
}
