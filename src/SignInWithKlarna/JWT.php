<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\Klarna\SignInWithKlarna;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\JWK as FirebaseJWK;
use KP_Form_Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Used for working with JWT tokens and their data.
 */
class JWT {

	/**
	 * The Klarna request's base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Klarna JWKS URL.
	 *
	 * @var string
	 */
	private $jwks_url;

	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The regional endpoint (EU v. NA).
	 *
	 * @var string `eu` or `na`.
	 */
	public $region;

	/**
	 * JWKS
	 *
	 * @var array
	 */
	private $jwks;

	/**
	 * Class constructor.
	 *
	 * @param bool     $test_mode Whether to use the test or production endpoint.
	 * @param Settings $settings Settings.
	 */
	public function __construct( $test_mode, $settings ) {
		$environment    = $test_mode ? 'playground.' : '';
		$this->base_url = "https://login.{$environment}klarna.com";
		$this->settings = $settings;

		if ( \class_exists( 'KP_Form_Fields' ) ) {
			$country      = strtolower( kp_get_klarna_country() );
			$country_data = KP_Form_Fields::$kp_form_auto_countries[ $country ] ?? null;
			$endpoint     = empty( $country_data['endpoint'] ) ? 'eu' : 'na';

			/**
			 * Filters the Klarna API region used to build the Sign in with Klarna JWKS URL.
			 *
			 * @param string $region The Klarna API region, derived from the store's country. Either 'eu' or 'na'.
			 */
			$region       = apply_filters( 'klarna_base_region', $endpoint );
			$this->region = strtolower( $region );
		}

		$this->jwks_url = "{$this->base_url}/{$this->region}/lp/idp/.well-known/jwks.json";
	}

	/**
	 * The Klarna request's base URL.
	 *
	 * @return string
	 */
	public function get_base_url() {
		return $this->base_url;
	}

	/**
	 * Validate a JWT token.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|false The validated JWT token as an array or FALSE if invalid.
	 */
	private function is_valid_jwt( $jwt_token ) {
		if ( empty( $this->jwks ) ) {
			$response = wp_remote_get(
				$this->jwks_url,
				array(
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$this->jwks = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		try {
			// An exception is thrown if the token is invalid. Convert the stdClass to associative array.
			return json_decode( wp_json_encode( FirebaseJWT::decode( $jwt_token, FirebaseJWK::parseKeySet( $this->jwks ) ) ), true );
		} catch ( \Exception $e ) {
			// Set to false to ensure new keys are retrieved next time this function is called.
			$this->jwks = false;
			return false;
		}
	}

	/**
	 * Validate and extract the payload only if the JWT token is valid.
	 *
	 * @param string $jwt_token The JWT token.
	 * @return array|\WP_Error A validated JSON decoded JWT token array or WP_Error if invalid.
	 */
	public function get_payload( $jwt_token ) {
		$jwt_token = $this->is_valid_jwt( $jwt_token );
		return empty( $jwt_token ) ? new \WP_Error( 'JWT token invalid.' ) : $jwt_token;
	}
}
