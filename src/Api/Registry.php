<?php
namespace Krokedil\Klarna\Api;

use Krokedil\Klarna\Api\Controllers\Controller;
use Krokedil\Klarna\Api\Controllers\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Registry class for the API controllers.
 * Handles the registration and initialization of API controllers.
 */
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
		$this->controllers = apply_filters( 'klarna_register_api_controller', array() );
		add_action( 'rest_api_init', array( $this, 'register_controller_routes' ) );
	}

	/**
	 * Initialize the API controllers and models.
	 *
	 * @return void
	 */
	public function init() {
		foreach ( $this->controllers as $controller ) {
			// Ensure the controller is an instance of Controller before registering.
			if ( ! $controller instanceof Controller ) {
				wc_doing_it_wrong(
					__METHOD__,
					sprintf(
					/* translators: 1: The name of the incorrect class. 2: The name of the base Controller class. */
						__( 'The controller %1$s must extend the %2$s class.', 'klarna-payments-for-woocommerce' ),
						is_object( $controller ) ? get_class( $controller ) : gettype( $controller ),
						Controller::class
					),
					'1.0.0'
				); // @TODO update version number.
				continue;
			}

			$this->register_controller( $controller );
		}
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
