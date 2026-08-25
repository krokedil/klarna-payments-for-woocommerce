<?php
namespace Krokedil\Klarna\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves which set of Klarna API credentials to use for a purchase.
 *
 * The market is where the customer is. The credentials country is which credential set signs the request. They
 * differ only when Klarna has granted a credential set the cross border add on.
 */
class ApiCredentialsUtility {
	/**
	 * The country code used for the combined EU credentials.
	 */
	const EU_COUNTRY_CODE = 'eu';

	/**
	 * Request scoped copy of the plugin settings.
	 *
	 * Resolving credentials reads the settings many times per request, so they are kept here and discarded
	 * whenever the option is written. See flush_cache().
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Request scoped copy of the markets any credential set can serve.
	 *
	 * @var array|null
	 */
	private static $serviceable_markets = null;

	/**
	 * Register the hooks that keep the cached settings from going stale.
	 *
	 * @return void
	 */
	public static function register_cache_invalidation() {
		foreach ( array( 'woocommerce_klarna_payments_settings', 'kp_credential_capabilities' ) as $option ) {
			add_action( "add_option_{$option}", array( self::class, 'flush_cache' ) );
			add_action( "update_option_{$option}", array( self::class, 'flush_cache' ) );
			add_action( "delete_option_{$option}", array( self::class, 'flush_cache' ) );
		}

		// The settings of one site in a network say nothing about the next.
		add_action( 'switch_blog', array( self::class, 'flush_cache' ) );
	}

	/**
	 * Discard the cached settings and everything derived from them.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$settings            = null;
		self::$serviceable_markets = null;
	}

	/**
	 * Get the settings for the plugin.
	 *
	 * @return array The plugin settings.
	 */
	public static function get_plugin_settings() {
		if ( null === self::$settings ) {
			self::$settings = get_option( 'woocommerce_klarna_payments_settings', array() );
		}

		return self::$settings;
	}

	/**
	 * Is the Klarna Payments plugin in test mode?
	 *
	 * @return bool True if the plugin is in test mode, false otherwise.
	 */
	public static function is_test_mode() {
		return wc_string_to_bool( self::get_plugin_settings()['testmode'] ?? 'no' );
	}

	/**
	 * Are the EU credentials combined into a single credential set?
	 *
	 * @return bool True if the EU credentials are combined, false otherwise.
	 */
	public static function is_eu_credentials_combined() {
		return wc_string_to_bool( self::get_plugin_settings()['combine_eu_credentials'] ?? 'no' );
	}

	/**
	 * Get the settings key for an API credential field.
	 *
	 * @param string    $field The field to get the settings key for. Either 'merchant_id', 'shared_secret' or 'client_id'.
	 * @param string    $country_code The country code to get the settings key for.
	 * @param bool|null $combined_eu True for the combined EU key. If null the current combined EU setting is used.
	 *
	 * @return string The settings key for the specified API credential field.
	 */
	public static function get_credentials_setting_key( $field, $country_code, $combined_eu = null ) {
		$country_code = strtolower( (string) $country_code );

		if ( is_null( $combined_eu ) ) {
			$combined_eu = self::is_eu_credentials_combined();
		}

		if ( $combined_eu && in_array( $country_code, self::get_eu_country_codes(), true ) ) {
			$country_code = self::EU_COUNTRY_CODE;
		}

		$testmode_prefix = self::is_test_mode() ? 'test_' : '';

		return "{$testmode_prefix}{$field}_{$country_code}";
	}

	/**
	 * Get the merchant ID (API username) from the settings.
	 *
	 * @param string    $country_code The country code to get the merchant ID for.
	 * @param bool|null $combined_eu True for the combined EU merchant ID. If null the current combined EU setting is used.
	 *
	 * @return string The merchant ID, or an empty string if it is not set.
	 */
	public static function get_merchant_id( $country_code, $combined_eu = null ) {
		$setting_key = self::get_credentials_setting_key( 'merchant_id', $country_code, $combined_eu );

		return self::get_plugin_settings()[ $setting_key ] ?? '';
	}

	/**
	 * Get the shared secret (API password) from the settings.
	 *
	 * @param string    $country_code The country code to get the shared secret for.
	 * @param bool|null $combined_eu True for the combined EU shared secret. If null the current combined EU setting is used.
	 *
	 * @return string The shared secret, or an empty string if it is not set.
	 */
	public static function get_shared_secret( $country_code, $combined_eu = null ) {
		$setting_key = self::get_credentials_setting_key( 'shared_secret', $country_code, $combined_eu );

		return self::get_plugin_settings()[ $setting_key ] ?? '';
	}

	/**
	 * Get the client ID used by the Klarna Web SDK from the settings.
	 *
	 * @param string    $country_code The country code to get the client ID for.
	 * @param bool|null $combined_eu True for the combined EU client ID. If null the current combined EU setting is used.
	 *
	 * @return string The client ID, or an empty string if it is not set.
	 */
	public static function get_client_id( $country_code, $combined_eu = null ) {
		$setting_key = self::get_credentials_setting_key( 'client_id', $country_code, $combined_eu );

		return self::get_plugin_settings()[ $setting_key ] ?? '';
	}

	/**
	 * Get the Klarna API region for a credential set, used to build the API base URL.
	 *
	 * @param string $country_code The settings country code of the credential set.
	 *
	 * @return string The region, for example '-na'. Blank for the European region.
	 */
	public static function get_region( $country_code ) {
		$country_code = strtolower( (string) $country_code );

		if ( self::EU_COUNTRY_CODE === $country_code ) {
			return '';
		}

		return \KP_Form_Fields::$kp_form_auto_countries[ $country_code ]['endpoint'] ?? '';
	}

	/**
	 * Get the settings country code of the credential set that owns a market.
	 *
	 * @param string $market The market (customer country) code.
	 *
	 * @return string The settings country code of the credential set.
	 */
	public static function get_credentials_country_for_market( $market ) {
		$market = strtolower( (string) $market );

		if ( self::is_eu_credentials_combined() && in_array( $market, self::get_eu_country_codes(), true ) ) {
			return self::EU_COUNTRY_CODE;
		}

		return $market;
	}

	/**
	 * Resolve the credentials to use for a market and currency.
	 *
	 * @param string      $market The market (customer country) code.
	 * @param string|null $currency The purchase currency. If null the current store currency is used.
	 *
	 * @return array|\WP_Error The credentials, or a WP_Error explaining why the purchase cannot be served. On
	 *                         success the array contains 'country_code', 'merchant_id', 'shared_secret' and
	 *                         'client_id'.
	 */
	public static function resolve( $market, $currency = null ) {
		$market   = strtolower( (string) $market );
		$currency = strtoupper( (string) ( $currency ?? get_woocommerce_currency() ) );

		// Some call sites and order meta pass 'eu' instead of a market. There is no market to validate then.
		if ( self::EU_COUNTRY_CODE === $market ) {
			return self::build_credentials( self::EU_COUNTRY_CODE, $market, $currency );
		}

		$market_values = \KP_Form_Fields::$kp_form_auto_countries[ $market ] ?? null;

		if ( empty( $market_values ) ) {
			return new \WP_Error( 'kp_unsupported_market', "Country ({$market}) is not supported by Klarna Payments." );
		}

		// The "available countries" setting is not checked here. It decides where the gateway is offered, and is
		// checked there, so disabling a market cannot break order management or renewals for existing purchases.
		$home_country = self::get_credentials_country_for_market( $market );

		// The credential set owning this market, if it accepts the currency.
		if ( self::has_credentials( $home_country ) && self::accepts_currency( $home_country, $currency, $market_values['currency'] ) ) {
			return self::build_credentials( $home_country, $market, $currency );
		}

		// Otherwise any credential set granted both this market and this currency.
		$foreign_country = self::find_foreign_credentials( $market, $currency );

		if ( ! empty( $foreign_country ) ) {
			return self::build_credentials( $foreign_country, $market, $currency );
		}

		if ( ! self::has_credentials( $home_country ) ) {
			return new \WP_Error( 'kp_missing_credentials', "No credentials found for {$home_country}." );
		}

		return new \WP_Error(
			'kp_currency_not_allowed',
			"{$market_values['currency']} must be used for {$market_values['name']} purchases, or the credentials must be allowed to settle in {$currency}."
		);
	}

	/**
	 * Resolve the credentials to use for an API request.
	 *
	 * Not every request is a purchase, so unlike resolve() a currency the market cannot settle in is no reason
	 * to refuse to authenticate. The gateway checks the currency separately before a purchase can be made.
	 *
	 * @param string      $market The market (customer country) code.
	 * @param string|null $currency The purchase currency. If null the current store currency is used.
	 *
	 * @return array|\WP_Error The credentials, or a WP_Error if the market has none.
	 */
	public static function resolve_for_request( $market, $currency = null ) {
		$credentials = self::resolve( $market, $currency );

		if ( is_wp_error( $credentials ) && 'kp_currency_not_allowed' === $credentials->get_error_code() ) {
			return self::get_credentials( $market );
		}

		return $credentials;
	}

	/**
	 * Get the credentials for a known credential set, skipping resolution.
	 *
	 * For requests about an existing Klarna order, where the credential set is already known.
	 *
	 * @param string $country_code The settings country code of the credential set, for example 'se' or 'eu'.
	 *
	 * @return array|\WP_Error The credentials, or a WP_Error if they are not configured.
	 */
	public static function get_credentials( $country_code ) {
		// Map through the combined EU setting, so orders made before it was enabled still resolve.
		$country_code = self::get_credentials_country_for_market( $country_code );

		return self::build_credentials( $country_code, $country_code, strtoupper( get_woocommerce_currency() ) );
	}

	/**
	 * Get the markets that any configured credential set can serve.
	 *
	 * @return string[] Uppercase country codes.
	 */
	public static function get_serviceable_markets() {
		if ( null !== self::$serviceable_markets ) {
			return self::$serviceable_markets;
		}

		$markets = array();
		$mode    = self::is_test_mode() ? 'test' : 'live';

		foreach ( self::get_capabilities() as $country_code => $capability ) {
			if ( ! empty( $capability['mode'] ) && $mode !== $capability['mode'] ) {
				continue;
			}

			if ( empty( $capability['markets'] ) || ! self::has_credentials( $country_code ) ) {
				continue;
			}

			$markets = array_merge( $markets, $capability['markets'] );
		}

		self::$serviceable_markets = array_values( array_unique( $markets ) );

		return self::$serviceable_markets;
	}

	/**
	 * Get the EU country codes, without the translated names that available_countries also builds.
	 *
	 * @return string[] Lowercase country codes.
	 */
	private static function get_eu_country_codes() {
		return array_keys( \KP_Form_Fields::available_countries( 'eu' ) );
	}

	/**
	 * Are both the merchant ID and shared secret set for a credential set?
	 *
	 * @param string $country_code The settings country code of the credential set.
	 *
	 * @return bool True if the credential set is fully configured.
	 */
	private static function has_credentials( $country_code ) {
		// combined_eu is false since the country code is already resolved to a credential set.
		return ! empty( self::get_merchant_id( $country_code, false ) )
			&& ! empty( self::get_shared_secret( $country_code, false ) );
	}

	/**
	 * Can a credential set settle a purchase in a currency?
	 *
	 * @param string $country_code The settings country code of the credential set.
	 * @param string $currency The uppercase purchase currency.
	 * @param string $market_currency The default currency of the market being served.
	 *
	 * @return bool True if the currency is accepted.
	 */
	private static function accepts_currency( $country_code, $currency, $market_currency ) {
		if ( strtoupper( $market_currency ) === $currency ) {
			return true;
		}

		// Anything but the markets own currency has to be granted by Klarna.
		$capabilities = self::get_capabilities();

		return in_array( $currency, $capabilities[ strtolower( $country_code ) ]['currencies'] ?? array(), true );
	}

	/**
	 * Find a credential set from another country that Klarna granted both this market and this currency.
	 *
	 * @param string $market The market (customer country) code.
	 * @param string $currency The uppercase purchase currency.
	 *
	 * @return string The settings country code of the credential set, or an empty string if there is none.
	 */
	private static function find_foreign_credentials( $market, $currency ) {
		$market_code = strtoupper( $market );
		$mode        = self::is_test_mode() ? 'test' : 'live';
		$candidates  = array();

		foreach ( self::get_capabilities() as $country_code => $capability ) {
			// Never mix test and live credentials.
			if ( ! empty( $capability['mode'] ) && $mode !== $capability['mode'] ) {
				continue;
			}

			if ( ! in_array( $market_code, $capability['markets'] ?? array(), true ) ) {
				continue;
			}

			if ( ! in_array( $currency, $capability['currencies'] ?? array(), true ) ) {
				continue;
			}

			// The capabilities can be older than the settings, so the credentials may have been removed.
			if ( ! self::has_credentials( $country_code ) ) {
				continue;
			}

			$candidates[] = $country_code;
		}

		// Prefer the same API region as the market, then sort by code so the pick is stable across requests.
		$market_region = self::get_region( $market );

		usort(
			$candidates,
			function ( $a, $b ) use ( $market_region ) {
				$a_same_region = self::get_region( $a ) === $market_region ? 1 : 0;
				$b_same_region = self::get_region( $b ) === $market_region ? 1 : 0;

				if ( $a_same_region !== $b_same_region ) {
					return $b_same_region - $a_same_region;
				}

				return strcmp( $a, $b );
			}
		);

		return $candidates[0] ?? '';
	}

	/**
	 * Build the credentials array for a credential set.
	 *
	 * @param string $country_code The settings country code of the credential set.
	 * @param string $market The market (customer country) code the credentials were resolved for.
	 * @param string $currency The uppercase purchase currency the credentials were resolved for.
	 *
	 * @return array|\WP_Error The credentials, or a WP_Error if they turned out not to be configured.
	 */
	private static function build_credentials( $country_code, $market, $currency ) {
		$country_code = strtolower( (string) $country_code );

		// combined_eu is false since the country code is already resolved to a credential set.
		$credentials = array(
			'country_code'  => $country_code,
			'merchant_id'   => self::get_merchant_id( $country_code, false ),
			'shared_secret' => self::get_shared_secret( $country_code, false ),
			'client_id'     => self::get_client_id( $country_code, false ),
		);

		if ( empty( $credentials['merchant_id'] ) || empty( $credentials['shared_secret'] ) ) {
			return new \WP_Error( 'kp_missing_credentials', "No credentials found for {$country_code}." );
		}

		/**
		 * Filters the Klarna API credentials resolved for a market and currency.
		 *
		 * The 'country_code' also picks the API region and client ID, and is stored on the order for order
		 * management. Change the merchant ID and shared secret to match it, or requests will fail to authenticate.
		 *
		 * @param array  $credentials The resolved credentials.
		 * @param string $market The market (customer country) code the credentials were resolved for.
		 * @param string $currency The purchase currency the credentials were resolved for.
		 */
		return apply_filters( 'kp_resolved_credentials', $credentials, $market, $currency );
	}

	/**
	 * Get the stored capabilities for every credential set.
	 *
	 * @return array The capabilities, keyed by settings country code.
	 */
	private static function get_capabilities() {
		$plugin_features = function_exists( 'KP_WC' ) ? KP_WC()->plugin_features() : null;

		if ( empty( $plugin_features ) ) {
			return array();
		}

		return $plugin_features->get_credential_capabilities();
	}
}
