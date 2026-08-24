<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * How a request signs itself, where it is sent, and the payload builder behind it.
 * KP_Requests is abstract, so the routing runs through KP_Create_Session.
 *
 * @covers \KP_Requests::set_credentials
 * @covers \KP_Requests::get_base_url
 * @covers \KP_Requests::calculate_auth
 * @covers \KP_Order_Data
 */
class RequestsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
	}

	/**
	 * Which merchant account a request signs itself with.
	 *
	 * @dataProvider provide_credential_resolution
	 */
	public function test_resolves_the_merchant_credentials( array $settings, string $country, array $expected ): void {
		$this->setKlarnaSettings( $settings );

		$this->assertSame( $expected, $this->decodedAuth( $country ) );
	}

	/** @return array<string, array{0: array, 1: string, 2: array}> */
	public function provide_credential_resolution(): array {
		$both_modes = [
			'merchant_id_se'        => 'live-mid',
			'shared_secret_se'      => 'live-secret',
			'test_merchant_id_se'   => 'test-mid',
			'test_shared_secret_se' => 'test-secret',
		];

		$combined_eu = [ 'test_merchant_id_eu' => 'mid-eu', 'test_shared_secret_eu' => 'secret-eu' ];

		return [
			'test mode signs with the test keys'          => [ array_merge( [ 'testmode' => 'yes' ], $both_modes ), 'SE', [ 'test-mid', 'test-secret' ] ],
			'live mode signs with the live keys'          => [ array_merge( [ 'testmode' => 'no' ], $both_modes ), 'SE', [ 'live-mid', 'live-secret' ] ],
			'country codes are case insensitive'          => [ array_merge( [ 'testmode' => 'yes' ], $both_modes ), 'se', [ 'test-mid', 'test-secret' ] ],
			'an EU country falls back to combined EU keys' => [ array_merge( [ 'testmode' => 'yes', 'combine_eu_credentials' => 'yes' ], $combined_eu ), 'DE', [ 'mid-eu', 'secret-eu' ] ],
			'combining off means no EU fallback'          => [ array_merge( [ 'testmode' => 'yes', 'combine_eu_credentials' => 'no' ], $combined_eu ), 'DE', [ '', '' ] ],
			'a non-EU country never uses EU keys'         => [ array_merge( [ 'testmode' => 'yes', 'combine_eu_credentials' => 'yes' ], $combined_eu ), 'US', [ '', '' ] ],
			'missing credentials sign with an empty pair' => [ [ 'testmode' => 'yes' ], 'SE', [ '', '' ] ],
			// WooCommerce settings sanitisation HTML-encodes ampersands and quotes.
			'an HTML-encoded secret is decoded first'     => [ [ 'testmode' => 'yes', 'test_merchant_id_se' => 'mid-se', 'test_shared_secret_se' => 'a&amp;b&quot;c' ], 'SE', [ 'mid-se', 'a&b"c' ] ],
		];
	}

	/**
	 * Which regional Klarna host a country's requests go to.
	 *
	 * @dataProvider provide_endpoint_routing
	 */
	public function test_routes_a_country_to_its_regional_endpoint( string $country, bool $testmode, string $expected ): void {
		$this->haveKlarnaCredentials( $country, [], $testmode );

		$this->assertSame( $expected, $this->request( $country )->config['base_url'] );
	}

	/** @return array<string, array{0: string, 1: bool, 2: string}> */
	public function provide_endpoint_routing(): array {
		return [
			'Europe'                              => [ 'SE', true, 'https://api.playground.klarna.com/' ],
			'North America'                       => [ 'US', true, 'https://api-na.playground.klarna.com/' ],
			'Oceania'                             => [ 'AU', true, 'https://api-oc.playground.klarna.com/' ],
			'live drops the playground subdomain' => [ 'US', false, 'https://api-na.klarna.com/' ],
			'an unknown country lands on Europe'  => [ 'JP', true, 'https://api.playground.klarna.com/' ],
		];
	}

	public function test_the_base_region_filter_can_override_the_endpoint(): void {
		$this->haveKlarnaCredentials( 'se' );

		add_filter( 'klarna_base_region', static fn() => '-na' );

		$this->assertSame( 'https://api-na.playground.klarna.com/', $this->request( 'SE' )->config['base_url'] );
	}

	/**
	 * Which properties survive the null-filtering on their way to Klarna.
	 *
	 * @dataProvider provide_remove_null_cases
	 *
	 * @param mixed $value The value to run through the filter.
	 */
	public function test_remove_null_decides_which_properties_survive( $value, bool $expected, string $message ): void {
		// Despite its name, the callback answers "keep this?", it is an array_filter() callback.
		$this->assertSame( $expected, (bool) \KP_Order_Data::remove_null( $value ), $message );
	}

	/** @return array<string, array{0: mixed, 1: bool, 2: string}> */
	public function provide_remove_null_cases(): array {
		return [
			'null'         => [ null, false, 'null is an absent property.' ],
			'empty array'  => [ [], false, 'An empty array is an absent property.' ],
			'filled array' => [ [ 'a' => 1 ], true, 'A populated array is kept.' ],
			'zero'         => [ 0, true, 'Zero is a real amount and must reach Klarna.' ],
			'empty string' => [ '', true, 'An empty string is kept.' ],
			'false'        => [ false, true, 'false is kept.' ],
			'negative int' => [ -10000, true, 'Negative amounts (gift cards) are kept.' ],
			'string'       => [ 'physical', true, 'A string is kept.' ],
		];
	}

	/**
	 * @dataProvider provide_product_url_settings
	 */
	public function test_product_image_urls_are_gated_by_the_setting( array $settings, ?string $expected ): void {
		$this->haveCartWith( [ $this->haveSimpleProduct() ] );

		// Built while the setting is off on purpose: it must be re-read per call.
		$this->setKlarnaSettings( [ 'send_product_urls' => 'no' ] );
		$order_data = new \KP_Order_Data( 'b2c' );

		$this->setKlarnaSettings( $settings );

		$this->assertSame( $expected, $order_data->maybe_allow_order_line_url( 'https://example.com/img.png' ) );
	}

	/** @return array<string, array{0: array, 1: string|null}> */
	public function provide_product_url_settings(): array {
		return [
			'absent defaults to withholding' => [ [], null ],
			'explicitly off'                 => [ [ 'send_product_urls' => 'no' ], null ],
			'switched on'                    => [ [ 'send_product_urls' => 'yes' ], 'https://example.com/img.png' ],
		];
	}

	/**
	 * The payload the interoperability token carries.
	 *
	 * @dataProvider provide_interoperability_scenarios
	 */
	public function test_the_interoperability_payload_matches_the_snapshot( string $scenario ): void {
		$product = $this->arrangeInteroperability( $scenario );

		$this->assertMatchesSnapshot(
			( new \KP_Order_Data( 'b2c' ) )->get_klarna_order_lines_interoperability(),
			'interoperability-' . $scenario,
			[ '<product-id>' => $product->get_id() ]
		);
	}

	/** @return array<string, array{0: string}> */
	public function provide_interoperability_scenarios(): array {
		return [
			'SE with shipping'    => [ 'se' ],
			'US sales tax'        => [ 'us' ],
			'nothing to ship'     => [ 'virtual' ],
			'separate shipping address' => [ 'separate-shipping' ],
		];
	}

	private function arrangeInteroperability( string $scenario ): \WC_Product {
		if ( 'us' === $scenario ) {
			$this->deleteAllTaxRates();
			$this->configureUsStore();
			$this->haveCustomerAddress( $this->usAddress(), $this->usAddress() );
		}

		if ( 'separate-shipping' === $scenario ) {
			$this->haveCustomerAddress(
				$this->swedishAddress(),
				$this->swedishAddress(
					[ 'first_name' => 'Anna', 'last_name' => 'Andersson', 'address_1' => 'Kungsgatan 9', 'city' => 'Stockholm', 'postcode' => '111 43' ]
				)
			);
		}

		$product = $this->haveSimpleProduct(
			[
				'name'    => 'Klarna Test Product',
				'sku'     => 'kp-interop-1',
				'price'   => '100.00',
				'virtual' => 'virtual' === $scenario,
			]
		);

		$this->haveCartWith( [ [ $product, 2 ] ] );

		if ( 'virtual' !== $scenario ) {
			$this->haveChosenFlatRateShipping( 'us' === $scenario ? 'US' : 'SE', '50.00' );
		}

		return $product;
	}

	private function request( string $country ): \KP_Create_Session {
		return new \KP_Create_Session( [ 'country' => $country, 'order_id' => null, 'include_address' => false ] );
	}

	/**
	 * The Basic auth header decoded back into a merchant id / shared secret pair.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function decodedAuth( string $country ): array {
		$header = $this->request( $country )->calculate_auth();

		$this->assertStringStartsWith( 'Basic ', $header );

		return explode( ':', base64_decode( substr( $header, strlen( 'Basic ' ) ) ), 2 );
	}
}
