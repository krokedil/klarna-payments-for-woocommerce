<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Krokedil\Klarna\Utilities\ApiCredentialsUtility;

/**
 * Store-level fixtures for the Integration suite: base location, currency, tax
 * options, tax rates and the Klarna Payments settings option.
 */
trait CanConfigureStore {

	/** The Klarna Payments settings option name. */
	public const KP_SETTINGS_OPTION = 'woocommerce_klarna_payments_settings';

	/** Applies a store configuration. */
	protected function configureStore( array $args = [] ): void {
		$args = array_merge(
			[
				'country'            => 'SE',
				'currency'           => 'SEK',
				'calc_taxes'         => true,
				'prices_include_tax' => false,
				'tax_based_on'       => 'billing',
				'ship_to_countries'  => '',
			],
			$args
		);

		update_option( 'woocommerce_default_country', $args['country'] );
		update_option( 'woocommerce_currency', $args['currency'] );
		update_option( 'woocommerce_calc_taxes', $args['calc_taxes'] ? 'yes' : 'no' );
		update_option( 'woocommerce_prices_include_tax', $args['prices_include_tax'] ? 'yes' : 'no' );
		update_option( 'woocommerce_tax_based_on', $args['tax_based_on'] );
		update_option( 'woocommerce_ship_to_countries', $args['ship_to_countries'] );

		$this->flushStoreCaches();
	}

	/** A SE / SEK store with a single 25% VAT rate. */
	protected function configureSwedishStore(): int {
		$this->configureStore(
			[
				'country'  => 'SE',
				'currency' => 'SEK',
			]
		);

		return $this->haveTaxRate(
			[
				'tax_rate_country' => 'SE',
				'tax_rate'         => '25.0000',
				'tax_rate_name'    => 'VAT',
			]
		);
	}

	/** A US:CA / USD store with a single 8.5% sales tax rate. */
	protected function configureUsStore(): int {
		$this->configureStore(
			[
				'country'  => 'US:CA',
				'currency' => 'USD',
			]
		);

		return $this->haveTaxRate(
			[
				'tax_rate_country' => 'US',
				'tax_rate_state'   => 'CA',
				'tax_rate'         => '8.5000',
				'tax_rate_name'    => 'Sales Tax',
			]
		);
	}

	/** Inserts a tax rate. */
	protected function haveTaxRate( array $rate ): int {
		$rate_id = \WC_Tax::_insert_tax_rate(
			array_merge(
				[
					'tax_rate_country'  => '',
					'tax_rate_state'    => '',
					'tax_rate'          => '0.0000',
					'tax_rate_name'     => 'Tax',
					'tax_rate_priority' => 1,
					'tax_rate_compound' => 0,
					'tax_rate_shipping' => 1,
					'tax_rate_order'    => 0,
					'tax_rate_class'    => '',
				],
				$rate
			)
		);

		$this->flushStoreCaches();

		return (int) $rate_id;
	}

	/** Creates a tax class and a rate for it, and returns the class slug. */
	protected function haveTaxClass( string $name, string $rate, string $country = 'SE' ): string {
		$existing = \WC_Tax::get_tax_class_slugs();
		$slug     = sanitize_title( $name );

		if ( ! in_array( $slug, $existing, true ) ) {
			\WC_Tax::create_tax_class( $name, $slug );
		}

		$this->haveTaxRate(
			[
				'tax_rate_country' => $country,
				'tax_rate'         => $rate,
				'tax_rate_name'    => $name,
				'tax_rate_class'   => $slug,
			]
		);

		return $slug;
	}

	/** Removes every tax rate in the store. */
	protected function deleteAllTaxRates(): void {
		global $wpdb;

		$rate_ids = $wpdb->get_col( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates" ); // phpcs:ignore

		foreach ( $rate_ids as $rate_id ) {
			\WC_Tax::_delete_tax_rate( (int) $rate_id );
		}

		$this->flushStoreCaches();
	}

	/** Overwrites the Klarna Payments settings option. */
	protected function setKlarnaSettings( array $settings ): void {
		update_option( self::KP_SETTINGS_OPTION, $settings );
	}

	/** Merges credentials for one country into the Klarna Payments settings. */
	protected function haveKlarnaCredentials( string $country, array $overrides = [], bool $testmode = true ): void {
		$country = strtolower( $country );
		$prefix  = $testmode ? 'test_' : '';

		$this->setKlarnaSettings(
			array_merge(
				[
					'enabled'                           => 'yes',
					'testmode'                          => $testmode ? 'yes' : 'no',
					// Both of these are read off the raw option without a fallback.
					'customer_type'                     => 'b2c',
					'logging'                           => 'no',
					"{$prefix}merchant_id_{$country}"   => "mid-{$country}",
					"{$prefix}shared_secret_{$country}" => "secret-{$country}",
					'available_countries'               => [ $country ],
				],
				$overrides
			)
		);
	}

	/**
	 * Merges credentials for several countries into the Klarna Payments settings.
	 *
	 * `haveKlarnaCredentials()` overwrites the whole option, so it cannot build the
	 * multi-account store a cross border purchase needs.
	 *
	 * @param array<int, string> $countries Settings country codes, for example `[ 'se', 'us' ]` or `[ 'eu' ]`.
	 */
	protected function haveKlarnaCredentialsForSets( array $countries, array $overrides = [], bool $testmode = true ): void {
		$prefix      = $testmode ? 'test_' : '';
		$credentials = [];

		foreach ( $countries as $country ) {
			$country = strtolower( $country );

			$credentials[ "{$prefix}merchant_id_{$country}" ]   = "mid-{$country}";
			$credentials[ "{$prefix}shared_secret_{$country}" ] = "secret-{$country}";
			$credentials[ "{$prefix}client_id_{$country}" ]     = "klarna_{$prefix}client_{$country}";
		}

		$this->setKlarnaSettings(
			array_merge(
				[
					'enabled'             => 'yes',
					'testmode'            => $testmode ? 'yes' : 'no',
					'customer_type'       => 'b2c',
					'logging'             => 'no',
					'available_countries' => array_map( 'strtolower', $countries ),
				],
				$credentials,
				$overrides
			)
		);
	}

	/**
	 * Records what Klarna granted each credential set, keyed by settings country code.
	 *
	 * PluginFeatures caches the option in a property, so it has to be re-initialized.
	 */
	protected function haveKlarnaCredentialCapabilities( array $capabilities ): void {
		update_option( 'kp_credential_capabilities', $capabilities );

		KP_WC()->plugin_features()->init_features( true );
	}

	/** Overrides the availability of a single plugin feature. */
	protected function setFeatureAvailability( string $feature_key, bool $available ): void {
		$features = KP_WC()->plugin_features()->get_features();

		$features[ $feature_key ]['availability'] = $available;

		update_option( 'kp_plugin_features', $features );
		KP_WC()->plugin_features()->init_features( true );
	}

	/** Restores the built-in feature defaults, where everything is available. */
	protected function resetPluginFeatures(): void {
		delete_option( 'kp_plugin_features' );
		delete_option( 'kp_unavailable_feature_ids' );
		delete_option( 'kp_credential_capabilities' );

		KP_WC()->plugin_features()->init_features( true );

		// The suite is one process, and the resolved credentials are cached in statics.
		ApiCredentialsUtility::flush_cache();
	}

	/**
	 * Rebuilds WooCommerce's payment gateway objects, so they pick up settings
	 * changed after they were first constructed.
	 */
	protected function reloadPaymentGateways(): void {
		WC()->payment_gateways()->init();
	}

	/** Clears the Klarna session, both the stored copy and the in-memory one. */
	protected function resetKlarnaSession(): void {
		$session = KP_WC()->session;

		$session->klarna_session  = null;
		$session->session_hash    = null;
		$session->session_country = null;
		$session->session_locale  = null;

		WC()->session->__unset( 'kp_session_data' );
		WC()->session->__unset( 'kec_client_token' );
	}

	/** Invalidates the WooCommerce caches that outlive a transaction rollback. */
	protected function flushStoreCaches(): void {
		\WC_Cache_Helper::invalidate_cache_group( 'taxes' );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}
