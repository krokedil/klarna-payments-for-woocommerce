<?php
namespace Krokedil\Klarna\Api\Controllers;

use Krokedil\Klarna\Api\Notifications\NotificationsProvider;

defined( 'ABSPATH' ) || exit;

class Notifications extends Controller {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'notifications';

	/**
	 * The provider for the notifications.
	 *
	 * @var NotificationsProvider
	 */
	protected $provider;

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->provider = new NotificationsProvider();
	}

	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the callback route for the controller.
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
	 * Handle the save card callback.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_notification( $request ) {
		try {
			$body = $request->get_json_params();

			$meta_data = $body['metadata'] ?? array();

			// If the metadata was empty, we can not process the notification.
			if ( empty( $meta_data ) ) {
				return $this->success_response();
			}


			$event_type    = $meta_data['event_type'] ?? '';
			$event_version = $meta_data['event_version'] ?? '';
			$payload       = $meta_data['payload'] ?? array();

			// Get the handler for the event type and version.
			$handler = $this->provider->get_handler( $event_type, $event_version );

			if ( null === $handler ) {
				do_action( "klarna_notification_{$event_type}_{$event_version}", $body ); // Trigger the action to allow other plugins to handle the event.
				return $this->success_response(); // Return a success if nothing has thrown an exception.
			}

			$handler->handle_notification( $payload );

			// Trigger an action to let other plugins know that a change has been made, and allow them to take action if needed.
			do_action( "klarna_notification_{$event_type}_{$event_version}", $body );

			return $this->success_response();
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
	 * Validate the request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	public function validate_request( $request ) {
		// Get the Klarna-Signing-Key-Id from the header.
		$signing_key_id = $request->get_header( 'Klarna-Signing-Key-Id' );
		$signature 	    = $request->get_header( 'Klarna-Signature' );
		$body           = $request->get_body();

		// Validate the signing key ID.
		if ( empty( $signing_key_id ) ) {
			return false;
		}

		// Get the signing key from the settings.
		$settings              = get_option( 'kec_notifications_signing_key', array() );
		$stored_signing_key_id = $settings['signing_key_id'] ?? '';
		$stored_signing_key    = $settings['signing_key'] ?? '';

		// Ensure the signing key id matches the stored one.
		if ( $signing_key_id !== $stored_signing_key_id ) {
			return false;
		}

		// Validate the body using the signing key and signature from the header using HMAC-SHA256.
		$calculated_signature = hash_hmac( 'sha256', $body, $stored_signing_key, false );
		if ( ! hash_equals( $calculated_signature, $signature ) ) {
			return false;
		}

		return true;
	}
}
