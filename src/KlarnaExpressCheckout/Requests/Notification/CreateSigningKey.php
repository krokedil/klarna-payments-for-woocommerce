<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout\Requests\Notification;

use Krokedil\Klarna\KlarnaExpressCheckout\Requests\Base;

defined( 'ABSPATH' ) || exit;

class CreateSigningKey extends Base {
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
		$this->endpoint  = 'v2/notification/signing-keys';
		$this->log_title = 'KEC: Create Notification Signing Key';
	}
}
