<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\Utilities\ApiCredentialsUtility;
use Tests\Support\IntegrationTestCase;

/**
 * Which Klarna merchant account signs a request, and which API region it goes to.
 *
 * @covers \Krokedil\Klarna\Utilities\ApiCredentialsUtility
 */
class ApiCredentialsTest extends IntegrationTestCase {

	protected ?string $storeProfile = null;

	/**
	 * @dataProvider provide_credential_setting_keys
	 */
	public function test_a_credential_field_is_read_from_the_key_the_mode_and_the_eu_setting_pick(
		string $field,
		string $country,
		bool $combine,
		bool $testmode,
		?bool $combined_argument,
		string $expected
	): void {
		$this->arrangeCredentials( [ 'se' ], $combine, [], $testmode );

		$this->assertSame(
			$expected,
			ApiCredentialsUtility::get_credentials_setting_key( $field, $country, $combined_argument )
		);
	}

	/** @return array<string, array{0: string, 1: string, 2: bool, 3: bool, 4: ?bool, 5: string}> */
	public function provide_credential_setting_keys(): array {
		return [
			'test mode prefixes the key'                  => [ 'merchant_id', 'se', false, true, null, 'test_merchant_id_se' ],
			'live mode does not'                          => [ 'merchant_id', 'se', false, false, null, 'merchant_id_se' ],
			'combining maps an EU market to the shared set' => [ 'merchant_id', 'de', true, true, null, 'test_merchant_id_eu' ],
			'combining leaves a non EU market alone'      => [ 'merchant_id', 'us', true, true, null, 'test_merchant_id_us' ],
			'the caller can opt out of the mapping'       => [ 'merchant_id', 'de', true, true, false, 'test_merchant_id_de' ],
			'a client id is read the same way'            => [ 'client_id', 'se', true, true, null, 'test_client_id_eu' ],
			'the country code is lower cased'             => [ 'shared_secret', 'US', false, true, null, 'test_shared_secret_us' ],
		];
	}

	/**
	 * @dataProvider provide_credential_fields
	 */
	public function test_each_credential_field_reads_its_own_setting( string $field, string $country, string $expected ): void {
		$this->arrangeCredentials( [ 'se' ], false );

		$actual = match ( $field ) {
			'merchant_id'   => ApiCredentialsUtility::get_merchant_id( $country ),
			'shared_secret' => ApiCredentialsUtility::get_shared_secret( $country ),
			'client_id'     => ApiCredentialsUtility::get_client_id( $country ),
		};

		$this->assertSame( $expected, $actual );
	}

	/** @return array<string, array{0: string, 1: string, 2: string}> */
	public function provide_credential_fields(): array {
		return [
			'the merchant id'                 => [ 'merchant_id', 'se', 'mid-se' ],
			'the shared secret'               => [ 'shared_secret', 'se', 'secret-se' ],
			'the client id'                   => [ 'client_id', 'se', 'klarna_test_client_se' ],
			'an unconfigured country is empty' => [ 'merchant_id', 'gb', '' ],
		];
	}

	/**
	 * @dataProvider provide_regions
	 */
	public function test_the_api_region_follows_the_credential_set( string $country, string $expected ): void {
		$this->assertSame( $expected, ApiCredentialsUtility::get_region( $country ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_regions(): array {
		return [
			'the combined EU set is the European region' => [ 'eu', '' ],
			'a European market'                          => [ 'se', '' ],
			'the United States'                          => [ 'us', '-na' ],
			'Mexico is North American'                   => [ 'mx', '-na' ],
			'Canada is North American'                   => [ 'ca', '-na' ],
			'Australia is Oceanian'                      => [ 'au', '-oc' ],
			'the code is case insensitive'               => [ 'US', '-na' ],
			'an unknown code has no region'              => [ 'xx', '' ],
		];
	}

	/**
	 * @dataProvider provide_market_owners
	 */
	public function test_which_credential_set_owns_a_market( string $market, bool $combine, string $expected ): void {
		$this->arrangeCredentials( [ 'se' ], $combine );

		$this->assertSame( $expected, ApiCredentialsUtility::get_credentials_country_for_market( $market ) );
	}

	/** @return array<string, array{0: string, 1: bool, 2: string}> */
	public function provide_market_owners(): array {
		return [
			'the sets apart, a market owns itself'     => [ 'se', false, 'se' ],
			'the sets combined, an EU market'          => [ 'de', true, 'eu' ],
			'the sets combined, a non euro EU market'  => [ 'no', true, 'eu' ],
			'the sets combined, a non EU market'       => [ 'us', true, 'us' ],
			'the combined set maps to itself'          => [ 'eu', true, 'eu' ],
			'an uppercase market'                      => [ 'SE', true, 'eu' ],
		];
	}

	/**
	 * @dataProvider provide_home_purchases
	 */
	public function test_a_purchase_uses_the_credential_set_that_owns_its_market(
		array $sets,
		bool $combine,
		array $capabilities,
		string $market,
		string $currency,
		string $expected
	): void {
		$this->arrangeCredentials( $sets, $combine, $capabilities );

		$credentials = $this->resolved( $market, $currency );

		$this->assertSame( $expected, $credentials['country_code'] );
		$this->assertSame( "mid-{$expected}", $credentials['merchant_id'] );
		$this->assertSame( "secret-{$expected}", $credentials['shared_secret'] );
		$this->assertSame( "klarna_test_client_{$expected}", $credentials['client_id'] );
	}

	/** @return array<string, array{0: array<int, string>, 1: bool, 2: array, 3: string, 4: string, 5: string}> */
	public function provide_home_purchases(): array {
		$granted = static fn( array $currencies ): array => [
			'mode'       => 'test',
			'markets'    => [],
			'currencies' => $currencies,
		];

		return [
			'the market has its own credentials'            => [ [ 'se' ], false, [], 'se', 'SEK', 'se' ],
			'the combined set signs for every EU market'    => [ [ 'eu' ], true, [], 'de', 'EUR', 'eu' ],
			'the combined set is passed straight through'   => [ [ 'eu' ], true, [], 'eu', 'USD', 'eu' ],
			'the market and currency are case insensitive'  => [ [ 'se' ], false, [], 'SE', 'sek', 'se' ],
			'a redundantly granted home currency changes nothing' => [ [ 'se' ], false, [ 'se' => $granted( [ 'SEK' ] ) ], 'se', 'SEK', 'se' ],
		];
	}

	public function test_the_purchase_currency_defaults_to_the_store_currency(): void {
		$this->configureStore( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->arrangeCredentials( [ 'se' ], false );

		$this->assertSame( 'se', $this->resolved( 'se', null )['country_code'] );
	}

	public function test_a_credential_set_without_a_client_id_still_resolves(): void {
		$this->arrangeCredentials( [ 'se' ], false, [], true, [ 'test_client_id_se' => '' ] );

		$credentials = $this->resolved( 'se', 'SEK' );

		$this->assertSame( 'se', $credentials['country_code'] );
		$this->assertSame( '', $credentials['client_id'], 'Only the Boost features need a client id.' );
	}

	/**
	 * @dataProvider provide_cross_border_purchases
	 */
	public function test_a_purchase_in_another_currency_is_signed_by_the_set_klarna_granted_it(
		array $sets,
		bool $combine,
		array $capabilities,
		string $market,
		string $currency,
		string $expected
	): void {
		$this->arrangeCredentials( $sets, $combine, $capabilities );

		$this->assertSame( $expected, $this->resolved( $market, $currency )['country_code'] );
	}

	/** @return array<string, array{0: array<int, string>, 1: bool, 2: array, 3: string, 4: string, 5: string}> */
	public function provide_cross_border_purchases(): array {
		$granted = static fn( array $markets, array $currencies ): array => [
			'mode'       => 'test',
			'markets'    => $markets,
			'currencies' => $currencies,
		];

		return [
			'a single country set was granted the currency'  => [ [ 'se' ], false, [ 'se' => $granted( [], [ 'EUR' ] ) ], 'se', 'EUR', 'se' ],
			'a German paying in dollars'                     => [ [ 'eu' ], true, [ 'eu' => $granted( [], [ 'USD' ] ) ], 'de', 'USD', 'eu' ],
			'a Norwegian paying in kronor'                    => [ [ 'eu' ], true, [ 'eu' => $granted( [], [ 'SEK' ] ) ], 'no', 'SEK', 'eu' ],
			'another countrys set was granted the market and the currency' => [ [ 'se', 'us' ], false, [ 'us' => $granted( [ 'DE' ], [ 'USD' ] ) ], 'de', 'USD', 'us' ],
			'the market has no credentials of its own'        => [ [ 'se' ], false, [ 'se' => $granted( [ 'DE' ], [ 'EUR' ] ) ], 'de', 'EUR', 'se' ],
			'the combined set serves a market outside the EU group' => [ [ 'eu' ], true, [ 'eu' => $granted( [ 'US' ], [ 'USD' ] ) ], 'us', 'USD', 'eu' ],
			'the home set keeps a purchase it can settle itself' => [
				[ 'se', 'us' ],
				false,
				[ 'se' => $granted( [], [ 'EUR' ] ), 'us' => $granted( [ 'SE' ], [ 'EUR' ] ) ],
				'se',
				'EUR',
				'se',
			],
		];
	}

	/**
	 * The base URL is built from the set that signs, so a market served from abroad
	 * leaves its own region behind.
	 *
	 * @dataProvider provide_cross_border_regions
	 */
	public function test_a_cross_border_purchase_keeps_the_region_of_the_set_that_signs_it(
		array $sets,
		bool $combine,
		array $capabilities,
		string $market,
		string $currency,
		string $expected_set,
		string $expected_region
	): void {
		$this->arrangeCredentials( $sets, $combine, $capabilities );

		$credentials = $this->resolved( $market, $currency );

		$this->assertSame( $expected_set, $credentials['country_code'] );
		$this->assertSame( $expected_region, ApiCredentialsUtility::get_region( $credentials['country_code'] ) );
	}

	/** @return array<string, array{0: array<int, string>, 1: bool, 2: array, 3: string, 4: string, 5: string, 6: string}> */
	public function provide_cross_border_regions(): array {
		$granted = static fn( array $markets, array $currencies ): array => [
			'mode'       => 'test',
			'markets'    => $markets,
			'currencies' => $currencies,
		];

		return [
			'North American credentials serving a European market' => [
				[ 'us' ],
				false,
				[ 'us' => $granted( [ 'DE' ], [ 'USD' ] ) ],
				'de',
				'USD',
				'us',
				'-na',
			],
			'European credentials serving a North American market' => [
				[ 'eu' ],
				true,
				[ 'eu' => $granted( [ 'US' ], [ 'USD' ] ) ],
				'us',
				'USD',
				'eu',
				'',
			],
			'Oceanian credentials serving a European market' => [
				[ 'au' ],
				false,
				[ 'au' => $granted( [ 'SE' ], [ 'AUD' ] ) ],
				'se',
				'AUD',
				'au',
				'-oc',
			],
		];
	}

	/**
	 * @dataProvider provide_competing_credentials
	 */
	public function test_only_one_credential_set_can_be_picked_for_a_cross_border_purchase(
		array $sets,
		array $capabilities,
		string $market,
		string $currency,
		string $expected
	): void {
		$this->arrangeCredentials( $sets, false, $capabilities );

		$this->assertSame( $expected, $this->resolved( $market, $currency )['country_code'] );
	}

	/** @return array<string, array{0: array<int, string>, 1: array, 2: string, 3: string, 4: string}> */
	public function provide_competing_credentials(): array {
		$granted = static fn( array $markets, array $currencies ): array => [
			'mode'       => 'test',
			'markets'    => $markets,
			'currencies' => $currencies,
		];

		return [
			'a set in the markets own region wins' => [
				[ 'se', 'us' ],
				[ 'se' => $granted( [ 'MX' ], [ 'MXN' ] ), 'us' => $granted( [ 'MX' ], [ 'MXN' ] ) ],
				'mx',
				'MXN',
				'us',
			],
			'sets in the same region are picked by code' => [
				[ 'at', 'se' ],
				[ 'at' => $granted( [ 'DE' ], [ 'USD' ] ), 'se' => $granted( [ 'DE' ], [ 'USD' ] ) ],
				'de',
				'USD',
				'at',
			],
			'a set whose credentials were removed is skipped' => [
				[ 'se' ],
				[ 'at' => $granted( [ 'DE' ], [ 'USD' ] ), 'se' => $granted( [ 'DE' ], [ 'USD' ] ) ],
				'de',
				'USD',
				'se',
			],
		];
	}

	/**
	 * @dataProvider provide_capability_modes
	 */
	public function test_a_stale_capabilities_table_cannot_leak_across_test_and_live( ?string $mode, ?string $expected ): void {
		$capability = [ 'markets' => [ 'DE' ], 'currencies' => [ 'USD' ] ];

		if ( null !== $mode ) {
			$capability['mode'] = $mode;
		}

		$this->arrangeCredentials( [ 'us' ], false, [ 'us' => $capability ] );

		$credentials = ApiCredentialsUtility::resolve( 'de', 'USD' );

		if ( null === $expected ) {
			$this->assertWpErrorCode( 'kp_missing_credentials', $credentials );
			return;
		}

		$this->assertSame( $expected, $credentials['country_code'] );
	}

	/** @return array<string, array{0: ?string, 1: ?string}> */
	public function provide_capability_modes(): array {
		return [
			'a live capability cannot serve a test mode purchase from abroad' => [ 'live', null ],
			'a test capability serves a test mode purchase'                  => [ 'test', 'us' ],
			'a capability without a mode applies to both'                    => [ null, 'us' ],
		];
	}

	/**
	 * @dataProvider provide_refused_purchases
	 */
	public function test_a_purchase_that_no_credential_set_can_serve_is_refused(
		array $sets,
		bool $combine,
		array $overrides,
		string $market,
		string $currency,
		string $expected_code,
		string $expected_message
	): void {
		$this->arrangeCredentials( $sets, $combine, [], true, $overrides );

		$error = ApiCredentialsUtility::resolve( $market, $currency );

		$this->assertWpErrorCode( $expected_code, $error );
		$this->assertSame( $expected_message, $error->get_error_message() );
	}

	/** @return array<string, array{0: array<int, string>, 1: bool, 2: array, 3: string, 4: string, 5: string, 6: string}> */
	public function provide_refused_purchases(): array {
		return [
			'an unsupported country' => [
				[ 'se' ],
				false,
				[],
				'xx',
				'SEK',
				'kp_unsupported_market',
				'Country (xx) is not supported by Klarna Payments.',
			],
			'no credentials for the market' => [
				[ 'se' ],
				false,
				[],
				'de',
				'EUR',
				'kp_missing_credentials',
				'No credentials found for de.',
			],
			'no credentials for the combined set' => [
				[ 'se' ],
				true,
				[],
				'se',
				'SEK',
				'kp_missing_credentials',
				'No credentials found for eu.',
			],
			'a currency the market cannot settle in' => [
				[ 'se' ],
				false,
				[],
				'se',
				'USD',
				'kp_currency_not_allowed',
				'SEK must be used for Sweden purchases, or the credentials must be allowed to settle in USD.',
			],
			'a merchant id with no shared secret' => [
				[ 'se' ],
				false,
				[ 'test_shared_secret_se' => '' ],
				'se',
				'SEK',
				'kp_missing_credentials',
				'No credentials found for se.',
			],
		];
	}

	/**
	 * A request is not always a purchase, so an unusable currency falls back to the
	 * customers own market and lets Klarna refuse it instead.
	 *
	 * @dataProvider provide_requests
	 */
	public function test_a_request_falls_back_to_the_customers_own_market_when_the_currency_is_not_allowed(
		array $sets,
		string $market,
		string $currency,
		?string $expected_set,
		?string $expected_code
	): void {
		$this->arrangeCredentials( $sets, false );

		$credentials = ApiCredentialsUtility::resolve_for_request( $market, $currency );

		if ( null !== $expected_code ) {
			$this->assertWpErrorCode( $expected_code, $credentials );
			return;
		}

		$this->assertSame( $expected_set, $credentials['country_code'] );
	}

	/** @return array<string, array{0: array<int, string>, 1: string, 2: string, 3: ?string, 4: ?string}> */
	public function provide_requests(): array {
		return [
			'a currency the market cannot settle in still authenticates' => [ [ 'se' ], 'se', 'USD', 'se', null ],
			'a market with no credentials has nothing to attempt'        => [ [ 'se' ], 'de', 'EUR', null, 'kp_missing_credentials' ],
			'an unsupported market has nothing to attempt'               => [ [ 'se' ], 'xx', 'SEK', null, 'kp_unsupported_market' ],
			'a resolvable purchase is unchanged'                         => [ [ 'se' ], 'se', 'SEK', 'se', null ],
		];
	}

	/**
	 * @dataProvider provide_known_credential_sets
	 */
	public function test_a_known_credential_set_is_used_without_resolving_it_again(
		array $sets,
		bool $combine,
		string $country,
		?string $expected
	): void {
		$this->arrangeCredentials( $sets, $combine );

		$credentials = ApiCredentialsUtility::get_credentials( $country );

		if ( null === $expected ) {
			$this->assertWpErrorCode( 'kp_missing_credentials', $credentials );
			return;
		}

		$this->assertSame( $expected, $credentials['country_code'] );
	}

	/** @return array<string, array{0: array<int, string>, 1: bool, 2: string, 3: ?string}> */
	public function provide_known_credential_sets(): array {
		return [
			'the combined set'                                            => [ [ 'eu' ], true, 'eu', 'eu' ],
			'an order filed under its market before the sets were combined' => [ [ 'eu' ], true, 'se', 'eu' ],
			'an order filed under its market with the sets apart'          => [ [ 'se' ], false, 'se', 'se' ],
			'a set that is no longer configured'                           => [ [ 'se' ], false, 'de', null ],
		];
	}

	/**
	 * @dataProvider provide_serviceable_markets
	 */
	public function test_the_markets_the_configured_credentials_can_serve( array $sets, array $capabilities, array $expected ): void {
		$this->arrangeCredentials( $sets, false, $capabilities );

		$this->assertSame( $expected, ApiCredentialsUtility::get_serviceable_markets() );
	}

	/** @return array<string, array{0: array<int, string>, 1: array, 2: array<int, string>}> */
	public function provide_serviceable_markets(): array {
		$granted = static fn( array $markets, string $mode = 'test' ): array => [
			'mode'       => $mode,
			'markets'    => $markets,
			'currencies' => [ 'USD' ],
		];

		return [
			'no capabilities at all'                       => [ [ 'se' ], [], [] ],
			'the markets Klarna granted'                   => [ [ 'se' ], [ 'se' => $granted( [ 'DE', 'FI' ] ) ], [ 'DE', 'FI' ] ],
			'a market granted to two sets is listed once'   => [ [ 'se', 'us' ], [ 'se' => $granted( [ 'DE' ] ), 'us' => $granted( [ 'DE', 'CA' ] ) ], [ 'DE', 'CA' ] ],
			'a set whose credentials were removed'         => [ [ 'se' ], [ 'us' => $granted( [ 'CA' ] ) ], [] ],
			'a set in the other mode'                      => [ [ 'se' ], [ 'se' => $granted( [ 'DE' ], 'live' ) ], [] ],
			'a set granted no markets'                     => [ [ 'se' ], [ 'se' => $granted( [] ) ], [] ],
		];
	}

	public function test_the_resolved_credentials_can_be_replaced_by_filter(): void {
		$this->arrangeCredentials( [ 'se', 'us' ], false );

		$seen = [];

		add_filter(
			'kp_resolved_credentials',
			static function ( $credentials, $market, $currency ) use ( &$seen ) {
				$seen = [ $market, $currency ];

				return array_merge( $credentials, [ 'country_code' => 'us', 'merchant_id' => 'mid-us' ] );
			},
			10,
			3
		);

		$credentials = $this->resolved( 'se', 'SEK' );

		$this->assertSame( 'us', $credentials['country_code'] );
		$this->assertSame( 'mid-us', $credentials['merchant_id'] );
		$this->assertSame( [ 'se', 'SEK' ], $seen, 'The filter has to know which market and currency it is changing.' );
	}

	/** A stale cache would sign requests with the previous merchant account. */
	public function test_the_cached_settings_are_dropped_when_they_are_written(): void {
		$this->arrangeCredentials( [ 'se' ], false );

		$this->assertSame( 'mid-se', $this->resolved( 'se', 'SEK' )['merchant_id'] );

		$this->setKlarnaSettings( array_merge( ApiCredentialsUtility::get_plugin_settings(), [ 'test_merchant_id_se' => 'mid-se-2' ] ) );

		$this->assertSame( 'mid-se-2', $this->resolved( 'se', 'SEK' )['merchant_id'], 'A settings write has to be seen by the next resolve.' );

		$this->haveKlarnaCredentialCapabilities(
			[ 'se' => [ 'mode' => 'test', 'markets' => [ 'DE' ], 'currencies' => [ 'USD' ] ] ]
		);

		$this->assertSame( [ 'DE' ], ApiCredentialsUtility::get_serviceable_markets(), 'A capabilities write has to be seen by the market list.' );

		delete_option( self::KP_SETTINGS_OPTION );

		$this->assertWpErrorCode( 'kp_missing_credentials', ApiCredentialsUtility::resolve( 'se', 'SEK' ) );
	}

	private function arrangeCredentials(
		array $sets,
		bool $combine,
		array $capabilities = [],
		bool $testmode = true,
		array $overrides = []
	): void {
		if ( $combine ) {
			$overrides['combine_eu_credentials'] = 'yes';
		}

		$this->haveKlarnaCredentialsForSets( $sets, $overrides, $testmode );

		if ( ! empty( $capabilities ) ) {
			$this->haveKlarnaCredentialCapabilities( $capabilities );
		}
	}

	/** Resolves and fails with the error message rather than a type error. */
	private function resolved( string $market, ?string $currency ): array {
		$credentials = ApiCredentialsUtility::resolve( $market, $currency );

		$this->assertIsArray(
			$credentials,
			is_wp_error( $credentials ) ? $credentials->get_error_message() : 'Expected credentials.'
		);

		return $credentials;
	}
}
