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
	 * The object cache group used to claim a notification before it is handled.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'kec_notifications';

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
		$notification_id = $this->get_notification_id( $request );

		if ( ! $this->claim_notification( $notification_id ) ) {
			\KP_Logger::log( "KEC: Ignored an already handled notification: {$notification_id}" );
			return $this->success_response();
		}

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
				/**
				 * Triggers when a Klarna notification is received but no handler is registered for its event type and version.
				 *
				 * The dynamic portion of the hook name, `$event_type` and `$event_version`, refers to the Klarna event type and version, for example `payment_completed_v2`.
				 *
				 * @param array $body The full notification body received from Klarna.
				 */
				do_action( "klarna_notification_{$event_type}_{$event_version}", $body );
				return $this->success_response();
			}

			$response = $handler->handle_notification( $payload );

			/**
			 * Triggers after a Klarna notification has been handled by its registered handler.
			 *
			 * The dynamic portion of the hook name, `$event_type` and `$event_version`, refers to the Klarna event type and version, for example `payment_completed_v2`.
			 *
			 * @param array $body The full notification body received from Klarna.
			 */
			do_action( "klarna_notification_{$event_type}_{$event_version}", $body );

			return $response ?? $this->success_response();
		} catch ( \Throwable $e ) {
			// Release the claim for every processing failure, not just exceptions, so that Klarna's retry of the notification is handled instead of ignored.
			$this->release_notification( $notification_id );
			return new \WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Get the identifier that uniquely identifies an incoming notification.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return string The notification identifier, or an empty string if it could not be determined.
	 */
	protected function get_notification_id( $request ) {
		$body      = $request->get_json_params();
		$meta_data = is_array( $body ) ? ( $body['metadata'] ?? array() ) : array();
		$event_id  = is_array( $meta_data ) ? ( $meta_data['event_id'] ?? '' ) : '';

		// The body is whatever Klarna sent us, so only a scalar event ID is usable as an identifier. Anything else falls back to the signature.
		if ( ! empty( $event_id ) && is_scalar( $event_id ) ) {
			return strval( $event_id );
		}

		return strval( $request->get_header( 'Klarna-Signature' ) );
	}

	/**
	 * Record the notification as handled, and report whether it is the first time it is seen.
	 *
	 * @param string $notification_id The notification identifier.
	 *
	 * @return bool Whether the notification may be handled.
	 */
	protected function claim_notification( $notification_id ) {
		if ( empty( $notification_id ) ) {
			return true;
		}

		$key = self::get_notification_transient( $notification_id );

		/**
		 * Filters how long a handled Klarna notification is remembered, and therefore how long a repeat of it is ignored.
		 *
		 * @param int $replay_window The time to remember a notification, in seconds. Default two days.
		 */
		$replay_window = apply_filters( 'kec_notification_replay_window', \DAY_IN_SECONDS * 2 );

		/*
		 * Atomically claim duplicate notifications when using a persistent object cache; otherwise the transient decides.
		 */
		if ( wp_using_ext_object_cache() && ! wp_cache_add( $key, time(), self::CACHE_GROUP, $replay_window ) ) {
			return false;
		}

		if ( false !== get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, time(), $replay_window );

		return true;
	}

	/**
	 * Forget a notification that could not be handled, so that Klarna's retry of it is processed.
	 *
	 * @param string $notification_id The notification identifier.
	 *
	 * @return void
	 */
	protected function release_notification( $notification_id ) {
		if ( empty( $notification_id ) ) {
			return;
		}

		$key = self::get_notification_transient( $notification_id );

		wp_cache_delete( $key, self::CACHE_GROUP );
		delete_transient( $key );
	}

	/**
	 * Get the name of the transient that records a notification as handled.
	 *
	 * @param string $notification_id The notification identifier.
	 *
	 * @return string
	 */
	private static function get_notification_transient( $notification_id ) {
		return 'kec_notification_' . hash( 'sha256', $notification_id );
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

		if ( empty( $signing_key_id ) || empty( $signature ) ) {
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
