<?php
namespace Krokedil\Klarna\Api;

use Krokedil\Klarna\Api\Controllers\Controller;
use Krokedil\Klarna\Api\Controllers\Notifications;

defined( 'ABSPATH' ) || exit;

class Registry {
	/**
	 * The list of controllers.
	 *
	 * @var Controller[]
	 */
	protected $controllers = array();

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
		add_action( 'rest_api_init', array( $this, 'register_controller_routes' ) );
	}

	/**
	 * Initialize the API controllers and models.
	 *
	 * @return void
	 */
	public function init() {
		// Register the controllers.
		$this->register_controller( new Notifications() );
	}

	/**
	 * Register the controllers.
	 *
	 * @return void
	 */
	public function register_controller_routes() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Register a controller.
	 *
	 * @param Controller $controller The controller to register.
	 *
	 * @return void
	 */
	public function register_controller( $controller ) {
		$this->controllers[ get_class( $controller ) ] = $controller;
	}
}
