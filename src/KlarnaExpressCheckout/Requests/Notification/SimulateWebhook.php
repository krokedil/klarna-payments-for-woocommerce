<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout\Requests\Notification;

use Krokedil\Klarna\KlarnaExpressCheckout\Requests\Base;

defined( 'ABSPATH' ) || exit;

class SimulateWebhook extends Base {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments Arguments to pass to the request.
	 *
	 * @return void
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method  = 'POST';

		$webhook_id      = $this->arguments['webhook_id'] ?? '';
		$this->endpoint  = "v2/notification/webhooks/$webhook_id/simulate";
		$this->log_title = 'KEC: Simulate Notification Webhook';
	}

	/**
	 * Get the request body.
	 *
	 * @return array The request body.
	 */
	public function get_request_body() {
		return array(
			'event_type'    => $this->arguments['event_type'] ?? '',
			'event_version' => $this->arguments['event_version'] ?? '',
		);
	}
}
