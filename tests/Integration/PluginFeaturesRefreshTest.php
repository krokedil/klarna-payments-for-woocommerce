<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;
use Tests\Support\IntegrationTestCase;

/**
 * Refreshing the feature availability of the credentials that are stored in the settings.
 *
 * This is what the manual refresh button on the settings page, the daily cron event and a settings save all run.
 *
 * @covers \Krokedil\Klarna\PluginFeatures
 */
class PluginFeaturesRefreshTest extends IntegrationTestCase {

	protected ?string $storeProfile = null;

	protected function setUp(): void {
		parent::setUp();

		$this->resetPluginFeatures();
	}

	/** The button never sends what the merchant typed into the form, only what was saved. */
	public function test_a_refresh_authenticates_with_the_saved_shared_secret(): void {
		$this->haveKlarnaCredentials( 'se' );
		$this->willRespondWithFeatures();

		$this->assertTrue( $this->refresh() );

		$request = $this->klarnaRequestTo( '/features' );

		$this->assertSame( 'basic secret-se', $request['headers']['Authorization'] );
	}

	public function test_a_refresh_asks_klarna_once_per_configured_credential_set(): void {
		$this->haveKlarnaCredentialsForSets( [ 'se', 'us' ] );
		$this->willRespondWithFeatures();
		$this->willRespondWithFeatures();

		$this->assertTrue( $this->refresh() );

		$secrets = array_map(
			static fn( array $request ): string => $request['headers']['Authorization'],
			$this->klarnaRequestsTo( '/features' )
		);

		$this->assertSame( [ 'basic secret-se', 'basic secret-us' ], $secrets );
	}

	public function test_a_refresh_stores_what_klarna_answered(): void {
		$this->haveKlarnaCredentials( 'se' );
		$this->willRespondWithFeatures(
			[ Features::KEC_ONE_STEP => false, Features::KEC_TWO_STEP => false ],
			[ 'available_markets' => [ 'de', 'fi' ], 'allowed_settlement_currencies' => [ 'eur' ] ]
		);

		$this->assertTrue( $this->refresh() );

		$this->assertFalse( PluginFeatures::is_available( Features::KEC ), 'Klarna reported both express checkout features as unavailable.' );
		$this->assertTrue( PluginFeatures::is_available( Features::PAYMENTS ) );

		$capabilities = KP_WC()->plugin_features()->get_credential_capabilities();

		$this->assertSame( [ 'DE', 'FI' ], $capabilities['se']['markets'] );
		$this->assertSame( [ 'EUR' ], $capabilities['se']['currencies'] );
	}

	/** The settings page hides a section for every feature the refresh found unavailable. */
	public function test_a_refresh_reports_the_sections_to_hide(): void {
		$this->haveKlarnaCredentials( 'se' );
		$this->willRespondWithFeatures( [ Features::KEC_ONE_STEP => false, Features::KEC_TWO_STEP => false ] );

		$this->assertTrue( $this->refresh() );

		$this->assertSame(
			[ 'kec_settings' ],
			PluginFeatures::get_sections_to_hide( KP_WC()->plugin_features()->get_features() )
		);
	}

	/** A partial run would disable every feature belonging to a credential set it never got to. */
	public function test_a_failed_refresh_keeps_the_stored_features(): void {
		$this->haveKlarnaCredentials( 'se' );
		$this->setFeatureAvailability( Features::KEC_ONE_STEP, false );

		$this->willRespondWith( [ 'error_code' => 'UNAUTHORIZED' ], 401 );

		$this->assertFalse( $this->refresh(), 'A failed request cannot be reported as a completed refresh.' );

		$this->assertFalse( PluginFeatures::is_available( Features::KEC_ONE_STEP ), 'The stored availability has to survive a failed refresh.' );
		$this->assertNotSame( '', KP_WC()->plugin_features()->get_last_error(), 'The button needs a message to show the merchant.' );
	}

	public function test_a_successful_refresh_clears_the_previous_error(): void {
		$this->haveKlarnaCredentials( 'se' );

		$this->willRespondWith( [ 'error_code' => 'UNAUTHORIZED' ], 401 );
		$this->assertFalse( $this->refresh() );

		$this->willRespondWithFeatures();

		$this->assertTrue( $this->refresh() );
		$this->assertSame( '', KP_WC()->plugin_features()->get_last_error() );
	}

	/** Without credentials there is nothing to ask about, and nothing to report as broken. */
	public function test_a_refresh_without_saved_credentials_does_not_call_klarna(): void {
		$this->assertTrue( $this->refresh() );

		$this->assertSame( [], $this->klarnaRequestsTo( '/features' ) );
	}

	private function refresh(): bool {
		return KP_WC()->plugin_features()->process_all_api_credentials();
	}

	/**
	 * Queues one plugin features response. Every feature is available unless listed in $availability.
	 *
	 * @param array<string, bool> $availability Feature key => whether Klarna grants it.
	 * @param array               $extra Additional top level fields, for example the cross border lists.
	 */
	private function willRespondWithFeatures( array $availability = [], array $extra = [] ): void {
		$features = [];

		foreach ( $availability as $feature_key => $available ) {
			$features[] = [
				'feature_key'  => 'platform-plugin-' . $feature_key,
				'availability' => $available ? 'AVAILABLE' : 'UNAVAILABLE',
				'markets'      => [],
			];
		}

		// Payments is what the gateway itself is gated on, so it is always part of the answer.
		$features[] = [
			'feature_key'  => 'platform-plugin-' . Features::PAYMENTS,
			'availability' => 'AVAILABLE',
			'markets'      => [ 'SE' ],
		];

		$this->willRespondWith( array_merge( [ 'features' => $features ], $extra ), 200, '/features' );
	}
}
