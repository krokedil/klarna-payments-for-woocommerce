<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The helpers in includes/kp-functions.php, and the functions that apply
 * Klarna's verdict to an order.
 *
 * @covers ::kp_get_klarna_country
 * @covers ::kp_get_locale
 * @covers ::kp_get_client_id
 * @covers ::kp_get_client_id_by_currency
 * @covers ::klarna_sanitize_client_id
 * @covers ::kp_extract_error_message
 * @covers ::kp_is_combined_payment_methods_enabled
 * @covers ::kp_save_order_meta_data
 * @covers ::kp_process_accepted
 * @covers ::kp_process_pending
 * @covers ::kp_process_rejected
 */
class FunctionsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se-no-tax';

	/**
	 * Where the Klarna country comes from when the order does not name one.
	 *
	 * @dataProvider provide_country_resolution
	 */
	public function test_resolves_the_klarna_country( string $scenario, string $expected ): void {
		if ( 'store-base' === $scenario ) {
			$this->configureStore( [ 'country' => 'NO', 'currency' => 'NOK', 'calc_taxes' => false ] );
			$this->haveCustomerAddress();

			$this->assertSame( $expected, kp_get_klarna_country() );
			return;
		}

		$this->haveCustomerAddress( $this->swedishAddress() );

		if ( 'filtered' === $scenario ) {
			add_filter( 'wc_klarna_payments_country', static fn( $country ) => $country . '-filtered' );
		}

		// The blank country is written out rather than left off, because it is the input under test.
		$billing = 'order' === $scenario || 'filtered' === $scenario ? $this->usAddress() : [ 'country' => '' ];

		$this->assertSame( $expected, kp_get_klarna_country( $this->haveOrder( [ 'billing' => $billing ] ) ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_country_resolution(): array {
		return [
			'the order billing country'          => [ 'order', 'US' ],
			'falls back to the customer'         => [ 'customer', 'SE' ],
			'falls back to the store base'       => [ 'store-base', 'NO' ],
			'the country filter is applied last' => [ 'filtered', 'US-filtered' ],
		];
	}

	/**
	 * What KP tells Klarna the customer's locale is.
	 *
	 * @dataProvider provide_locales
	 */
	public function test_formats_the_wordpress_locale_for_klarna( ?string $wp_locale, string $expected, bool $filtered = false ): void {
		if ( null !== $wp_locale ) {
			add_filter( 'locale', static fn() => $wp_locale );
		}

		if ( $filtered ) {
			add_filter( 'kp_locale', static fn( $locale ) => $locale . '-filtered' );
		}

		$this->assertSame( $expected, kp_get_locale() );
	}

	/** @return array<string, array{0: string|null, 1: string, 2?: bool}> */
	public function provide_locales(): array {
		return [
			'underscores become hyphens' => [ 'sv_SE', 'sv-SE' ],
			// WordPress reports Finnish as a bare `fi`, so KP expands it and lowercases the region half.
			'Finnish is expanded by hand, and lowercased' => [ 'fi', 'fi-fi' ],
			'a variant is truncated to five characters' => [ 'de_DE_formal', 'de-DE' ],
			'the locale filter wins'     => [ null, 'en-US-filtered', true ],
		];
	}

	/**
	 * Which stored client ids are fit to hand to Klarna's Web SDK.
	 *
	 * @dataProvider provide_client_ids
	 */
	public function test_only_a_properly_prefixed_client_id_survives_sanitising( string $client_id, string $expected ): void {
		$this->assertSame( $expected, klarna_sanitize_client_id( $client_id ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_client_ids(): array {
		return [
			'a live client id passes through'   => [ 'klarna_live_client_abc123', 'klarna_live_client_abc123' ],
			'a test client id passes through'   => [ 'klarna_test_client_abc123', 'klarna_test_client_abc123' ],
			'an empty id is returned unchanged' => [ '', '' ],
			'the wrong prefix is rejected'      => [ 'klarna_client_abc123', '' ],
			'the prefix has to be at the start' => [ 'xklarna_test_client_abc123', '' ],
			'the prefix alone is not an id'     => [ 'klarna_test_client_', '' ],
		];
	}

	/**
	 * Which client id the Web SDK is handed for a country.
	 *
	 * @dataProvider provide_client_id_resolution
	 */
	public function test_resolves_the_client_id_for_a_country( array $settings, string $country, string $expected ): void {
		$this->setKlarnaSettings( $settings );

		$this->assertSame( $expected, kp_get_client_id( $country ) );
	}

	/** @return array<string, array{0: array, 1: string, 2: string}> */
	public function provide_client_id_resolution(): array {
		$both_modes = [
			'available_countries' => [ 'se' ],
			'test_client_id_se'   => 'klarna_test_client_se',
			'client_id_se'        => 'klarna_live_client_se',
		];

		$both_eu_keys = [
			'testmode'            => 'yes',
			'available_countries' => [ 'de' ],
			'test_client_id_de'   => 'klarna_test_client_de',
			'test_client_id_eu'   => 'klarna_test_client_eu',
		];

		return [
			'test mode reads the test key'               => [ array_merge( [ 'testmode' => 'yes' ], $both_modes ), 'SE', 'klarna_test_client_se' ],
			'live mode reads the live key'               => [ array_merge( [ 'testmode' => 'no' ], $both_modes ), 'SE', 'klarna_live_client_se' ],
			'an unavailable country gets nothing'        => [ [ 'testmode' => 'yes', 'available_countries' => [ 'se' ], 'test_client_id_de' => 'klarna_test_client_de' ], 'DE', '' ],
			'combining EU redirects to the EU key'       => [ array_merge( $both_eu_keys, [ 'combine_eu_credentials' => 'yes' ] ), 'DE', 'klarna_test_client_eu' ],
			'without combining, the country key wins'    => [ array_merge( $both_eu_keys, [ 'combine_eu_credentials' => 'no' ] ), 'DE', 'klarna_test_client_de' ],
			'combining EU leaves a non-EU country alone' => [ [ 'testmode' => 'yes', 'combine_eu_credentials' => 'yes', 'available_countries' => [ 'us' ], 'test_client_id_us' => 'klarna_test_client_us', 'test_client_id_eu' => 'klarna_test_client_eu' ], 'US', 'klarna_test_client_us' ],
			// `logging` is read without a fallback when an id is rejected.
			'a malformed client id is dropped'           => [ [ 'testmode' => 'yes', 'logging' => 'no', 'available_countries' => [ 'se' ], 'test_client_id_se' => 'not-a-klarna-client-id' ], 'SE', '' ],
			'a missing client id is empty'               => [ [ 'testmode' => 'yes', 'available_countries' => [ 'se' ] ], 'SE', '' ],
		];
	}

	/**
	 * A German store trading in kronor: the fall-through rows land on the base
	 * country, an omitted argument on the store currency.
	 *
	 * @dataProvider provide_currency_resolution
	 */
	public function test_resolves_the_client_id_from_a_currency( ?string $currency, string $expected ): void {
		$this->configureStore( [ 'country' => 'DE', 'currency' => 'SEK', 'calc_taxes' => false ] );
		$this->haveCustomerAddress();
		$this->setKlarnaSettings(
			[
				'testmode'            => 'yes',
				'available_countries' => [ 'se', 'us', 'de' ],
				'test_client_id_se'   => 'klarna_test_client_se',
				'test_client_id_us'   => 'klarna_test_client_us',
				'test_client_id_de'   => 'klarna_test_client_de',
			]
		);

		$this->assertSame( $expected, kp_get_client_id_by_currency( $currency ) );
	}

	/** @return array<string, array{0: string|null, 1: string}> */
	public function provide_currency_resolution(): array {
		return [
			'kronor map to Sweden'                              => [ 'SEK', 'klarna_test_client_se' ],
			'dollars map to the United States'                  => [ 'USD', 'klarna_test_client_us' ],
			'euros fall back to the resolved country'           => [ 'EUR', 'klarna_test_client_de' ],
			'a currency Klarna does not trade in falls back too' => [ 'JPY', 'klarna_test_client_de' ],
			'no currency means the store currency'              => [ null, 'klarna_test_client_se' ],
		];
	}

	public function test_the_client_id_defaults_to_the_resolved_klarna_country(): void {
		$this->haveCustomerAddress( $this->usAddress() );
		$this->setKlarnaSettings(
			[
				'testmode'            => 'yes',
				'available_countries' => [ 'se', 'us' ],
				'test_client_id_se'   => 'klarna_test_client_se',
				'test_client_id_us'   => 'klarna_test_client_us',
			]
		);

		$this->assertSame( 'klarna_test_client_us', kp_get_client_id() );
	}

	/**
	 * @dataProvider provide_misc_helpers
	 */
	public function test_the_settings_and_error_helpers( string $helper, $input, $expected ): void {
		if ( 'error' === $helper ) {
			$this->assertSame( $expected, kp_extract_error_message( new \WP_Error( $input[0], $input[1] ) ) );
			return;
		}

		$this->setKlarnaSettings( $input );
		$this->assertSame( $expected, kp_is_combined_payment_methods_enabled() );
	}

	/** @return array<string, array{0: string, 1: mixed, 2: mixed}> */
	public function provide_misc_helpers(): array {
		return [
			'a Klarna error code'                  => [ 'error', [ 'invalid_request', 'Bad value for order_amount' ], 'Klarna Payments API Error: invalid_request Bad value for order_amount' ],
			// process_response() puts the HTTP status in the code slot.
			'an HTTP status stands in for the code' => [ 'error', [ 404, 'No such order' ], 'Klarna Payments API Error: 404 No such order' ],
			'combining shows one Klarna method'    => [ 'combined', [ 'combine_payment_methods' => 'yes' ], true ],
			'absent defaults to one per category'  => [ 'combined', [], false ],
		];
	}

	/**
	 * The meta a completed purchase leaves on the order.
	 *
	 * @dataProvider provide_saved_meta
	 */
	public function test_saves_the_klarna_order_meta( string $scenario, array $expected ): void {
		$order = $this->arrangeSavedMeta( $scenario );

		$saved = $this->reload( $order );

		foreach ( $expected as $key => $value ) {
			$this->assertSame( $value, $saved->get_meta( $key, true ), $key );
		}
	}

	/** @return array<string, array{0: string, 1: array<string, string>}> */
	public function provide_saved_meta(): array {
		return [
			'the klarna reference'          => [ 'default', [ '_wc_klarna_order_id' => 'klarna-order-123', '_wc_klarna_environment' => 'test' ] ],
			'a live store'                  => [ 'live', [ '_wc_klarna_environment' => 'live' ] ],
			'the country from the order'    => [ 'norwegian', [ '_wc_klarna_country' => 'NO' ] ],
			// Order management has to call the same combined-EU account.
			'EU when credentials are combined' => [ 'combined-eu', [ '_wc_klarna_country' => 'EU' ] ],
		];
	}

	public function test_saving_the_meta_also_stamps_the_transaction_and_gateway(): void {
		$order = $this->arrangeSavedMeta( 'default' );
		$saved = $this->reload( $order );

		$this->assertSame( 'klarna-order-123', $saved->get_transaction_id() );
		$this->assertSame( 'klarna_payments', $saved->get_payment_method() );
		$this->assertSame( WC_KLARNA_PAYMENTS_VERSION, $saved->get_meta( '_krokedil_environment_info', true )['plugin_version'] );
	}

	/**
	 * What the customer sees the order was paid with.
	 *
	 * @dataProvider provide_payment_titles
	 */
	public function test_names_the_payment_method_on_the_order( ?string $description, string $authorized_type, string $method, string $title ): void {
		$order = $this->havePlacedOrder();

		if ( null !== $description ) {
			$this->willRespondWith( [ 'initial_payment_method' => [ 'description' => $description ] ], 200, 'ordermanagement/v1/orders' );
		}

		// A failed lookup echoes before it returns.
		ob_start();
		kp_save_order_meta_data( $order, $this->klarnaResponse( [ 'authorized_payment_method' => [ 'type' => $authorized_type ] ] ) );
		ob_end_clean();

		$saved = $this->reload( $order );

		$this->assertSame( $method, $saved->get_meta( '_kp_payment_method', true ) );
		$this->assertSame( $title, $saved->get_payment_method_title() );
	}

	/** @return array<string, array{0: string|null, 1: string, 2: string, 3: string}> */
	public function provide_payment_titles(): array {
		return [
			'klarna names the method'              => [ 'Pay in 30 days', 'invoice', 'Pay in 30 days', 'Klarna - Pay in 30 days' ],
			'the authorized method is the fallback' => [ null, 'base_account', 'Slice It', 'Klarna - Slice It' ],
			'an unrecognised method keeps Klarna'  => [ null, 'something_new', '', 'Klarna' ],
		];
	}

	/**
	 * @dataProvider provide_verdict_processors
	 */
	public function test_the_verdict_processors_resolve_the_order( string $verdict, string $result, string $status, string $klarna_order_id ): void {
		$order = $this->havePlacedOrder();

		if ( 'REJECTED' !== $verdict ) {
			$this->willRespondWith( [ 'initial_payment_method' => [ 'description' => 'Pay Later' ] ], 200, 'ordermanagement/v1/orders' );
		}

		$processor = [ 'ACCEPTED' => 'kp_process_accepted', 'PENDING' => 'kp_process_pending', 'REJECTED' => 'kp_process_rejected' ][ $verdict ];
		$processed = $processor( $order, $this->klarnaResponse( [ 'fraud_status' => $verdict ] ) );

		$saved = $this->reload( $order );

		$this->assertSame( $result, $processed['result'] );
		$this->assertSame( $status, $saved->get_status() );
		$this->assertSame( $klarna_order_id, (string) $saved->get_meta( '_wc_klarna_order_id', true ) );

		if ( 'ACCEPTED' === $verdict ) {
			$this->assertNotEmpty( $processed['redirect'] );
			$this->assertNotEmpty( $saved->get_date_paid(), 'A paid date is what stops the callback processing the order twice.' );
			return;
		}

		if ( 'REJECTED' === $verdict ) {
			$this->assertNoKlarnaRequests();
			$this->assertEmpty( $saved->get_transaction_id() );
		}
	}

	/** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
	public function provide_verdict_processors(): array {
		return [
			// A pending order still needs its reference so it can be captured later.
			'accepted' => [ 'ACCEPTED', 'success', 'processing', 'klarna-order-123' ],
			'pending'  => [ 'PENDING', 'success', 'on-hold', 'klarna-order-123' ],
			'rejected' => [ 'REJECTED', 'failure', 'failed', '' ],
		];
	}

	public function test_an_accepted_order_fires_the_integration_actions(): void {
		$order = $this->havePlacedOrder();
		$fired = [];

		foreach ( [ 'wc_klarna_payments_accepted', 'wc_klarna_accepted' ] as $hook ) {
			add_action(
				$hook,
				static function ( $order_id ) use ( &$fired, $hook ) {
					$fired[] = "{$hook}:{$order_id}";
				}
			);
		}

		$this->willRespondWith( [ 'initial_payment_method' => [ 'description' => 'Pay Later' ] ], 200, 'ordermanagement/v1/orders' );
		kp_process_accepted( $order, $this->klarnaResponse() );

		$this->assertSame(
			[ "wc_klarna_payments_accepted:{$order->get_id()}", "wc_klarna_accepted:{$order->get_id()}" ],
			$fired
		);
	}

	public function test_the_rejected_status_can_be_overridden_by_filter(): void {
		$order = $this->havePlacedOrder();

		add_filter( 'kp_order_rejected_status', static fn() => 'cancelled' );

		kp_process_rejected( $order, $this->klarnaResponse( [ 'fraud_status' => 'REJECTED' ] ) );

		$this->assertSame( 'cancelled', $this->reload( $order )->get_status() );
	}

	private function arrangeSavedMeta( string $scenario ): \WC_Order {
		$billing = $this->swedishAddress();

		switch ( $scenario ) {
			case 'live':
				$this->haveKlarnaCredentials( 'se', [], false );
				break;
			case 'norwegian':
				$billing = $this->swedishAddress( [ 'country' => 'NO' ] );
				break;
			case 'combined-eu':
				$this->haveKlarnaCredentials( 'eu', [ 'combine_eu_credentials' => 'yes' ] );
				$billing = $this->swedishAddress( [ 'country' => 'DE' ] );
				break;
		}

		$order = $this->haveOrder( [ 'items' => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ], 'billing' => $billing ] );

		ob_start();
		kp_save_order_meta_data( $order, $this->klarnaResponse() );
		ob_end_clean();

		return $order;
	}

	private function havePlacedOrder(): \WC_Order {
		return $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress(),
			]
		);
	}

	private function klarnaResponse( array $overrides = [] ): array {
		return array_merge(
			[
				'order_id'                  => 'klarna-order-123',
				'fraud_status'              => 'ACCEPTED',
				'authorized_payment_method' => [ 'type' => 'invoice' ],
			],
			$overrides
		);
	}
}
