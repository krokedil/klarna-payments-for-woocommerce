<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The checkout entry point. Three of its four forks are reachable from PHP;
 * only the browser's round trip through the hosted payment page is E2E's.
 *
 * @covers \WC_Gateway_Klarna_Payments::process_payment
 */
class CheckoutTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
	}

	/**
	 * Every fork stamps the same three meta keys, and each returns its own shape.
	 *
	 * @dataProvider provide_checkout_forks
	 */
	public function test_the_checkout_forks_stamp_the_order_and_return_their_own_shape( string $fork, array $expected_meta ): void {
		$gateway = $this->gateway();
		$this->arrangeFork( $fork );

		$order  = $this->haveCheckoutOrder( 'blocks' === $fork ? [ 'created_via' => 'store-api' ] : [] );
		$result = $gateway->process_payment( $order->get_id() );

		$reloaded = $this->reload( $order );

		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( $expected_meta['_wc_klarna_environment'], $reloaded->get_meta( '_wc_klarna_environment' ) );
		$this->assertSame( $expected_meta['_wc_klarna_country'], $reloaded->get_meta( '_wc_klarna_country' ) );
		$this->assertSame( $expected_meta['_kp_session_id'], $reloaded->get_meta( '_kp_session_id' ) );

		switch ( $fork ) {
			case 'shortcode':
				$this->assertSame( $order->get_id(), $result['order_id'] );
				$this->assertSame( $order->get_order_key(), $result['order_key'] );
				$this->assertArrayNotHasKey( 'redirect', $result, 'KP\'s own script finishes this flow.' );
				return;
			case 'blocks':
				$this->assertSame( 'https://pay.playground.klarna.com/eu/hpp/payment/hpp-1', $result['redirect'] );
				$this->assertStringEndsWith( '/hpp/v1/sessions', $this->klarnaRequestTo( 'hpp/v1/sessions' )['url'] );
				return;
			case 'kec':
				$this->assertSame( 'SEK', $result['payload']['purchase_currency'] );
				$this->assertNoKlarnaRequests( 'The express-checkout branch talks to Klarna only from the browser.' );
				return;
		}
	}

	/** @return array<string, array{0: string, 1: array<string, string>}> */
	public function provide_checkout_forks(): array {
		$meta = static fn( string $session_id ): array => [
			'_wc_klarna_environment' => 'test',
			'_wc_klarna_country'     => 'SE',
			'_kp_session_id'         => $session_id,
		];

		return [
			'shortcode checkout'      => [ 'shortcode', $meta( 'sess-1' ) ],
			'blocks checkout via HPP' => [ 'blocks', $meta( 'sess-1' ) ],
			'express checkout token'  => [ 'kec', $meta( 'kec-token-1' ) ],
		];
	}

	/**
	 * @dataProvider provide_eu_forks
	 */
	public function test_combining_eu_credentials_files_the_order_under_eu( string $fork ): void {
		$gateway = $this->gateway( [ 'combine_eu_credentials' => 'yes' ] );
		$this->arrangeFork( $fork );

		$order = $this->haveCheckoutOrder( 'blocks' === $fork ? [ 'created_via' => 'store-api' ] : [] );

		$gateway->process_payment( $order->get_id() );

		$this->assertSame(
			'EU',
			$this->reload( $order )->get_meta( '_wc_klarna_country' ),
			'Order management has to sign later calls with the merchant account the purchase used.'
		);
	}

	/** @return array<string, array{0: string}> */
	public function provide_eu_forks(): array {
		return [
			'blocks checkout'        => [ 'blocks' ],
			'express checkout token' => [ 'kec' ],
		];
	}

	public function test_a_live_store_records_the_live_environment(): void {
		$gateway = $this->gateway( [], false );
		$this->arrangeFork( 'shortcode' );

		$order = $this->haveCheckoutOrder();
		$gateway->process_payment( $order->get_id() );

		$this->assertSame(
			'live',
			$this->reload( $order )->get_meta( '_wc_klarna_environment' ),
			'Order management reads this to decide which Klarna host to talk to.'
		);
	}

	/**
	 * The customer addresses the shortcode fork hands back to KP's script.
	 *
	 * @dataProvider provide_address_shapes
	 */
	public function test_the_returned_addresses_match_the_snapshot( string $scenario ): void {
		$gateway = $this->gateway( 'b2b' === $scenario ? [ 'customer_type' => 'b2b' ] : [] );
		$this->arrangeFork( 'shortcode' );

		$this->haveCustomerAddress( ...$this->addressesFor( $scenario ) );

		$order     = $this->haveCheckoutOrder();
		$addresses = $gateway->process_payment( $order->get_id() )['addresses'];

		$this->assertMatchesSnapshot( $addresses, 'checkout-addresses-' . $scenario );
	}

	/** @return array<string, array{0: string}> */
	public function provide_address_shapes(): array {
		return [
			'b2c, spaced postcode'    => [ 'b2c' ],
			'b2b company'             => [ 'b2b' ],
			'separate shipping'       => [ 'separate-shipping' ],
			'no billing country'      => [ 'no-country' ],
		];
	}

	/** @return array{0: array, 1: array} */
	private function addressesFor( string $scenario ): array {
		switch ( $scenario ) {
			case 'separate-shipping':
				return [
					$this->swedishAddress(),
					[
						'first_name' => 'Anna', 'last_name' => 'Andersson', 'address_1' => 'Kungsgatan 9',
						'city'       => 'Stockholm', 'postcode' => '111 43', 'country' => 'SE',
					],
				];
			case 'no-country':
				return [ $this->swedishAddress( [ 'country' => '' ] ), [] ];
			default:
				$address = $this->swedishAddress( [ 'postcode' => '411 06' ] );
				return [ $address, $address ];
		}
	}

	/**
	 * @dataProvider provide_failure_guards
	 */
	public function test_a_purchase_that_cannot_proceed_is_stopped( string $scenario, string $message, array $expected_meta ): void {
		$gateway = $this->gateway();
		$order   = $this->arrangeFailure( $scenario );

		$thrown = null;

		try {
			$gateway->process_payment( $order->get_id() );
		} catch ( \Exception $exception ) {
			$thrown = $exception;
		}

		$this->assertNotNull( $thrown, sprintf( 'Expected "%s" to stop the purchase.', $scenario ) );
		$this->assertSame( $message, $thrown->getMessage() );

		$reloaded = $this->reload( $order );

		foreach ( $expected_meta as $key => $value ) {
			$this->assertSame( $value, $reloaded->get_meta( $key ), $key );
		}

		$this->assertSame( 'pending', $reloaded->get_status() );
	}

	/** @return array<string, array{0: string, 1: string, 2: array<string, string>}> */
	public function provide_failure_guards(): array {
		$session_error = 'Failed to get required data from the Klarna session. Please try again.';
		$no_meta       = [ '_kp_session_id' => '', '_wc_klarna_country' => '', '_wc_klarna_environment' => '' ];

		return [
			'no session at all'      => [ 'no-session', $session_error, $no_meta ],
			'session with no country' => [ 'empty-session-country', $session_error, $no_meta ],
			'session creation failed' => [ 'blocks-no-session', 'Failed to create a session with Klarna. Please try again.', $no_meta ],
			'hosted page failed'      => [ 'blocks-no-hpp', 'Failed to create a hosted payment page with Klarna. Please try again.', [] ],
		];
	}

	private function arrangeFailure( string $scenario ): \WC_Order {
		if ( 'empty-session-country' === $scenario ) {
			// Session id present, country not. `??` only falls back on null, so '' reaches the guard.
			WC()->session->set(
				'kp_session_data',
				wp_json_encode(
					[
						'klarna_session'  => [ 'session_id' => 'sess-1' ],
						'session_hash'    => 'hash-1',
						'session_country' => '',
						'session_locale'  => 'sv_SE',
					]
				)
			);
		}

		if ( 0 === strpos( $scenario, 'blocks' ) ) {
			$this->simulateCheckoutPage();

			if ( 'blocks-no-hpp' === $scenario ) {
				$this->willCreateSession();
			}

			return $this->haveCheckoutOrder( [ 'created_via' => 'store-api' ] );
		}

		return $this->haveCheckoutOrder();
	}

	/**
	 * Rebuilds the gateways after the settings are written, `testmode` and
	 * `customer_type` are read in the constructor, and returns the registry's copy.
	 */
	private function gateway( array $settings = [], bool $testmode = true ): \WC_Gateway_Klarna_Payments {
		$this->haveKlarnaCredentials( 'se', $settings, $testmode );
		$this->reloadPaymentGateways();

		return WC()->payment_gateways()->payment_gateways()['klarna_payments'];
	}

	private function haveCheckoutOrder( array $args = [] ): \WC_Order {
		return $this->haveOrder(
			array_merge(
				[
					'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
					'billing' => $this->swedishAddress(),
				],
				$args
			)
		);
	}

	private function arrangeFork( string $fork ): void {
		if ( 'kec' === $fork ) {
			// Deliberately not on a checkout page, so nothing can create a session.
			$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
			WC()->session->set( 'kec_client_token', 'kec-token-1' );
			return;
		}

		$this->simulateCheckoutPage();

		if ( 'blocks' === $fork ) {
			$this->willCreateSession();
			$this->willCreateHpp();
			return;
		}

		$this->haveKlarnaSessionForTheCart();
	}

	/**
	 * Stores a real Klarna session the way the checkout page does, then blanks the
	 * KP_Session singleton so the test proves process_payment() reads the stored blob.
	 */
	private function haveKlarnaSessionForTheCart(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$this->assertSame( 'sess-1', KP_WC()->session->get_klarna_session_id(), 'Fixture failed to create a session.' );

		$session                  = KP_WC()->session;
		$session->klarna_session  = null;
		$session->session_hash    = null;
		$session->session_country = null;
		$session->session_locale  = null;

		$this->resetHttpInterception();
	}
}
