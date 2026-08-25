<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The token a subscription renews on, and the unattended renewal that charges it.
 * `KP_WC()->subscription` is null in this suite, the suite fakes the `wcs_*`
 * functions, not the plugin, so each test builds its own KP_Subscription.
 *
 * @covers \KP_Subscription
 */
class SubscriptionsTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * Whether a purchase mints a recurring token, and against which order.
	 *
	 * @dataProvider provide_tokenisation
	 */
	public function test_a_subscription_purchase_mints_a_recurring_token( string $scenario, string $expected_token, bool $calls_klarna ): void {
		// A differently named session customer, so the body proves the address came from the order.
		$this->haveCustomerAddress( $this->swedishAddress( [ 'first_name' => 'Session' ] ) );

		$parent = $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress( [ 'first_name' => 'Ordered' ] ),
				'klarna'  => true,
			]
		);

		if ( 'no-subscription' !== $scenario ) {
			$this->haveSubscriptionFor( $parent );
		}

		if ( $calls_klarna ) {
			$this->willCreateCustomerToken( 'customer-token-1', 'auth-token-1' );
		}

		$response = 'failed-purchase' === $scenario
			? new \WP_Error( 'kp_error', 'Klarna refused the purchase' )
			: [ 'order_id' => 'klarna-order-123' ];

		( new \KP_Subscription() )->add_recurring_token_to_order( $response, $parent->get_id(), 'auth-token-1' );

		$this->assertSame( $expected_token, (string) $this->reload( $parent )->get_meta( \KP_Subscription::RECURRING_TOKEN ) );

		if ( ! $calls_klarna ) {
			$this->assertNoKlarnaRequests();
			return;
		}

		$request = $this->klarnaRequestTo( '/customer-token' );
		$this->assertSame( 'POST', $request['method'] );
		$this->assertStringEndsWith( '/payments/v1/authorizations/auth-token-1/customer-token', $request['url'] );
		$this->assertSame(
			'Ordered',
			$request['json']['billing_address']['given_name'],
			'The token is created against the order that bought the subscription.'
		);
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_tokenisation(): array {
		return [
			'a subscription purchase' => [ 'subscription', 'customer-token-1', true ],
			'a purchase that failed'  => [ 'failed-purchase', '', false ],
			'no subscription bought'  => [ 'no-subscription', '', false ],
		];
	}

	/**
	 * A renewal charged unattended: it must record its Klarna order id or it
	 * cannot be reconciled.
	 *
	 * @dataProvider provide_renewals
	 */
	public function test_a_renewal_charges_the_recurring_token( bool $succeeds, string $subscription_status, string $note ): void {
		$parent = $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress(),
				'klarna'  => true,
			]
		);

		$subscription = $this->haveSubscriptionFor( $parent );
		\KP_Subscription::save_recurring_token( $parent->get_id(), 'customer-token-1' );

		$renewal = $this->haveRenewalOrderFor(
			$this->reload( $subscription ),
			[ 'items' => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ], 'billing' => $this->swedishAddress() ]
		);

		if ( $succeeds ) {
			$this->willRespondWith(
				[ 'order_id' => 'klarna-renewal-1', 'authorized_payment_method' => [ 'type' => 'invoice' ] ],
				200,
				'/tokens/customer-token-1/order'
			);
			$this->willRetrieveKlarnaOrder( [ 'order_id' => 'klarna-renewal-1' ] );
		} else {
			$this->willRejectWith( '/tokens/customer-token-1/order', 'The token was revoked' );
		}

		ob_start();
		( new \KP_Subscription() )->process_scheduled_payment( 125.00, $renewal );
		ob_end_clean();

		// '/order' alone would also match the order-management lookup made afterwards.
		$request = $this->klarnaRequestTo( '/tokens/customer-token-1/order' );

		$this->assertSame( 'POST', $request['method'] );
		$this->assertStringEndsWith( '/customer-token/v1/tokens/customer-token-1/order', $request['url'] );
		$this->assertOrderHasNote( $renewal, $note );

		// The charge resolves the subscription, not the renewal order it was billed on.
		$this->assertSame( $subscription_status, $this->statusOf( $this->reload( $subscription ) ) );

		if ( $succeeds ) {
			$this->assertSame( 'klarna-renewal-1', $this->reload( $renewal )->get_meta( '_wc_klarna_order_id', true ) );
			$this->assertSame( 'klarna-renewal-1', $this->reload( $subscription )->get_transaction_id() );
		}

		$this->assertSame(
			'customer-token-1',
			(string) $this->reload( $renewal )->get_meta( \KP_Subscription::RECURRING_TOKEN ),
			'The token survives either way, so the next renewal can still be attempted.'
		);
	}

	/** @return array<string, array{0: bool, 1: string, 2: string}> */
	public function provide_renewals(): array {
		return [
			// The subscription itself carries no items, so payment_complete() lands on completed.
			'a successful charge' => [ true, 'completed', 'Subscription payment made with Klarna. Klarna order id: klarna-renewal-1' ],
			'a refused charge'    => [ false, 'failed', 'Subscription payment failed with Klarna. Reason:' ],
		];
	}

	public function test_cancelling_a_subscription_cancels_the_token_at_klarna(): void {
		$parent = $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress(),
				'klarna'  => true,
			]
		);

		$subscription = $this->haveSubscriptionFor( $parent );
		\KP_Subscription::save_recurring_token( $parent->get_id(), 'customer-token-1' );

		$this->willRespondWith( [], 204, '/tokens/customer-token-1/status' );

		( new \KP_Subscription() )->cancel_scheduled_payment( $this->reload( $subscription ) );

		$request = $this->klarnaRequestTo( '/tokens/customer-token-1/status' );

		$this->assertSame( 'PATCH', $request['method'] );
		$this->assertStringEndsWith( '/customer-token/v1/tokens/customer-token-1/status', $request['url'] );
	}
}
