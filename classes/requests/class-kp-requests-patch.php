<?php
/**
 * Base class for all PATCH requests.
 *
 * @package WC_Klarna_Payments/Classes/Request
 */

defined( 'ABSPATH' ) || exit;

/**
 *  The main class for PATCH requests.
 */
abstract class KP_Requests_Patch extends KP_Requests {

	/**
	 * KP_Requests_Patch constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'PATCH';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		$body = $this->get_body();

		return apply_filters(
			$this->request_filter,
			array(
				'headers'    => $this->get_request_headers(),
				'user-agent' => $this->get_user_agent(),
				'method'     => $this->method,
				'timeout'    => apply_filters( 'wc_kp_request_timeout', 10 ),
				'body'       => wp_json_encode( apply_filters( 'kp_wc_api_request_args', $body ) ),
			)
		);
	}

	/**
	 * Returns a formatted Klarna order object.
	 *
	 * @return array
	 */
	abstract function get_body();
}
