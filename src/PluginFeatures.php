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
	protected $default_features = [
		Features::PAYMENTS                    => [ "availability" => true, "markets" => [] ],
		Features::OSM_PRODUCT_PAGE            => [ "availability" => true, "markets" => [] ],
		Features::OSM_CART_PAGE               => [ "availability" => true, "markets" => [] ],
		Features::OSM_PROMOTIONAL_BANNER      => [ "availability" => true, "markets" => [] ],
		Features::KEC_ONE_STEP                => [ "availability" => true, "markets" => [] ],
		Features::KEC_TWO_STEP                => [ "availability" => true, "markets" => [] ],
		Features::SIWK_ACCOUNT_CREATION_PAGE  => [ "availability" => true, "markets" => [] ],
		Features::SIWK_AUTHENTICATION_PAGE    => [ "availability" => true, "markets" => [] ],
		Features::SIWK_CART_PAGE              => [ "availability" => true, "markets" => [] ],
		Features::SUPPLEMENTARY_PURCHASE_DATA => [ "availability" => true, "markets" => [] ],
	];

	/**
	 * List of all features and their availability.
	 *
	 * @var array
	 */
	protected $features = [];

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// Initialize features early early to be available for other classes.
		add_action( 'init', [ $this, 'init_features' ], 5 );
	}

	/**
	 * Initialize the features and their availability.
	 *
	 * @return void
	 */
	public function init_features() {
		// Maybe migrate the legacy unavailable features option.
		$this->maybe_migrate_legacy_unavailable_features();

		// Get the features from the options.
		$features = get_option( 'kp_plugin_features', $this->default_features );

		// Initialize the features.
		$this->features = $features;
	}

	/**
	 * Process the response from the plugins api request, and store the available and unavailable features.
	 *
	 * @param array $response The response from the plugins API request.
	 * @param array $features The current features array to update. Passed by reference.
	 *
	 * @return void
	 */
	public function process_api_response( $response, &$features ) {
		foreach( $response['features'] ?? [] as $feature ) {
			$key          = str_replace( 'platform-plugin-', '', $feature['feature_key'] ?? '' );
			$availability = $feature['availability'] ?? 'AVAILABLE'; // Default to AVAILABLE if not set
			$markets      = $feature['markets'] ?? [];

			// If the feature exists already, and was available before, we should not override it to unavailable.
			if ( isset( $features[ $key ] ) && $features[ $key ]['availability'] ) {
				$availability = 'AVAILABLE';
			}

			// If we have markets, merge them with the existing ones and make them unique.
			if ( ! empty( $markets ) ) {
				$features[ $key ]['markets'] = array_unique ( array_merge ( $features[ $key ]['markets'] ?? [], $markets));
			}

			$features[ $key ]['availability'] = $availability === 'AVAILABLE';
		}
	}

	/**
	 * Get the availability of all credentials stored in the settings,
	 * and update the option kp_plugin_features with the result.
	 *
	 * @return void
	 */
	public function process_all_api_credentials() {
		try {
			$features        = [];
			$api_credentials = $this->get_api_credentials();

			foreach ( $api_credentials as $credential ) {
				$response = KP_WC()->api->get_unavailable_features( $credential );

				// If we get an error, throw an error and reset the features to default.
				if ( is_wp_error( $response ) ) {
					throw new \WP_Exception( __( 'There was an error when trying to get the feature availability from Klarna. Please check your API credentials and try again. Error: ' . $response->get_error_message(), 'klarna-payments-for-woocommerce' ) );
				}

				$this->process_api_response( $response, $features );
			}
		} catch ( \WP_Exception $e ) {
			// If we get an error, reset the features to default and log the error.
			$features = $this->default_features;
			KP_WC()->logger()->error( 'Error when trying to get the feature availability from Klarna: ' . $e->getMessage() );
		} finally {
			// Update the features option.
			update_option( 'kp_plugin_features', array_merge( $this->default_features, $features ) );
			// Re-initialize the features.
			$this->init_features();
		}
	}

	/**
	 * Check if a feature is available or not.
	 *
	 * @param string|array $feature_key The feature(s) key to test.
	 *
	 * @return bool True if the feature(s) is available, false otherwise. If an array is passed, if any feature is available, true is returned.
	 */
	public static function is_available( $feature_key ) {
		if ( is_array( $feature_key ) ) {
			foreach ( $feature_key as $key ) {
				if ( KP_WC()->plugin_features()->is_feature_available( $key ) ) {
					return true;
				}
			}
			return false;
		}

		return KP_WC()->plugin_features()->is_feature_available( $feature_key );
	}

	/**
	 * Check if a feature is available or not. Private method called by the static method is_available.
	 *
	 * @param string $feature_key The feature key to test.
	 *
	 * @return bool True if the feature is available, false otherwise.
	 */
	private function is_feature_available( $feature_key ) {
		return isset( $this->features[ $feature_key ] ) && $this->features[ $feature_key ]['availability'];
	}

	/**
	 * Get the API credentials that have a value set in the settings.
	 *
	 * @return array The API credentials.
	 */
	private function get_api_credentials() {
		$settings        = get_option( 'woocommerce_klarna_payments_settings', [] );
		$country_codes   = array_keys( \KP_Form_Fields::available_countries() );
		$combined_eu     = wc_string_to_bool( $settings['combine_eu_credentials'] ?? 'no' );
		$testmode        = wc_string_to_bool( $settings['testmode'] ?? 'no' );
		$api_credentials = [];

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

			$api_credentials[] = [
				'country_code'  => $cc,
				'merchant_id'   => $merchant_id,
				'shared_secret' => $shared_secret,
				'mode'          => $testmode ? 'test' : 'live',
			];
		}

		return $api_credentials;
	}

	/**
	 * Migrate the legacy option kp_unavailable_feature_ids to the new format in kp_plugin_features.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_unavailable_features() {
		$legacy_unavailable_features = get_option( 'kp_unavailable_feature_ids', [] );

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
				return [ Features::PAYMENTS ];
			case 'onsite_messaging':
				return [ Features::OSM_CART_PAGE, Features::OSM_PRODUCT_PAGE, Features::OSM_PROMOTIONAL_BANNER ];
			case 'kec_settings':
				return [ Features::KEC_ONE_STEP, Features::KEC_TWO_STEP ];
			case 'siwk':
				return [ Features::SIWK_ACCOUNT_CREATION_PAGE, Features::SIWK_AUTHENTICATION_PAGE ];
			default:
				return [];
		}
	}

}
