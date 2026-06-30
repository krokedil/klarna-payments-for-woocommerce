<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout\Requests\Notification;

use Krokedil\Klarna\KlarnaExpressCheckout\Requests\Base;

defined( 'ABSPATH' ) || exit;

class CreateWebhook extends Base {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments Arguments to pass to the request.
	 *
	 * @return void
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method    = 'POST';
		$this->endpoint  = 'v2/notification/webhooks';
		$this->log_title = 'KEC: Create Notification Webhook';
	}

	/**
	 * Get the request body.
	 *
	 * @return array The request body.
	 */
	public function get_request_body() {
		return array(
			'url'            => $this->arguments['url'],
			'event_types'    => $this->arguments['event_types'],
			'event_version'  => $this->arguments['event_version'],
			'signing_key_id' => $this->arguments['signing_key_id'],
			'status'         => 'ENABLED',
		);
	}
}
