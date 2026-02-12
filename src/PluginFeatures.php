<?php
namespace Krokedil\Klarna;

/**
 * Class for handling the plugin features and their availability based on the plugins API request.
 */
class PluginFeatures {
	/**
	 * List of all features and their default availability.
	 *
	 * @var array
	 */
	protected $default_features = array(
		Features::PAYMENTS                    => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::OSM_PRODUCT_PAGE            => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::OSM_CART_PAGE               => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::OSM_PROMOTIONAL_BANNER      => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::KEC_ONE_STEP                => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::KEC_TWO_STEP                => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::SIWK_ACCOUNT_CREATION_PAGE  => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::SIWK_AUTHENTICATION_PAGE    => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::SIWK_CART_PAGE              => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
		Features::SUPPLEMENTARY_PURCHASE_DATA => array(
			'availability'  => true,
			'markets'       => array(),
			'available_for' => array(),
		),
	);

	/**
	 * List of all features and their availability.
	 *
	 * @var array
	 */
	protected $features = array();

	/**
	 * If the credentials we have parsed had a AP key or not.
	 * Only used when processing all API credentials, and can not be used
	 * to determine if we have an AP key or not in general.
	 *
	 * @var bool
	 */
	protected $check_has_acquiring_partner_key = false;

	/**
	 * Initialize the features and their availability.
	 *
	 * @param bool $force Whether to force re-initialization even if it has already been initialized once during this request. Default false.
	 *
	 * @return void
	 */
	public function init_features( $force = false ) {
		// Ensure we only initialize once per request.
		if ( ! $force && did_action( 'kp_plugin_features_initialized' ) ) {
			return;
		}

		// Maybe migrate the legacy unavailable features option.
		$this->maybe_migrate_legacy_unavailable_features();

		// Get the features from the options.
		$features = get_option( 'kp_plugin_features', $this->default_features );

		// Initialize the features.
		$this->features = $features;

		do_action( 'kp_plugin_features_initialized', $this->features );
	}

	/**
	 * Get the features and their availability.
	 *
	 * @return array The features and their availability.
	 */
	public function get_features() {
		return $this->features;
	}

	/**
	 * Process the response from the plugins api request, and store the available and unavailable features.
	 *
	 * @param array $response The response from the plugins API request.
	 * @param array $credentials The credentials used for the request.
	 * @param array $features The current features array to update. Passed by reference.
	 *
	 * @return void
	 */
	public function process_api_response( $response, $credentials, &$features ) {
		foreach ( $response['features'] ?? array() as $feature ) {
			$key           = str_replace( 'platform-plugin-', '', $feature['feature_key'] ?? '' );
			$availability  = $feature['availability'] ?? 'AVAILABLE'; // Default to AVAILABLE if not set.
			$markets       = $feature['markets'] ?? array();
			$available_for = $features[ $key ]['available_for'] ?? array(); // Get the existing available_for list from the saved features.

			// Add or remove the country code from the available_for list based on availability.
			if ( 'AVAILABLE' === $availability ) {
				// If it is available, add the country code if not already present.
				if ( ! in_array( $credentials['country_code'] ?? 'unknown', $available_for, true ) ) {
					$available_for[] = $credentials['country_code'] ?? 'unknown';
				}
			} elseif ( in_array( $credentials['country_code'] ?? 'unknown', $available_for, true ) ) {
				// If it is unavailable, remove the country code if present.
				$available_for = array_diff( $available_for, array( $credentials['country_code'] ?? 'unknown' ) );
			}

			// If the feature exists already, and was available before, we should not override it to unavailable.
			if ( isset( $features[ $key ] ) && $features[ $key ]['availability'] ) {
				$availability = 'AVAILABLE';
			}

			// If we have markets, merge them with the existing ones and make them unique.
			if ( ! empty( $markets ) ) {
				$features[ $key ]['markets'] = array_unique( array_merge( $features[ $key ]['markets'] ?? array(), $markets ) );
			}

			// Update the feature availability and available_for list. Ensure available_for is uppercase.
			$features[ $key ]['availability']  = 'AVAILABLE' === $availability;
			$features[ $key ]['available_for'] = array_map( 'strtoupper', $available_for );
		}

		// Store the acquiring_partner_key if present in the response.
		if ( isset( $response['acquiring_partner_key'] ) && ! empty( $response['acquiring_partner_key'] ) ) {
			$this->check_has_acquiring_partner_key = true;
			update_option( 'klarna_acquiring_partner_key', $response['acquiring_partner_key'] );
		}
	}

	/**
	 * Get the availability of all credentials stored in the settings,
	 * and update the option kp_plugin_features with the result.
	 *
	 * @throws \WP_Exception If there is an error when trying to get the feature availability from Klarna.
	 * @return void
	 */
	public function process_all_api_credentials() {
		try {
			$features        = array();
			$api_credentials = $this->get_api_credentials();

			foreach ( $api_credentials as $credentials ) {
				$response = KP_WC()->api->get_unavailable_features( $credentials );

				// If we get an error, throw an error and reset the features to default.
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					throw new \WP_Exception(
						sprintf(
							// translators: %s: The error message from the API request.
							__( 'There was an error when trying to get the feature availability from Klarna. Please check your API credentials and try again. Error: %s', 'klarna-payments-for-woocommerce' ),
							$error_message
						)
					);
				}
				$this->process_api_response( $response, $credentials, $features );
			}
		} catch ( \WP_Exception $e ) {
			// If we get an error, reset the features to default and log the error.
			$features = $this->default_features;
			KP_WC()->logger()->error( 'Error when trying to get the feature availability from Klarna: ' . $e->getMessage() );
		} finally {
			// If we did not have an acquiring partner key from the processed credentials, ensure we delete any existing one to ensure we do not have a stale key.
			if ( ! $this->check_has_acquiring_partner_key ) {
				delete_option( 'klarna_acquiring_partner_key' );
			}

			// Update the features option.
			update_option( 'kp_plugin_features', array_merge( $this->default_features, $features ) );
			// Re-initialize the features.
			$this->init_features( true );
		}
	}

	/**
	 * Test a specific set of credentials and get the result of the feature availability.
	 *
	 * @param array $credentials The API credentials to test.
	 *
	 * @throws \WP_Exception If there is an error when trying to get the feature availability from Klarna.
	 * @return array
	 */
	public function process_api_credentials( $credentials ) {
		try {
			// Use the stored features as a base since we might have other features that are available from other credentials.
			$features = $this->features;
			$response = KP_WC()->api->get_unavailable_features( $credentials );

			// If we get an error, throw an error and reset the features to default.
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				throw new \WP_Exception(
					sprintf(
						// translators: %s: The error message from the API request.
						__( 'There was an error when trying to get the feature availability from Klarna. Please check your API credentials and try again. Error: %s', 'klarna-payments-for-woocommerce' ),
						$error_message
					)
				);
			}

			$this->process_api_response( $response, $credentials, $features );
		} catch ( \WP_Exception $e ) {
			// Log the error.
			KP_WC()->logger()->error( 'Error when trying to get the feature availability from Klarna: ' . $e->getMessage() );
		} finally {
			// Return the features as the result.
			return $features;
		}
	}

	/**
	 * Get the sections for the settings page that should be hidden based on unavailable features from the availability.
	 *
	 * @param array $features The features array. From either the settings or from process_api_credentials.
	 *
	 * @return string[] The list of sections to hide.
	 */
	public static function get_sections_to_hide( $features ) {
		$sections_to_hide = array();
		foreach ( $features as $feature_key => $feature_data ) {
			$new_hidden_sections = array();

			switch ( $feature_key ) {
				case Features::PAYMENTS:
				case Features::PAYMENTS_RECURRING:
					$new_hidden_sections[] = 'general';
					$new_hidden_sections[] = 'kom';
					break;
				case Features::OSM_PRODUCT_PAGE:
				case Features::OSM_CART_PAGE:
				case Features::OSM_PROMOTIONAL_BANNER:
					$new_hidden_sections[] = 'onsite_messaging';
					break;
				case Features::KEC_ONE_STEP:
				case Features::KEC_TWO_STEP:
					$new_hidden_sections[] = 'kec_settings';
					break;
				case Features::SIWK_ACCOUNT_CREATION_PAGE:
				case Features::SIWK_AUTHENTICATION_PAGE:
				case Features::SIWK_CART_PAGE:
					$new_hidden_sections[] = 'siwk';
					break;
			}

			// If the feature is available, ensure it is not added to the unavailable sections.
			if ( $feature_data['availability'] && ! empty( array_intersect( $new_hidden_sections, $sections_to_hide ) ) ) {
				$sections_to_hide = array_diff( $sections_to_hide, $new_hidden_sections );
			}

			// Add it to the unavailable sections if not available.
			if ( ! $feature_data['availability'] ) {
				$sections_to_hide = array_merge( $sections_to_hide, $new_hidden_sections );
				$sections_to_hide = array_unique( $sections_to_hide );
			}
		}

		return $sections_to_hide;
	}

	/**
	 * Check if a feature is available or not.
	 *
	 * @param string|array $feature_key The feature(s) key to test.
	 * @param string|null  $country_code The country code to test for. Optional. If not passed we will check general availability instead.
	 *
	 * @return bool True if the feature(s) is available, false otherwise. If an array is passed, if any feature is available, true is returned.
	 */
	public static function is_available( $feature_key, $country_code = null ) {
		if ( is_array( $feature_key ) ) {
			foreach ( $feature_key as $key ) {
				if ( KP_WC()->plugin_features()->is_feature_available( $key, $country_code ) ) {
					return true;
				}
			}
			return false;
		}

		return KP_WC()->plugin_features()->is_feature_available( $feature_key, $country_code );
	}

	/**
	 * Get the acquiring partner key.
	 *
	 * @return string|null The acquiring partner key, or null if not set.
	 */
	public static function get_acquiring_partner_key() {
		return get_option( 'klarna_acquiring_partner_key', null );
	}

	/**
	 * Check if a feature is available or not. Private method called by the static method is_available.
	 *
	 * @param string      $feature_key The feature key to test.
	 * @param string|null $country_code The country code to test for. Optional. If not passed we will check general availability instead.
	 *
	 * @return bool True if the feature is available, false otherwise.
	 */
	private function is_feature_available( $feature_key, $country_code = null ) {
		// If the country code is set, check if the feature is available for that country.
		if ( $country_code ) {
			// Ensure the country code is uppercase for comparison, since it will always be stored as uppercase.
			return isset( $this->features[ $feature_key ] ) && in_array( strtoupper( $country_code ), $this->features[ $feature_key ]['available_for'], true );
		}
		return isset( $this->features[ $feature_key ] ) && $this->features[ $feature_key ]['availability'];
	}

	/**
	 * Get the API credentials that have a value set in the settings.
	 *
	 * @return array The API credentials.
	 */
	private function get_api_credentials() {
		$settings        = get_option( 'woocommerce_klarna_payments_settings', array() );
		$country_codes   = array_keys( \KP_Form_Fields::available_countries() );
		$combined_eu     = wc_string_to_bool( $settings['combine_eu_credentials'] ?? 'no' );
		$testmode        = wc_string_to_bool( $settings['testmode'] ?? 'no' );
		$api_credentials = array();

		// If combined eu credentials is active, filter out any EU countries from the list of countries to check, and add 'eu' instead.
		if ( $combined_eu ) {
			$eu_countries    = array_keys( \KP_Form_Fields::available_countries( 'eu' ) );
			$country_codes   = array_diff( $country_codes, $eu_countries );
			$country_codes[] = 'eu';
		}

		// Get the credentials for each country, and add them to the list if they are set.
		foreach ( $country_codes as $cc ) {
			$merchant_id   = $testmode ? $settings[ "test_merchant_id_$cc" ] ?? '' : $settings[ "merchant_id_$cc" ] ?? '';
			$shared_secret = $testmode ? $settings[ "test_shared_secret_$cc" ] ?? '' : $settings[ "shared_secret_$cc" ] ?? '';

			// If the merchant id or shared secret is empty, skip this country.
			if ( empty( $merchant_id ) || empty( $shared_secret ) ) {
				continue;
			}

			$api_credentials[] = array(
				'country_code'  => $cc,
				'merchant_id'   => $merchant_id,
				'shared_secret' => $shared_secret,
				'mode'          => $testmode ? 'test' : 'live',
			);
		}

		return $api_credentials;
	}

	/**
	 * Migrate the legacy option kp_unavailable_feature_ids to the new format in kp_plugin_features.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_unavailable_features() {
		$legacy_unavailable_features = get_option( 'kp_unavailable_feature_ids', array() );

		// If the legacy option is empty, or not an array, we have nothing to migrate.
		if ( empty( $legacy_unavailable_features ) || ! is_array( $legacy_unavailable_features ) ) {
			return;
		}

		// Get the current features.
		$features = get_option( 'kp_plugin_features', $this->default_features );

		// Loop through the legacy unavailable features, and set their availability to false in the new features array.
		foreach ( $legacy_unavailable_features as $feature_key ) {
			$keys_for_unavailable_legacy = self::convert_legacy_feature_key( $feature_key );
			foreach ( $keys_for_unavailable_legacy as $key ) {
				if ( isset( $features[ $key ] ) ) {
					$features[ $key ]['availability'] = false;
				}
			}
		}
		// Update the new features option.
		update_option( 'kp_plugin_features', $features );

		// Delete the legacy option.
		delete_option( 'kp_unavailable_feature_ids' );
	}

	/**
	 * Convert legacy feature values.
	 *
	 * @param string $legacy_key The legacy feature key.
	 *
	 * @return string[] The new feature keys.
	 */
	public static function convert_legacy_feature_key( $legacy_key ) {
		switch ( $legacy_key ) {
			case 'general':
			case 'kom':
				return array( Features::PAYMENTS );
			case 'onsite_messaging':
				return array( Features::OSM_CART_PAGE, Features::OSM_PRODUCT_PAGE, Features::OSM_PROMOTIONAL_BANNER );
			case 'kec_settings':
				return array( Features::KEC_ONE_STEP, Features::KEC_TWO_STEP );
			case 'siwk':
				return array( Features::SIWK_ACCOUNT_CREATION_PAGE, Features::SIWK_AUTHENTICATION_PAGE );
			default:
				return array();
		}
	}
}
