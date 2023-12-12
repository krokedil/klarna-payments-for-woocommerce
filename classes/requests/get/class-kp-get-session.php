<?php
/**
 * Class for the request to create a session.
 *
 * @package WC_Klarna_Payments/Classes/Requests/GET
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Get_Session class.
 */
class KP_Get_Session extends KP_Requests_Get {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$session_id = $arguments['session_id'];

		$this->log_title      = 'Get session';
		$this->request_filter = 'wc_klarna_payments_get_session_args';
		$this->endpoint       = "payments/v1/sessions/$session_id";
	}
}
