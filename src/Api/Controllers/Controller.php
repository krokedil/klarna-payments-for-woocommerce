<?php
namespace Krokedil\Klarna\Api\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract controller class.
 * Contains common methods and properties for all controllers.
 */
abstract class Controller {
	/**
	 * The namespace of the controller.
	 *
	 * @var string
	 */
	protected $namespace = 'klarna';

	/**
	 * The version of the controller.
	 *
	 * @var string
	 */
	protected $version = 'v1';

	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Get the base path for the controller.
	 *
	 * @return string
	 */
	protected function get_base_path() {
		// Combine the version and path to create the base path, ensuring that the path doesn't start or end with a slash.
		return trim( "{$this->version}/{$this->path}", '/' );
	}

	/**
	 * Get the request path.
	 *
	 * @return string
	 */
	public function get_request_path() {
		$base_path = $this->get_base_path();
		return trim( "{$base_path}", '/' );
	}

	/**
	 * Send a response.
	 *
	 * @param object|array|null|\WP_Error $response Response object.
	 * @param int                         $status_code Status code.
	 *
	 * @return void
	 */
	protected function send_response( $response, $status_code = 200 ) {
		// Check if the response is a WP_Error.
		if ( is_wp_error( $response ) ) {
			$this->send_error_response( $response );
		}
	}

	/**
	 * Send a error response.
	 *
	 * @param \WP_Error $wp_error The error object.
	 *
	 * @return void
	 */
	protected function send_error_response( $wp_error ) {
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	abstract public function register_routes();
}
