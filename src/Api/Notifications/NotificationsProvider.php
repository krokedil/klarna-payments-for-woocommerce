<?php
namespace Krokedil\Klarna\Api\Notifications;

defined( 'ABSPATH' ) || exit;

class NotificationsProvider {
	/**
	 * Array of handlers for notifications.
	 *
	 * @var Handler[]
	 */
	protected $handlers = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->handlers = array(
			new PaymentStateSubmitted(),
			new PaymentStateCompleted(),
			new PaymentStateExpired(),
		);
	}

	/**
	 * Get the handler for the given event type and version.
	 *
	 * @param string $event_type    The event type to check.
	 * @param string $event_version The version to check.
	 *
	 * @return Handler|null The handler if found, null otherwise.
	 */
	public function get_handler( $event_type, $event_version ) {
		foreach ( $this->handlers as $handler ) {
			if ( $handler->matches( $event_type, $event_version ) ) {
				return $handler;
			}
		}

		return null;
	}
}
