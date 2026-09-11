<?php
namespace Krokedil\Klarna\Logging;

use KrokedilKlarnaPaymentsDeps\Krokedil\WpApi\KeyMasker;

defined( 'ABSPATH' ) || exit;

/**
 * What the plugin masks out of its logs, shared by both request layers.
 *
 * The configured rules describe the Klarna payloads we know about. The key names are the
 * safety net for everything else that reaches a log entry, including the stack trace.
 */
class LogMasking {
	/**
	 * The address fields kept readable, since a rejected address is the most common thing
	 * a support case is about and none of these identify a person on their own.
	 */
	const ADDRESS_KEPT = array( 'postal_code', 'city', 'region', 'country' );

	/**
	 * Key names masked wherever they appear, on top of the package defaults.
	 *
	 * @var string[]
	 */
	private static $key_names = array(
		'given_name',
		'family_name',
		'organization_name',
		'email',
		'phone',
		'street_address',
		'date_of_birth',
		'national_identification',
		'organization_registration_id',
		'vat_id',
		// Shipment tracking identifies a delivery, and with it a person.
		'tracking_number',
		'tracking_uri',
		// Klarna hosted URLs are single use capabilities: whoever holds one can pay with it.
		'redirect_url',
		'distribution_url',
		'qr_code_url',
		'html_snippet',
	);

	/**
	 * Widen the package key name masking with the names Klarna uses.
	 *
	 * @return void
	 */
	public static function register() {
		KeyMasker::add_keys( self::$key_names );
	}

	/**
	 * The rules for a Klarna request body, which is the same shape on every endpoint.
	 *
	 * @return array
	 */
	public static function body_fields() {
		return array(
			'billing_address'  => array( 'keep' => self::ADDRESS_KEPT ),
			'shipping_address' => array( 'keep' => self::ADDRESS_KEPT ),
			'customer'         => array( 'keep' => array( 'type' ) ),
			// The EMD attachment repeats the customer details in a json encoded string.
			'attachment'       => 'mask',
			// The success URL carries the order key, which grants access to the order.
			'merchant_urls'    => 'mask',
		);
	}

	/**
	 * The rules for a Klarna response body.
	 *
	 * @return array
	 */
	public static function response_fields() {
		return self::body_fields() + array( 'client_token' => 'mask' );
	}
}
