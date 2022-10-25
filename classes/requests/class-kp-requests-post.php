<?php
/**
 * Base class for all POST requests.
 *
 * @package WC_Klarna_Payments/Classes/Request
 */

defined( 'ABSPATH' ) || exit;

/**
 *  The main class for POST requests.
 */
abstract class KP_Requests_Post extends KP_Requests {

	/**
	 * Qliro_One_Request_Post constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'POST';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		return apply_filters(
			$this->request_filter,
			array(
				'headers'    => $this->get_request_headers(),
				'user-agent' => $this->get_user_agent(),
				'method'     => $this->method,
				'timeout'    => apply_filters( 'wc_kp_request_timeout', 10 ),
				'body'       => wp_json_encode( apply_filters( 'kp_wc_api_request_args', $this->get_body() ) ),
			)
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}
