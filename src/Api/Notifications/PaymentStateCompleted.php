<?php
namespace Krokedil\Klarna\Api\Notifications;

defined( 'ABSPATH' ) || exit;

class PaymentStateCompleted extends Handler {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'payment.request.state-change.completed';

	/**
	 * The version for the notification.
	 *
	 * @var string
	 */
	protected $event_version = 'v2';

	/**
	 * @inheritDoc
	 */
	public function handle_notification($payload, $order = null) {
	}
}
