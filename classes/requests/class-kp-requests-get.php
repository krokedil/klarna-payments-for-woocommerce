<?php
/**
 * Base class for all GET requests.
 *
 * @package WC_Klarna_Payments/Classes/Request
 */

defined( 'ABSPATH' ) || exit;

/**
 *  The main class for GET requests.
 */
abstract class KP_Requests_Get extends KP_Requests {

	/**
	 * KP_Requests_Post constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'GET';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @param string $url The request URL, forwarded to get_user_agent() for the http_headers_useragent filter.
	 * @return array Request arguments
	 */
	protected function get_request_args( $url = '' ) {
		return apply_filters(
			$this->request_filter,
			array(
				'headers'    => $this->get_request_headers(),
				'user-agent' => $this->get_user_agent( $url ),
				'method'     => $this->method,
				'timeout'    => apply_filters( 'wc_kp_request_timeout', 10 ),
			)
		);
	}
}
