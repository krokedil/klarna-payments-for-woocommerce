<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\Klarna\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIWK Ajax class.
 */
class Ajax {

	/**
	 * JWT interface.
	 *
	 * @var JWT
	 */
	private $jwt;

	/**
	 * Handles metadata associated with a WordPress user.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Class constructor.
	 *
	 * The AJAX request should only be enqueued ONCE, and only ONCE.
	 *
	 * @param JWT  $jwt JWT instance.
	 * @param User $user User instance.
	 */
	public function __construct( $jwt, $user ) {
		$this->jwt  = $jwt;
		$this->user = $user;

		$ajax_events = array(
			'siwk_sign_in_from_popup'    => true,
			'siwk_sign_in_from_redirect' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
				add_action( 'wc_ajax_' . $ajax_event, array( $this, $ajax_event ) );
			}
		}
	}

	/**
	 * Register a new user or sign in an existing user.
	 *
	 * @return void A WP JSON response.
	 */
	private function handle_sign_in() {
		// phpcs:ignore -- Nonce is checked by calling function.
		$id_token = wc_get_var( $_POST['id_token'] );
		if ( empty( $id_token ) ) {
			wp_send_json_error( 'missing parameters' );
		}

		$id_token = sanitize_text_field( wp_unslash( $id_token ) );
		$payload  = $this->jwt->get_payload( $id_token );

		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( $payload->get_error_message() );
		}

		$userdata = $this->user->get_user_data( $payload );

		if ( email_exists( $userdata['user_email'] ) ) {
			$user_id = $this->user->merge_with_existing_user( $userdata );
		} else {
			$user_id = $this->user->register_new_user( $userdata );
		}

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( $user_id->get_error_message() );
		}

		$guest        = 0;
		$current_user = get_current_user_id();
		$tokens       = array(
			'id_token' => $id_token,
		);

		if ( $guest === $current_user ) {
			$this->user->sign_in_user( $user_id, $tokens );
		} else {
			// The only condition for displaying the sign-in button is that the user does not have an id token.
			// Therefore, it could be displayed for a signed-in user. If the user is already signed in, we only update the tokens.
			$this->user->set_tokens( $user_id, $tokens );
		}

		// phpcs:ignore -- Nonce is checked by calling function.
		$url  = sanitize_url( wc_get_var( $_POST['url'], '' ) );
		$page = strpos( wc_get_cart_url(), $url ) !== false ? 'cart' : 'shop';
		wp_send_json_success(
			array(
				'user_id'  => $user_id,
				'redirect' => apply_filters( 'siwk_redirect_url', get_permalink( wc_get_page_id( $page ) ), $page ),
			)
		);
	}

	/**
	 * Handle sign-in request from Klarna redirect.
	 *
	 * @return void A WP JSON response.
	 */
	public function siwk_sign_in_from_redirect() {
		// Unlike with pop-out, we don't need to check for a nonce here since the request is triggered directly by Klarna.
		$this->handle_sign_in();
	}

	/**
	 * Handle sign-in request from client via pop-out.
	 *
	 * @return void A WP JSON response.
	 */
	public function siwk_sign_in_from_popup() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'siwk_sign_in_from_popup' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		$this->handle_sign_in();
	}
}
