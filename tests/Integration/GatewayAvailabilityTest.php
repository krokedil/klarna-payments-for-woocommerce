<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\Features;
use Tests\Support\IntegrationTestCase;

/**
 * Whether the Klarna gateway is available at checkout.
 *
 * @covers \WC_Gateway_Klarna_Payments::country_currency_check
 * @covers \WC_Gateway_Klarna_Payments::is_available
 * @covers ::kp_is_country_available
 */
class GatewayAvailabilityTest extends IntegrationTestCase {

	/**
	 * Whether the store's country, currency and credentials add up to a market KP serves.
	 *
	 * @dataProvider provide_country_currency_checks
	 */
	public function test_the_country_and_currency_check( array $store, array $settings, ?string $error_contains ): void {
		$this->givenStoreAndCustomerIn( $store['country'], $store['currency'], $store['customer'] ?? $store['country'] );
		$this->setKlarnaSettings( $settings );

		$result = $this->gateway()->country_currency_check();

		if ( null === $error_contains ) {
			$this->assertTrue( $result );
			return;
		}

		$this->assertWPError( $result );
		$this->assertStringContainsString( $error_contains, $result->get_error_message() );
	}

	/** @return array<string, array{0: array, 1: array, 2: string|null}> */
	public function provide_country_currency_checks(): array {
		$se     = [ 'country' => 'SE', 'currency' => 'SEK' ];
		$de     = [ 'country' => 'DE', 'currency' => 'EUR' ];
		$creds  = static fn( string $country, array $extra = [], string $prefix = 'test_' ): array => array_merge(
			[
				'enabled'                          => 'yes',
				'testmode'                         => 'test_' === $prefix ? 'yes' : 'no',
				'customer_type'                    => 'b2c',
				"{$prefix}merchant_id_{$country}"  => "mid-{$country}",
				"{$prefix}shared_secret_{$country}" => "secret-{$country}",
				'available_countries'              => [ $country ],
			],
			$extra
		);

		return [
			'a supported country with its own currency'     => [ $se, $creds( 'se' ), null ],
			'a currency Klarna does not support'            => [ [ 'country' => 'SE', 'currency' => 'JPY' ], $creds( 'se' ), 'Currency' ],
			'a country Klarna does not support'             => [ [ 'country' => 'SE', 'currency' => 'SEK', 'customer' => 'JP' ], $creds( 'se' ), 'Country' ],
			'a supported currency the country does not use' => [ [ 'country' => 'SE', 'currency' => 'EUR' ], $creds( 'se' ), 'SEK' ],
			'no credentials at all'                         => [ $se, [ 'enabled' => 'yes', 'testmode' => 'yes' ], 'No credentials found' ],
			'live mode with only test credentials'          => [ $se, $creds( 'se', [ 'testmode' => 'no' ] ), 'No credentials found' ],
			'live mode with live credentials'               => [ $se, $creds( 'se', [], '' ), null ],
			'combined EU credentials'                       => [ $de, $creds( 'eu', [ 'combine_eu_credentials' => 'yes', 'available_countries' => [ 'de' ] ] ), null ],
			'combined EU credentials switched off'          => [ $de, $creds( 'eu', [ 'combine_eu_credentials' => 'no', 'available_countries' => [ 'de' ] ] ), 'No credentials found' ],
		];
	}

	public function test_a_failed_check_drops_the_klarna_session(): void {
		$this->givenStoreAndCustomerIn( 'SE', 'JPY' );
		$this->haveKlarnaCredentials( 'se' );

		WC()->session->set( 'kp_session_data', '{"session_id":"stale"}' );

		$this->gateway()->country_currency_check();

		$this->assertEmpty(
			WC()->session->get( 'kp_session_data' ),
			'Or the customer keeps a session for a country we can no longer serve.'
		);
	}

	/**
	 * @dataProvider provide_availability
	 */
	public function test_whether_the_gateway_offers_itself( array $overrides, ?string $feature_off, bool $expected ): void {
		$this->givenStoreAndCustomerIn( 'SE', 'SEK' );
		$this->haveKlarnaCredentials( 'se', $overrides );

		if ( null !== $feature_off ) {
			$this->setFeatureAvailability( $feature_off, false );
		}

		$this->assertSame( $expected, $this->gateway()->is_available() );

		// Checkout renders on every page load, so a request here would block it on a Klarna round trip.
		$this->assertNoKlarnaRequests();
	}

	/** @return array<string, array{0: array, 1: string|null, 2: bool}> */
	public function provide_availability(): array {
		return [
			'the happy path'                => [ [], null, true ],
			'the gateway is disabled'       => [ [ 'enabled' => 'no' ], null, false ],
			'payments switched off remotely' => [ [], Features::PAYMENTS, false ],
			'the country is not allowed'    => [ [ 'available_countries' => [ 'no' ] ], null, false ],
		];
	}

	/**
	 * Whether a country counts as switched on for this merchant.
	 *
	 * @dataProvider provide_country_availability
	 */
	public function test_country_availability( array $settings, string $country, bool $expected, string $why ): void {
		$this->setKlarnaSettings( $settings );

		$this->assertSame( $expected, kp_is_country_available( $country ), $why );
	}

	/** @return array<string, array{0: array, 1: string, 2: bool, 3: string}> */
	public function provide_country_availability(): array {
		$allow_list = [
			'enabled'             => 'yes', 'testmode' => 'yes',
			'test_merchant_id_se' => 'mid-se', 'test_shared_secret_se' => 'secret-se',
			'available_countries' => [ 'se', 'no' ],
		];

		// Saved before `available_countries` existed, so a credential pair is what enables a country.
		$legacy = [
			'enabled'             => 'yes', 'testmode' => 'yes',
			'test_merchant_id_se' => 'mid-se', 'test_shared_secret_se' => 'secret-se',
		];

		$legacy_eu = [
			'enabled'             => 'yes', 'testmode' => 'yes', 'combine_eu_credentials' => 'yes',
			'test_merchant_id_eu' => 'mid-eu', 'test_shared_secret_eu' => 'secret-eu',
		];

		return [
			'a country on the allow-list'           => [ $allow_list, 'SE', true, 'Listed in available_countries.' ],
			'the allow-list is case insensitive'    => [ $allow_list, 'no', true, 'Listed in available_countries.' ],
			'a country off the allow-list'          => [ $allow_list, 'DE', false, 'Not listed in available_countries.' ],
			'legacy settings, country with keys'    => [ $legacy, 'SE', true, 'A stored credential pair marks the country enabled.' ],
			'legacy settings, country without keys' => [ $legacy, 'DE', false, 'No credential pair, so not enabled.' ],
			'legacy combined EU, an EU country'     => [ $legacy_eu, 'DE', true, 'DE resolves to the EU credential pair.' ],
			'legacy combined EU, a non-EU country'  => [ $legacy_eu, 'US', false, 'US is not an EU country, so it has no credentials.' ],
			'no list and no credentials'            => [ [ 'enabled' => 'yes', 'testmode' => 'yes' ], 'SE', false, 'Nothing marks any country enabled.' ],
			'combining switched off'                => [ array_merge( $legacy_eu, [ 'combine_eu_credentials' => 'no' ] ), 'DE', false, 'The EU pair only counts while combine_eu_credentials is yes.' ],
			'an allow-list wins over combining EU'  => [ array_merge( $allow_list, [ 'combine_eu_credentials' => 'yes' ] ), 'DE', false, 'available_countries takes precedence over the combined-EU fallback.' ],
		];
	}

	private function gateway(): \WC_Gateway_Klarna_Payments {
		return new \WC_Gateway_Klarna_Payments();
	}

	private function givenStoreAndCustomerIn( string $country, string $currency, ?string $customer_country = null ): void {
		$this->configureStore( [ 'country' => $country, 'currency' => $currency, 'calc_taxes' => false ] );
		$this->haveCustomerAddress( [ 'country' => $customer_country ?? $country ] );
	}
}
