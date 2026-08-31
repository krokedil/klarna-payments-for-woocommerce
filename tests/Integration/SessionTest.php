<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Creating, updating and reusing a Klarna session, and the order body it carries.
 *
 * @covers \KP_Session
 */
class SessionTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->reloadPaymentGateways();
		$this->simulateCheckoutPage();

		// Detached so cart fixtures do not fire sessions of their own.
		remove_action( 'woocommerce_after_calculate_totals', [ KP_WC()->session, 'get_session' ], 999999 );

		$this->resetHttpInterception();
	}

	/**
	 * Whether a change to the cart reuses, patches or replaces the Klarna session.
	 *
	 * @dataProvider provide_session_lifecycle
	 */
	public function test_the_session_lifecycle( string $change, int $creates, int $updates ): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		if ( 'not-on-checkout' === $change ) {
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
		} elseif ( 'gateway-disabled' === $change ) {
			$this->haveKlarnaCredentials( 'se', [ 'enabled' => 'no' ] );
			$this->reloadPaymentGateways();
		} else {
			$this->willCreateSession();
			KP_WC()->session->get_session();
		}

		$this->applyCartChange( $change );

		if ( $updates > 0 ) {
			$this->willUpdateSession();
		}

		if ( $creates > 1 ) {
			$this->willCreateSession( [ 'session_id' => 'sess-2' ] );
		}

		KP_WC()->session->get_session();

		$this->assertSessionCalls( $creates, $updates );
	}

	/** @return array<string, array{0: string, 1: int, 2: int}> */
	public function provide_session_lifecycle(): array {
		return [
			'not on the checkout page' => [ 'not-on-checkout', 0, 0 ],
			'klarna unavailable'       => [ 'gateway-disabled', 0, 0 ],
			'nothing changed'          => [ 'none', 1, 0 ],
			'cart contents changed'    => [ 'cart', 1, 1 ],
			'billing address changed'  => [ 'billing', 1, 1 ],
			'shipping method changed'  => [ 'shipping', 1, 1 ],
			'coupon applied'           => [ 'coupon', 1, 1 ],
			'locale changed'           => [ 'locale', 2, 0 ],
		];
	}

	private function applyCartChange( string $change ): void {
		switch ( $change ) {
			case 'cart':
				$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '49.00' ] ) ] );
				return;
			case 'billing':
				$this->haveCustomerAddress( $this->swedishAddress( [ 'address_1' => 'Nygatan 7' ] ), $this->swedishAddress() );
				return;
			case 'shipping':
				$this->haveChosenFlatRateShipping( 'SE', '50.00' );
				return;
			case 'coupon':
				$this->haveAppliedCoupon( 'kp-session-10', 10 );
				return;
			case 'locale':
				add_filter( 'locale', static fn() => 'sv_SE' );
				return;
		}
	}

	public function test_a_country_change_starts_a_new_session_rather_than_patching_the_old(): void {
		$this->configureStore( [ 'country' => 'DE', 'currency' => 'EUR', 'calc_taxes' => false ] );
		$this->setKlarnaSettings(
			[
				'enabled'             => 'yes', 'testmode' => 'yes',
				'test_merchant_id_de' => 'mid-de', 'test_shared_secret_de' => 'secret-de',
				'test_merchant_id_at' => 'mid-at', 'test_shared_secret_at' => 'secret-at',
				'available_countries' => [ 'de', 'at' ], 'customer_type' => 'b2c',
			]
		);
		$this->reloadPaymentGateways();
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->haveCustomerAddress( $this->swedishAddress( [ 'country' => 'DE' ] ) );
		$this->recalculateCart();
		$this->resetHttpInterception();

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$this->haveCustomerAddress( $this->swedishAddress( [ 'country' => 'AT' ] ) );
		$this->recalculateCart();

		$this->willCreateSession( [ 'session_id' => 'sess-2' ] );
		KP_WC()->session->get_session();

		$this->assertSessionCalls( 2, 0 );
		$this->assertSame( 'AT', $this->storedSession()['session_country'] );
		$this->assertSame( 'DE', $this->createCalls()[0]['json']['purchase_country'] );
		$this->assertSame( 'EUR', $this->createCalls()[0]['json']['purchase_currency'] );
	}

	/**
	 * @dataProvider provide_order_bodies
	 */
	public function test_the_klarna_order_body_matches_the_snapshot( string $scenario ): void {
		$placeholders = $this->arrangeOrderBody( $scenario );

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$this->assertRequestMatchesSnapshot( $this->createCalls()[0], 'session-' . $scenario, $placeholders );
	}

	/** @return array<string, array{0: string}> */
	public function provide_order_bodies(): array {
		return [
			'b2c SE'            => [ 'se-b2c' ],
			'b2b SE'            => [ 'se-b2b' ],
			'US sales tax'      => [ 'us-sales-tax' ],
			'cart fee'          => [ 'cart-fee' ],
			'gift card'         => [ 'gift-card' ],
			'no SKU'            => [ 'no-sku' ],
			'iframe colours'    => [ 'iframe-colours' ],
			'shipping'          => [ 'shipping' ],
			'coupon'            => [ 'coupon' ],
		];
	}

	/** @return array<string, scalar> Volatile values to mask out of the snapshot. */
	private function arrangeOrderBody( string $scenario ): array {
		$product = $this->haveSimpleProduct( [ 'name' => 'Klarna Test Product', 'sku' => 'kp-test-1', 'price' => '100.00' ] );

		switch ( $scenario ) {
			case 'se-b2b':
				$this->haveKlarnaCredentials( 'se', [ 'customer_type' => 'b2b' ] );
				$this->reloadPaymentGateways();
				break;
			case 'us-sales-tax':
				$this->deleteAllTaxRates();
				$this->configureUsStore();
				$this->haveKlarnaCredentials( 'us' );
				$this->reloadPaymentGateways();
				$this->haveCustomerAddress( $this->usAddress(), $this->usAddress() );
				break;
			case 'cart-fee':
				$this->haveCartFee( 'Handling fee', 25.00 );
				break;
			case 'gift-card':
				// The PW Gift Cards integration reads straight from the WC session.
				WC()->session->set( 'pw-gift-card-data', [ 'gift_cards' => [ 'KP-GIFT-1' => 100.00 ] ] );
				break;
			case 'no-sku':
				$product = $this->haveSimpleProduct( [ 'name' => 'No SKU Product', 'sku' => '', 'price' => '100.00' ] );
				break;
			case 'iframe-colours':
				$this->haveKlarnaCredentials( 'se', [ 'color_border' => '#ff0000' ] );
				$this->reloadPaymentGateways();
				break;
		}

		$this->haveCartWith( [ [ $product, 2 ] ] );

		if ( 'shipping' === $scenario ) {
			$this->haveChosenFlatRateShipping( 'SE', '50.00' );
		}

		if ( 'coupon' === $scenario ) {
			$this->haveAppliedCoupon( 'kp-session-10', 10 );
		}

		$this->resetHttpInterception();

		return [ '<product-id>' => $product->get_id() ];
	}

	public function test_the_order_lines_add_up_to_the_order_amount(): void {
		$this->haveCartWith(
			[
				[ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 2 ],
				[ $this->haveSimpleProduct( [ 'price' => '59.50' ] ), 3 ],
			]
		);
		$this->haveCartFee( 'Handling fee', 25.00 );
		$this->haveChosenFlatRateShipping( 'SE', '50.00' );
		$this->recalculateCart();
		$this->resetHttpInterception();

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$body = $this->createCalls()[0]['json'];

		$this->assertSame(
			$body['order_amount'],
			array_sum( array_column( $body['order_lines'], 'total_amount' ) ),
			'Klarna rejects a session whose order lines do not sum to the order amount.'
		);
	}

	public function test_the_order_line_and_country_filters_are_applied(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->configureStore( [ 'currency' => 'NOK', 'calc_taxes' => false ] );
		$this->haveKlarnaCredentials( 'no' );
		$this->reloadPaymentGateways();
		$this->resetHttpInterception();

		add_filter( 'wc_klarna_payments_country', static fn() => 'NO' );
		add_filter( 'kp_cart_line_item_merchant_data', static fn() => [ 'internal_id' => 'abc123' ] );
		add_filter( 'kp_cart_line_item_product_identifiers', static fn() => [ 'brand' => 'Krokedil' ] );
		add_filter( 'kp_cart_line_item_quantity_unit', static fn() => 'kg' );

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$body = $this->createCalls()[0]['json'];
		$line = $this->assertHasOrderLine( $body['order_lines'], 'physical' );

		$this->assertSame( 'NO', $body['purchase_country'] );
		$this->assertSame( [ 'internal_id' => 'abc123' ], $line['merchant_data'] );
		$this->assertSame( [ 'brand' => 'Krokedil' ], $line['product_identifiers'] );
		$this->assertSame( 'kg', $line['quantity_unit'] );
	}

	public function test_a_dead_session_is_dropped_rather_than_patched_again(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		$this->willCreateSession();
		KP_WC()->session->get_session();
		$this->assertNotEmpty( WC()->session->get( 'kp_session_data' ) );

		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '49.00' ] ) ] );

		// No queued response, so the update comes back as an error.
		ob_start();
		$result = KP_WC()->session->get_session();
		ob_end_clean();

		$this->assertWPError( $result );
		$this->assertEmpty( WC()->session->get( 'kp_session_data' ) );
	}

	public function test_it_exposes_the_session_details_the_frontend_needs(): void {
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ] );
		$this->resetHttpInterception();

		$this->assertNull( KP_WC()->session->get_klarna_session_id() );
		$this->assertSame( 'SE', KP_WC()->session->get_klarna_session_country(), 'Falls back to the Klarna country before a session exists.' );

		$this->willCreateSession( [ 'session_id' => 'sess-abc' ] );
		KP_WC()->session->get_session();

		$session = KP_WC()->session;
		$this->assertSame( 'token-1', $session->get_klarna_client_token() );
		$this->assertSame( 'sess-abc', $session->get_klarna_session_id() );
		$this->assertSame( 'SE', $session->get_klarna_session_country() );
		$this->assertSame( 'pay_later', $session->get_klarna_payment_method_categories()[0]['identifier'] );

		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'price' => '49.00' ] ) ] );
		$this->willUpdateSession( 'sess-abc' );
		KP_WC()->session->get_session();

		$this->assertStringEndsWith( '/payments/v1/sessions/sess-abc', $this->updateCalls()[0]['url'] );
	}

	public function test_an_order_pay_session_belongs_to_the_order_not_the_shopper(): void {
		$this->haveKlarnaCredentials(
			'se',
			[
				'test_merchant_id_no' => 'mid-no', 'test_shared_secret_no' => 'secret-no',
				'available_countries' => [ 'se', 'no' ],
			]
		);
		$this->reloadPaymentGateways();

		$order = $this->haveOrder(
			[
				'items'    => [ $this->haveSimpleProduct() ],
				'billing'  => $this->swedishAddress( [ 'country' => 'NO' ] ),
				'currency' => 'EUR',
			]
		);
		$this->resetHttpInterception();

		$this->willCreateSession();
		KP_WC()->session->get_session( $order );

		$stored = json_decode( $this->reload( $order )->get_meta( '_kp_session_data', true ), true );

		$this->assertSame( 'sess-1', $stored['klarna_session']['session_id'] );
		$this->assertEmpty( WC()->session->get( 'kp_session_data' ) );

		$body = $this->createCalls()[0]['json'];
		$this->assertSame( 'NO', $body['purchase_country'], 'The order billing country wins over the SE store base.' );
		$this->assertSame( 'EUR', $body['purchase_currency'], 'The order currency wins over the SEK store currency.' );
	}

	public function test_an_order_id_that_does_not_resolve_is_an_error(): void {
		$result = KP_WC()->session->get_session( 999999999 );

		$this->assertWpErrorCode( 'kp_order_not_found', $result );
		$this->assertNoKlarnaRequests();
	}

	public function test_the_session_is_refreshed_after_the_cart_totals_are_calculated(): void {
		$this->assertSame(
			999999,
			has_action( 'woocommerce_after_calculate_totals', [ new \KP_Session(), 'get_session' ] ),
			'This wiring is what keeps the Klarna session in step with the cart.'
		);
	}

	private function haveAppliedCoupon( string $code, int $percent ): void {
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->save();

		WC()->cart->apply_coupon( $code );
		$this->recalculateCart();
	}

	private function willUpdateSession( string $session_id = 'sess-1' ): void {
		$this->willRespondWith( [], 200, "/payments/v1/sessions/{$session_id}" );
	}

	/** @return array<string, mixed> */
	private function storedSession(): array {
		return json_decode( WC()->session->get( 'kp_session_data' ), true );
	}

	private function createCalls(): array {
		return $this->sessionCallsMatching( '#/payments/v1/sessions$#' );
	}

	private function updateCalls(): array {
		return $this->sessionCallsMatching( '#/payments/v1/sessions/.+$#' );
	}

	private function sessionCallsMatching( string $pattern ): array {
		return array_values(
			array_filter(
				$this->httpRequests(),
				static fn( $request ) => (bool) preg_match( $pattern, $request['url'] )
			)
		);
	}

	private function assertSessionCalls( int $creates, int $updates ): void {
		$this->assertSame(
			[ 'creates' => $creates, 'updates' => $updates ],
			[ 'creates' => count( $this->createCalls() ), 'updates' => count( $this->updateCalls() ) ],
			'Requests made: ' . implode( ', ', array_column( $this->httpRequests(), 'url' ) )
		);
	}
}
