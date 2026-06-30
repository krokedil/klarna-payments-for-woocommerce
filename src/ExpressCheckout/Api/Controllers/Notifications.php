<?php

namespace Krokedil\Klarna\ExpressCheckout\Api\Controllers;

use Krokedil\Klarna\Api\Controllers\Controller;
use Krokedil\Klarna\ExpressCheckout\Api\Notifications\NotificationsProvider;

defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for Express Checkout notifications.
 */
class Notifications extends Controller {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'kec/notifications';

	/**
	 * The provider for the notifications.
	 *
	 * @var NotificationsProvider
	 */
	protected $provider;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->provider = new NotificationsProvider();
	}

	/**
	 * Register the controller for handling notifications in the Klarna API.
	 *
	 * @return void
	 */
	public static function register_controller() {
		KP_WC()->api_registry()->register_controller( new Notifications() );
	}

	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->get_request_path(),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_notification' ),
				'permission_callback' => array( $this, 'validate_request' ),
			)
		);
	}

	/**
	 * Handle the notification callback.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_notification( $request ) {
		try {
			$body = $request->get_json_params();

			$meta_data = $body['metadata'] ?? array();

			if ( empty( $meta_data ) ) {
				return $this->success_response();
			}

			$event_type    = $meta_data['event_type'] ?? '';
			$event_version = $meta_data['event_version'] ?? '';
			$payload       = $body['payload'] ?? array();

			$handler = $this->provider->get_handler( $event_type, $event_version );

			if ( null === $handler ) {
				do_action( "klarna_notification_{$event_type}_{$event_version}", $body );
				return $this->success_response();
			}

			$response = $handler->handle_notification( $payload );

			do_action( "klarna_notification_{$event_type}_{$event_version}", $body );

			return $response ?? $this->success_response();
		} catch ( \Exception $e ) {
			return new \WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Return a successful response.
	 *
	 * @return \WP_REST_Response
	 */
	public function success_response() {
		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Validate the request using HMAC-SHA256 signature verification.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	public function validate_request( $request ) {
		$signing_key_id = $request->get_header( 'Klarna-Signing-Key-Id' );
		$signature      = $request->get_header( 'Klarna-Signature' );
		$body           = $request->get_body();

		if ( empty( $signing_key_id ) ) {
			return false;
		}

		$settings              = get_option( 'kec_signing_key', array() );
		$stored_signing_key_id = $settings['signing_key_id'] ?? '';
		$stored_signing_key    = $settings['signing_key'] ?? '';

		if ( $signing_key_id !== $stored_signing_key_id ) {
			return false;
		}

		$calculated_signature = hash_hmac( 'sha256', $body, $stored_signing_key, false );
		if ( ! hash_equals( $calculated_signature, $signature ) ) {
			return false;
		}

		return true;
	}
}
