<?php
class KP_Payment_Processor_KEC extends KP_Payment_Processor {
	/**
	 * Get the Klarna country code for the order.
	 *
	 * @return string The Klarna country code.
	 */
	public function get_klarna_country() {
		$klarna_country = $this->get_klarna_country();

		// If EU credentials are combined, we should use the EU country code.
		$combined_eu = 'yes' === ( isset( $this->settings['combine_eu_credentials'] ) ? $this->settings['combine_eu_credentials'] : 'no' );
		if ( $combined_eu && key_exists( strtolower( $klarna_country ), KP_Form_Fields::available_countries( 'eu' ) ) ) {
			$klarna_country = 'EU';
		}

		return $klarna_country;
	}

	/**
	 * Get the Klarna session id for the order.
	 *
	 * @return string
	 */
	public function get_session_id() {
		$session_id = KrokedilKlarnaPaymentsDeps\Krokedil\KlarnaExpressCheckout\Session::get_client_token();

		if ( empty( $session_id ) ) {
			throw new WP_Exception( __( 'Failed to get required data from the Klarna session. Please try again.', 'krokedil-klarna-payments' ) );
		}

		return $session_id;
	}
}