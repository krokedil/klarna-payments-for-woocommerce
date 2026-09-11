<?php
namespace Krokedil\Klarna\Logging;

use KrokedilKlarnaPaymentsDeps\Krokedil\WpApi\FieldMasker;
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

	/**
	 * The rules for a whole set of request args.
	 *
	 * @return array
	 */
	public static function request_fields() {
		return array(
			'headers' => array( 'Authorization' ),
			'body'    => self::body_fields(),
		);
	}

	/**
	 * Mask a set of request args, for a request layer the package does not own.
	 *
	 * @param array $request_args The request args.
	 * @return array|string The masked args, or the failure marker.
	 */
	public static function mask_request( $request_args ) {
		try {
			// Decode the body that was really sent, so the rules can reach into it.
			if ( isset( $request_args['body'] ) && is_string( $request_args['body'] ) ) {
				$decoded              = json_decode( $request_args['body'], true );
				$request_args['body'] = is_array( $decoded ) ? $decoded : $request_args['body'];
			}

			return FieldMasker::mask( $request_args, self::request_fields() );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask a decoded response body, for a request layer the package does not own.
	 *
	 * @param array $body The decoded response body.
	 * @return array|string The masked body, or the failure marker.
	 */
	public static function mask_response( $body ) {
		if ( empty( $body ) ) {
			return $body;
		}

		try {
			return FieldMasker::mask( $body, self::response_fields() );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}
}
