<?php

namespace Krokedil\Klarna\ExpressCheckout\Requests\Notification;

use Krokedil\Klarna\ExpressCheckout\Requests\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Request class for creating a notification signing key.
 */
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
