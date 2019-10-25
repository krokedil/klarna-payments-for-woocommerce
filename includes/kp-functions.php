<?php
/**
 * Plugin function file.
 *
 * @package WC_Klarna_Payments/Includes
 */

/**
 * Maybe creates or updates Klarna Payments session.
 *
 * @param string $klarna_country The Klarna Country.
 * @return void|string
 */
function kp_maybe_create_session( $klarna_country ) {
	if ( WC()->session->get( 'klarna_payments_session_id' ) && ( WC()->checkout->get_value( 'billing_country' ) === WC()->session->get( 'klarna_payments_session_country' ) ) ) { // Check if we have session ID and country has not changed.
		// Try to update the session, if it fails try to create new session.
		$request  = new KP_Update_Session();
		$response = $request->request();
		if ( is_wp_error( $response ) ) { // If update session failed try to create new session.
			kp_unset_session_values();
			$request  = new KP_Create_Session();
			$response = $request->request();
			if ( is_wp_error( $response ) ) {
				return kp_extract_error_message( $response );
			}
			WC()->session->set( 'klarna_payments_session_id', $create_response['session_id'] );
			WC()->session->set( 'klarna_payments_client_token', $create_response['client_token'] );
			WC()->session->set( 'klarna_payments_session_country', $klarna_country );
			WC()->session->set( 'klarna_payments_categories', $create_response['payment_method_categories'] );
		}
	} else {
		$request  = new KP_Create_Session();
		$response = $request->request();
		if ( is_wp_error( $response ) ) {
			return kp_extract_error_message( $response );
		}
		WC()->session->set( 'klarna_payments_session_id', $create_response['session_id'] );
		WC()->session->set( 'klarna_payments_client_token', $create_response['client_token'] );
		WC()->session->set( 'klarna_payments_session_country', $klarna_country );
		WC()->session->set( 'klarna_payments_categories', $create_response['payment_method_categories'] );
	}

	// If we have a client token now, initialize Klarna Credit.
	if ( WC()->session->get( 'klarna_payments_client_token' ) && is_ajax() ) {
		// @codingStandardsIgnoreStart
		// @TODO Maybe change this to not be inline included.
		?>
		<script>
			window.klarnaInitData = {client_token: "<?php echo esc_attr( WC()->session->get( 'klarna_payments_client_token' ) ); ?>"};
			window.klarnaAsyncCallback = function () {
				Klarna.Payments.init(klarnaInitData);
			};
		</script>
		<script src="https://x.klarnacdn.net/kp/lib/v1/api.js" async></script>
		<?php
		// @codingStandardsIgnoreEnd
	}
}

/**
 * Unsets all Klarna Payments sessions.
 */
function kp_unset_session_values() {
	WC()->session->__unset( 'klarna_payments_session_id' );
	WC()->session->__unset( 'klarna_payments_client_token' );
	WC()->session->__unset( 'klarna_payments_session_country' );
	WC()->session->__unset( 'klarna_payments_categories' );
}

/**
 * Checks if a response has errors in it.
 *
 * @param array $response Klarna Payment Response.
 * @return string
 */
function kp_extract_error_message( $response ) {
	return 'Error';
}
