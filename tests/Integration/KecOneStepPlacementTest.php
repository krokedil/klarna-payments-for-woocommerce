<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\ExpressCheckout\KECOneStepIntegration;
use Tests\Support\IntegrationTestCase;

/**
 * What a completed one-step Klarna Express Checkout payment does to the order.
 *
 * @covers \Krokedil\Klarna\ExpressCheckout\KECOneStepIntegration::process_order_state
 */
class KecOneStepPlacementTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se-no-tax';

	/**
	 * @dataProvider provide_verdicts
	 */
	public function test_klarnas_fraud_verdict_decides_the_order( string $verdict, string $status, string $klarna_order_id ): void {
		$order = $this->haveOrderAwaitingPayment();
		$this->willPlaceOrder( $verdict );
		$this->willFetchKlarnaOrder();

		$this->completePayment( $order );

		$saved = $this->reload( $order );

		$this->assertSame( $status, $saved->get_status() );
		$this->assertSame( $klarna_order_id, (string) $saved->get_meta( '_wc_klarna_order_id', true ) );

		if ( 'processing' === $status ) {
			$this->assertNotEmpty( $saved->get_date_paid(), 'Only an accepted placement pays the order.' );
			return;
		}

		$this->assertEmpty( $saved->get_date_paid(), "A {$verdict} verdict must not release the order for fulfilment." );
	}

	/** @return array<string, array{0: string, 1: string, 2: string}> */
	public function provide_verdicts(): array {
		return [
			'accepted'                => [ 'ACCEPTED', 'processing', 'klarna-order-123' ],
			'pending fraud review'    => [ 'PENDING', 'on-hold', 'klarna-order-123' ],
			'rejected'                => [ 'REJECTED', 'failed', '' ],
			'an unrecognised verdict' => [ 'SOMETHING_NEW', 'on-hold', 'klarna-order-123' ],
		];
	}

	/**
	 * Placing a one-step payment token returns only the order ID, so the verdict is read from the Klarna order.
	 *
	 * @dataProvider provide_klarna_order_verdicts
	 */
	public function test_the_klarna_orders_verdict_decides_when_placement_returns_none( ?string $verdict, string $status ): void {
		$order = $this->haveOrderAwaitingPayment();
		$this->willRespondWith( [ 'order_id' => 'klarna-order-123' ], 200, '/authorizations/auth-token-1/order' );
		if ( null === $verdict ) {
			$this->willRespondWith( [ 'error_code' => 'NOT_FOUND' ], 404, 'ordermanagement/v1/orders' );
		} else {
			$this->willRespondWith( [ 'fraud_status' => $verdict ], 200, 'ordermanagement/v1/orders' );
		}
		$this->willFetchKlarnaOrder();

		$this->completePayment( $order );

		$saved = $this->reload( $order );

		$this->assertSame( $status, $saved->get_status() );

		if ( 'processing' === $status ) {
			$this->assertNotEmpty( $saved->get_date_paid(), 'Only an accepted Klarna order pays the order.' );
			return;
		}

		$this->assertEmpty( $saved->get_date_paid(), 'Without an accepted verdict the order must not be released for fulfilment.' );
	}

	/** @return array<string, array{0: ?string, 1: string}> */
	public function provide_klarna_order_verdicts(): array {
		return [
			'accepted'                  => [ 'ACCEPTED', 'processing' ],
			'pending fraud review'      => [ 'PENDING', 'on-hold' ],
			'stopped'                   => [ 'STOPPED', 'failed' ],
			'the lookup fails'          => [ null, 'on-hold' ],
		];
	}

	public function test_an_unreachable_klarna_order_holds_the_order_under_the_plain_klarna_title(): void {
		$order = $this->haveOrderAwaitingPayment();
		$this->willRespondWith( [ 'order_id' => 'klarna-order-123' ], 200, '/authorizations/auth-token-1/order' );
		$this->willRespondWith( [ 'error_code' => 'NOT_FOUND' ], 404, 'ordermanagement/v1/orders' );
		$this->willRespondWith( [ 'error_code' => 'NOT_FOUND' ], 404, 'ordermanagement/v1/orders' );

		$this->completePayment( $order );

		$saved = $this->reload( $order );

		$this->assertSame( 'on-hold', $saved->get_status() );
		$this->assertSame( 'Klarna', $saved->get_payment_method_title() );
	}

	public function test_an_already_paid_order_is_not_placed_again(): void {
		$order = $this->haveOrderAwaitingPayment( [ 'status' => 'processing', 'paid' => true ] );

		$this->completePayment( $order );

		$this->assertNoKlarnaRequests( 'A repeated completed notification must not place a paid order again.' );
		$this->assertSame( 'processing', $this->reload( $order )->get_status() );
	}

	private function haveOrderAwaitingPayment( array $args = [] ): \WC_Order {
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

	private function completePayment( \WC_Order $order ): void {
		( new KECOneStepIntegration() )->process_order_state(
			$order,
			'interop-token-1',
			[],
			'COMPLETED',
			[
				'state'         => 'COMPLETED',
				'payment_token' => 'auth-token-1',
			]
		);
	}

	/** The order management lookup that names the payment method. */
	private function willFetchKlarnaOrder(): void {
		$this->willRespondWith(
			[ 'initial_payment_method' => [ 'description' => 'Pay Later' ] ],
			200,
			'ordermanagement/v1/orders'
		);
	}
}
