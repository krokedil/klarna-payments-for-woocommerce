<?php

namespace Krokedil\Klarna\ExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Parses and validates the Klarna client token.
 */
class ClientTokenParser {
	/**
	 * The KEC settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * ClientTokenParser constructor.
	 *
	 * @param Settings $settings The KEC settings instance.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Parse and validate a Klarna client token.
	 *
	 * @param string $client_token The client token to parse.
	 *
	 * @return array The decoded header and payload.
	 * @throws \Exception If the client token could not be parsed.
	 */
	public function parse( $client_token ) {
		$token_parts = explode( '.', $client_token );

		if ( 3 !== count( $token_parts ) ) {
			throw new \Exception( 'Could not parse client token.' );
		}

		$decoded_client_token = array_map( 'base64_decode', $token_parts );

		if ( in_array( false, $decoded_client_token, true ) ) {
			throw new \Exception( 'Could not parse client token.' );
		}

		$header  = json_decode( $decoded_client_token[0], true );
		$payload = json_decode( $decoded_client_token[1], true );

		if ( ! $header || ! $payload ) {
			throw new \Exception( 'Could not parse client token.' );
		}

		return array(
			'header'  => $header,
			'payload' => $payload,
		);
	}
}
