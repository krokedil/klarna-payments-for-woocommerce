<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\SignInWithKlarna\Settings;
use Tests\Support\IntegrationTestCase;

/**
 * The OAuth scopes requested with Sign in with Klarna.
 *
 * @covers \Krokedil\Klarna\SignInWithKlarna\Settings::get
 */
class SignInWithKlarnaSettingsTest extends IntegrationTestCase {

	private const REQUIRED = 'openid offline_access customer:login profile:name profile:email profile:phone profile:billing_address';

	/**
	 * The required scopes always come first, once each, and the siwk_scope filter can only add to them.
	 *
	 * @dataProvider provide_scopes
	 */
	public function test_requests_the_required_scopes_plus_any_additional_ones( array $settings, ?callable $filter, string $expected ): void {
		if ( null !== $filter ) {
			add_filter( 'siwk_scope', $filter );
		}

		$siwk = new Settings( $settings );
		$siwk->init_settings();

		$this->assertSame( $expected, $siwk->get( 'scope' ) );
	}

	/** @return array<string, array{0: array<string, string>, 1: ?callable, 2: string}> */
	public function provide_scopes(): array {
		return [
			'nothing additional, required keys never saved' => [ [], null, self::REQUIRED ],
			'a checkbox ticked'                              => [ [ 'siwk_optional_scopes_country' => 'yes' ], null, self::REQUIRED . ' profile:country' ],
			'a filter that adds with a leading space'        => [ [], static fn( $scope ) => $scope . ' profile:country', self::REQUIRED . ' profile:country' ],
			'a filter that adds a required scope'            => [ [], static fn( $scope ) => 'profile:email', self::REQUIRED ],
			'a filter that tries to replace everything'      => [ [ 'siwk_optional_scopes_language' => 'yes' ], static fn( $scope ) => 'profile:country', self::REQUIRED . ' profile:country' ],
		];
	}
}
