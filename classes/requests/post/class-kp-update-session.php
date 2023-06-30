<?php
/**
 * Class for the request to update a session.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Update_Session class.
 */
class KP_Update_Session extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Update session';
		$this->request_filter = 'wc_klarna_payments_update_session_args';
		$session_id           = $this->arguments['session_id'];
		$this->endpoint       = "payments/v1/sessions/{$session_id}";
	}
}
