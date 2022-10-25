<?php
/**
 * Main request class
 *
 * @package WC_Klarna_Payments/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
 */
abstract class KP_Requests {

	/**
	 * The request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The request title.
	 *
	 * @var string
	 */
	protected $log_title;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * The Environment to make the requests to (base url)
	 *
	 * @var string
	 */
	protected $environment;

	/**
	 * The Klarna merchant Id, or MID. Used for calculating the request auth.
	 *
	 * @var string
	 */
	protected $merchant_id;

	/**
	 * The Klarna shared api secret. Used for calculating the request auth.
	 *
	 * @var string
	 */
	protected $shared_secret;

	/**
	 * For backwards compatability. The filter to wrap the entire request args in before we return it.
	 *
	 * @var string
	 */
	protected $request_filter;


	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments      = $arguments;
		$this->country_params = KP_Form_Fields::$kp_form_auto_countries[ strtolower( $this->arguments['country'] ?? '' ) ] ?? null;

		$this->load_settings();
		$this->set_environment();
	}

	/**
	 * Loads the Klarna payments settings and sets them to be used here.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_klarna_payments_settings', array() );
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		return $this->environment;
	}

	/**
	 * Get the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Content-type'  => 'application/json',
			'Authorization' => $this->calculate_auth(),
		);
	}

	/**
	 * Calculates the basic auth.
	 *
	 * @return string
	 */
	protected function calculate_auth() {
		return 'Basic ' . base64_encode( $this->merchant_id . ':' . htmlspecialchars_decode( $this->shared_secret ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth headers.
	}

	/**
	 * Sets the environment.
	 */
	protected function set_environment() {
		$region     = $this->country_params['endpoint'] ?? ''; // Get the region from the country parameters, blank for EU.
		$playground = 'yes' == $this->settings['test_mode'] ? 'playground.' : ''; // If testmode is enabled, add playground to the subdomain.
		$subdomain  = "api${region}.${playground}"; // Combine the string to one subdomain.

		$this->environment = "https://${subdomain}.klarna.com/"; // Return the full base url for the api.
	}

	/**
	 * Sets Klarna credentials.
	 */
	public function set_credentials() {
		if ( 'yes' == $this->settings['test_mode'] ) {
			$merchant_id   = 'test_merchant_id_' . strtolower( $this->country );
			$shared_secret = 'test_shared_secret_' . strtolower( $this->country );

			$this->merchant_id   = isset( $this->settings[ $merchant_id ] ) ? $this->settings[ $merchant_id ] : '';
			$this->shared_secret = isset( $this->settings[ $shared_secret ] ) ? $this->settings[ $shared_secret ] : '';
		} else {
			$merchant_id   = 'merchant_id_' . strtolower( $this->country );
			$shared_secret = 'shared_secret_' . strtolower( $this->country );

			$this->merchant_id   = isset( $this->settings[ $merchant_id ] ) ? $this->settings[ $merchant_id ] : '';
			$this->shared_secret = isset( $this->settings[ $shared_secret ] ) ? $this->settings[ $shared_secret ] : '';
		}
	}

	/**
	 * Get the user agent.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		$wp_version  = get_bloginfo( 'version' );
		$wp_url      = get_bloginfo( 'url' );
		$wc_version  = WC()->version;
		$kp_version  = WC_KLARNA_PAYMENTS_VERSION;
		$php_version = phpversion();

		return apply_filters( 'http_headers_useragent', "WordPress/$wp_version; $wp_url - WooCommerce: $wc_version - KP: $kp_version - PHP Version: $php_version - Krokedil" );
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	abstract protected function get_request_args();

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Make the request.
	 *
	 * @return object|WP_Error
	 */
	public function request() {
		$url      = $this->get_request_url();
		$args     = $this->get_request_args();
		$response = wp_remote_request( $url, $args );
		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );

				foreach ( $errors as $error ) {
					$error_message .= ' ' . $error;
				}
			}
			$code          = wp_remote_retrieve_response_code( $response );
			$error_message = empty( $response['body'] ) ? "API Error ${code}" : json_decode( $response['body'], true )['ErrorMessage'];
			$return        = new WP_Error( $code, $error_message, $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		$this->log_response( $response, $request_args, $request_url );
		return $return;
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request URL.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url ) {
		$method   = $this->method;
		$title    = "{$this->log_title} - URL: {$request_url}";
		$code     = wp_remote_retrieve_response_code( $response );
		$order_id = $response['OrderID'] ?? null;
		$log      = KP_Logger::format_log( $order_id, $method, $title, $request_args, $response, $code, $request_url );
		KP_Logger::log( $log );
	}
}
