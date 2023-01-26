<?php
/**
 * Class for the request to create a session.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Create_Session class.
 */
class KP_Create_Session extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create session';
		$this->request_filter = 'wc_klarna_payments_create_session_args';
		$this->endpoint       = 'payments/v1/sessions';
	}
}
