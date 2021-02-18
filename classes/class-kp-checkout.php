<?php
/**
 * Class for handling events during the checkout process.
 *
 * @package WC_Klarna_Payments/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Class for handling events during the checkout process.
 */
class KP_Checkout {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_klarna_session' ), 9999 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_token_fragment' ) );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'html_client_token' ) );
	}

	/**
	 * Update the current Klarna session.
	 *
	 * @return void
	 */
	public function update_klarna_session() {
		if ( ! is_checkout() ) {
			return;
		}

		$kp_session = kp_maybe_create_session_cart( WC()->customer->get_billing_country() );

		if ( ! is_array( $kp_session ) && ! empty( $kp_session ) ) {
			// Handle error.
			return;
		}
	}

	/**
	 * Returns a HTML snippet for the client token for the KP Session.
	 *
	 * @param array $fragments The fragments for the checkout.
	 * @return array
	 */
	public function add_token_fragment( $fragments ) {
		$session_token = WC()->session->get( 'klarna_payments_client_token' );
		if ( empty( $session_token ) ) {
			return $fragments;
		}

		ob_start();
		$this->html_client_token( $session_token );
		$html = ob_get_clean();

		$fragments['#kp-client-token'] = $html;
		return $fragments;
	}

	/**
	 * Generates the HTML for the client token input.
	 *
	 * @param string|bool $session_token The Klarna payments session token.
	 * @return void
	 */
	public function html_client_token( $session_token = false ) {
		if ( ! $session_token ) {
			$session_token = WC()->session->get( 'klarna_payments_client_token' );
		}
		?>
		<input type="hidden" id="kp_client_token" value="<?php echo esc_html( $session_token ); ?>" >
		<?php
	}
} new KP_Checkout();
