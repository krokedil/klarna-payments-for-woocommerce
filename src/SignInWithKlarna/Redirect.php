<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\Klarna\SignInWithKlarna;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the callback from the redirect flow.
 */
class Redirect {

	/**
	 * The redirect callback endpoint.
	 *
	 * @var string
	 */
	public const REDIRECT_CALLBACK_ENDPOINT = '/siwk/klarna/callback';

	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Class constructor.
	 *
	 * @param Settings $settings The plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;

		// Check whether the request is for the callback endpoint. If so, handle it.
		add_action( 'parse_request', array( $this, 'maybe_handle_callback' ) );
	}

	/**
	 * Check whether the request is for the callback endpoint.
	 *
	 * @hook parse_request
	 *
	 * @param \WP $wp The WordPress request object.
	 * @return void
	 */
	public function maybe_handle_callback( $wp ) {
		if ( strpos( $wp->request, 'siwk/klarna/callback' ) !== false ) {
			$this->handle_redirect_callback();
		}
	}

	/**
	 * Callback for the sign-in endpoint.
	 *
	 * @return void
	 */
	public function handle_redirect_callback() {
		$response = wp_remote_get( plugin_dir_url( __FILE__ ) . 'templates/callback.html' );
		$body     = wp_remote_retrieve_body( $response );

		$page         = null;
		$redirect_url = apply_filters( 'siwk_redirect_url', get_permalink( wc_get_page_id( 'shop' ) ), $page );
		if ( empty( $body ) ) {
			wp_safe_redirect( $redirect_url );
		} else {
			header( 'Content-Type: text/html' );
			$client_id = $this->settings->get( 'client_id' );
			$locale    = $this->settings->get( 'locale' );

			// The Klarna SDK will not run any event if the redirect_uri differs from the pre-registered URL. Therefore, we cannot redirect the user to the callback.html page. Instead, we must echo the contents of the file. And since we cannot add any query parameters, we must use template strings to add the client ID and the locale.
			$body = str_replace( '%client_id%', $client_id, $body );
			$body = str_replace( '%locale%', $locale, $body );

			// Show a link back to the shop page in case the sign in fails.
			$body = str_replace( '%store_url%', $redirect_url, $body );

			// The AJAX URL.
			$body = str_replace( '%sign_in_url%', \WC_AJAX::get_endpoint( 'siwk_sign_in_from_redirect' ), $body );

			// phpcs:ignore -- body does not contain user input.
			echo $body;
		}
		exit;
	}

	/**
	 * Retrieve the callback URL.
	 *
	 * @return string
	 */
	public static function get_callback_url() {
		// Since Woo requires pretty permalinks, we can assume it is always set, therefore, don't have to fallback to the "rest_route" parameter.
		return apply_filters( 'siwk_callback_url', home_url( self::REDIRECT_CALLBACK_ENDPOINT ) );
	}
}
