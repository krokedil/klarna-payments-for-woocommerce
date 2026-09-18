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
			// An exception is thrown if the token is invalid.
			$payload = FirebaseJWT::decode( $jwt_token, FirebaseJWK::parseKeySet( $this->jwks ) );

			// Validate the iss claim to ensure the token is from Klarna, and that its for the correct environment.
			if ( ! $this->validate_iss_claim( $payload ) ) {
				return false;
			}

			// Ensure the aud claim matches the Klarna client ID.
			if ( ! $this->validate_aud_claim( $payload ) ) {
				return false;
			}

			// Only trust the email claim if Klarna has verified ownership of it.
			if ( ! $this->validate_email_verified_claim( $payload ) ) {
				return false;
			}

			// Validate that the token has not already been used by seeing if the jti claim exists as a transient.
			if ( ! $this->validate_jti_claim( $payload ) ) {
				return false;
			}

			// Return the payload as an associative array.
			return json_decode( wp_json_encode( $payload ), true );
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

	/**
	 * Validate the JWT tokens aud claim against the Klarna client ID.
	 *
	 * @param \stdClass $payload The JWT token payload.
	 *
	 * @return bool True if the aud claim matches the Klarna client ID, false otherwise.
	 */
	public function validate_aud_claim( $payload ) {
		$client_id = kp_get_client_id();
		$aud       = $payload->aud ?? null;

		// If the aud claim is empty, return false.
		if ( empty( $aud ) ) {
			return false;
		}

		// If we get an array of audiences, check if the client ID is in the array.
		if ( is_array( $aud ) ) {
			return in_array( $client_id, $aud, true );
		}

		// If we get a single audience, check if it matches the client ID.
		return $aud === $client_id;
	}

	/**
	 * Validate that Klarna has verified ownership of the email claim.
	 *
	 * The sign-in flow matches existing accounts by email, so an unverified email
	 * would let a caller link to an account they do not own.
	 *
	 * @param \stdClass $payload The JWT token payload.
	 *
	 * @return bool True if the email is verified, false otherwise.
	 */
	public function validate_email_verified_claim( $payload ) {
		// Accept both a JSON boolean and a string ("true"/"false") representation.
		return filter_var( $payload->email_verified ?? false, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Validate the JWT token's iss claim to ensure it is from Klarna and for the correct environment.
	 *
	 * @param \stdClass $payload The JWT token payload.
	 *
	 * @return bool True if the iss claim is valid, false otherwise.
	 */
	public function validate_iss_claim( $payload ) {
		$expected_iss = $this->base_url;
		$iss          = $payload->iss ?? null;

		if ( empty( $iss ) ) {
			return false;
		}

		return $iss === $expected_iss;
	}
	/**
	 * Validate the JWT token's jti claim to ensure it has not already been used.
	 *
	 * @param \stdClass $payload The JWT token payload.
	 *
	 * @return bool True if the jti claim is valid, false otherwise.
	 */
	private function validate_jti_claim( $payload ) {
		$jti = $payload->jti ?? null;

		if ( empty( $jti ) ) {
			return false;
		}

		// Check if the jti claim exists as a transient.
		if ( false !== get_transient( "siwk_used_{$jti}" ) ) {
			return false;
		}

		$fallback_exp = time() + MINUTE_IN_SECONDS * 5; // 5 minutes from now as the fallback expiration time if one is missing.

		// Set the jti claim as a transient. We only need to store it until the token expires.
		set_transient( "siwk_used_{$jti}", true, ( $payload->exp ?? $fallback_exp ) - time() );

		return true;
	}
}
