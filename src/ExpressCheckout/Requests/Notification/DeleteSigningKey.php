<?php

namespace Krokedil\Klarna\ExpressCheckout\Requests\Notification;

use Krokedil\Klarna\ExpressCheckout\Requests\Base;

defined( 'ABSPATH' ) || exit;

class DeleteSigningKey extends Base {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments Arguments to pass to the request.
	 *
	 * @return void
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method  = 'DELETE';

		$signing_key_id  = $this->arguments['signing_key_id'] ?? '';
		$this->endpoint  = "v2/notification/signing-keys/$signing_key_id";
		$this->log_title = 'KEC: Delete Notification Signing Key';
	}
}
