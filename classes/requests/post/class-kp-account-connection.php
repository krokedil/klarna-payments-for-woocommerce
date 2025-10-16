<?php
/**
 * Class for the request to connect a Klarna account.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

class KP_Account_Connection extends KP_Requests_Post {
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
		parent::__construct( $arguments );
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {

		if ( 'test' === $this->mode ) {
			return 'https://api-global.test.klarna.com/v2/plugins/';
		}

		return 'https:// api-global.klarna.com/v2/plugins/';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$code_verifier  = self::generate_code_verifier();
		$code_challenge = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );

		return array(
			'plugin'        => 'Klarna Payments for WooCommerce',
			'version'       => WC_KLARNA_PAYMENTS_VERSION,
			'platform'      => 'WooCommerce',
			'redirectUrl'   => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=klarna_payments' ),
			'codeChallenge' => $code_challenge,
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

	/**
	 * Generate a code verifier.
	 *
	 * @return string
	 */
	public static function generate_code_verifier() {
		$length        = 128;
		$possibleChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
		$maxIndex      = strlen( $possibleChars ) - 1;
		$codeVerifier  = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$codeVerifier .= $possibleChars[ random_int( 0, $maxIndex ) ];
		}

		set_transient( 'kp_account_code_verifier', $codeVerifier, 60 * 5 );

		return $codeVerifier;
	}
}
