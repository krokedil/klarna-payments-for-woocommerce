<?php
namespace Krokedil\Klarna\Api\Notifications;

defined( 'ABSPATH' ) || exit;

abstract class Handler {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type;

	/**
	 * The version for the notification.
	 *
	 * @var string
	 */
	protected $event_version;

	/**
	 * Check if the event type and version matches the notification handler.
	 *
	 * @param string $event_type    The event type to check.
	 * @param string $event_version The version to check.
	 */
	public function matches( $event_type, $event_version ) {
		return $this->event_type === $event_type && $this->event_version === $event_version;
	}

	/**
	 * Handle the notification callback.
	 *
	 * @param array         $payload The payload from the notification.
	 * @param \WC_Order|null $order The order object, if available.
	 *
	 * @return void
	 * @throws \WP_Exception If the notification cannot be handled.
	 */
	abstract public function handle_notification( $payload, $order = null );
}
