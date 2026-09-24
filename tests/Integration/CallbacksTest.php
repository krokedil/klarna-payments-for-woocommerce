<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The Klarna authorization callback. process_hpp_redirect() reads its arguments
 * with filter_input( INPUT_GET ), which stays null in CLI, so it belongs to E2E.
 *
 * @covers \KP_Callbacks::kp_wc_authorization
 * @covers \KP_Callbacks::handle_authorization_payload
 */
class CallbacksTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se-no-tax';

	/**
	 * What Klarna's verdict on the authorization does to the order.
	 *
	 * @dataProvider provide_verdicts
	 */
	public function test_the_authorization_verdict_decides_the_order( ?string $verdict, string $status, string $klarna_order_id ): void {
		$order = $this->haveOrderAwaitingAuthorization();

		if ( null !== $verdict ) {
			$this->willPlaceOrder( $verdict );
			$this->willFetchKlarnaOrder();
		}

		// A failed place-order call echoes before it returns.
		ob_start();
		( new \KP_Callbacks() )->kp_wc_authorization( $this->callbackData() );
		ob_end_clean();

		$saved = $this->reload( $order );

		$this->assertSame( $status, $saved->get_status() );
		$this->assertSame( $klarna_order_id, (string) $saved->get_meta( '_wc_klarna_order_id', true ) );

		if ( 'processing' === $status ) {
			$this->assertNotEmpty( $saved->get_date_paid(), 'Only an accepted authorization pays the order.' );
			return;
		}

		$this->assertEmpty( $saved->get_date_paid() );
	}

	/** @return array<string, array{0: string|null, 1: string, 2: string}> */
	public function provide_verdicts(): array {
		return [
			'accepted'              => [ 'ACCEPTED', 'processing', 'klarna-order-123' ],
			'pending fraud review'  => [ 'PENDING', 'on-hold', 'klarna-order-123' ],
			'rejected'              => [ 'REJECTED', 'failed', '' ],
			'an unrecognised verdict' => [ 'SOMETHING_NEW', 'pending', '' ],
			'the call itself failed' => [ null, 'pending', '' ],
		];
	}

	public function test_a_failed_place_order_call_is_noted_on_the_order(): void {
		$order = $this->haveOrderAwaitingAuthorization();

		// No queued response, so the place-order request comes back an error.
		ob_start();
		( new \KP_Callbacks() )->kp_wc_authorization( $this->callbackData() );
		ob_end_clean();

		$this->assertOrderHasNote( $order, 'Failed to complete the order' );
	}

	public function test_the_order_is_placed_against_the_authorization_token(): void {
		$order = $this->haveOrderAwaitingAuthorization();

		$this->willPlaceOrder( 'ACCEPTED' );
		$this->willFetchKlarnaOrder();

		( new \KP_Callbacks() )->kp_wc_authorization( $this->callbackData() );

		$request = $this->klarnaRequestTo( '/authorizations/' );

		$this->assertStringEndsWith( '/payments/v1/authorizations/auth-token-1/order', $request['url'] );
		$this->assertSame( (string) $order->get_order_number(), (string) $request['json']['merchant_reference1'] );
		$this->assertSame( $order->get_id(), (int) $request['json']['merchant_reference2'] );
		$this->assertNotEmpty( $request['json']['merchant_urls']['confirmation'] );
	}

	/**
	 * @dataProvider provide_ignorable_callbacks
	 */
	public function test_a_callback_it_cannot_act_on_is_ignored( string $session_id, bool $already_paid ): void {
		$order = $this->haveOrderAwaitingAuthorization();

		if ( $already_paid ) {
			$order->set_date_paid( time() );
			$order->save();
		}

		( new \KP_Callbacks() )->kp_wc_authorization( $this->callbackData( $session_id ) );

		$this->assertNoKlarnaRequests();
	}

	/** @return array<string, array{0: string, 1: bool}> */
	public function provide_ignorable_callbacks(): array {
		return [
			'no order carries that session' => [ 'some-other-session', false ],
			'the order already paid'        => [ 'sess-1', true ],
		];
	}

	/**
	 * What the endpoint refuses to queue. The Action Scheduler queue is shared with the
	 * store's own Klarna capture and cancellation jobs, so irrelevant work never enters it.
	 *
	 * @dataProvider provide_unusable_payloads
	 */
	public function test_a_payload_it_cannot_act_on_is_never_queued( array $payload ): void {
		$this->haveOrderAwaitingAuthorization();

		$status = ( new \KP_Callbacks() )->handle_authorization_payload( $payload );

		$this->assertSame( 400, $status );
		$this->assertSame( [], $this->queuedAuthorizations() );
	}

	/** @return array<string, array{0: array<string, mixed>}> */
	public function provide_unusable_payloads(): array {
		return [
			'nothing at all'                => [ [] ],
			'no session id'                 => [ [ 'authorization_token' => 'auth-token-1' ] ],
			'no authorization token'        => [ [ 'session_id' => 'sess-1' ] ],
			'an empty session id'           => [ [ 'session_id' => '', 'authorization_token' => 'auth-token-1' ] ],
			'a session id that is an array' => [ [ 'session_id' => [ 'sess-1' ], 'authorization_token' => 'auth-token-1' ] ],
		];
	}

	public function test_a_callback_for_a_session_no_order_carries_is_not_queued(): void {
		$this->haveOrderAwaitingAuthorization();

		$status = ( new \KP_Callbacks() )->handle_authorization_payload( $this->callbackData( 'some-other-session' ) );

		$this->assertSame( 200, $status, 'Answered as a success so Klarna does not retry a message this store can never act on.' );
		$this->assertSame( [], $this->queuedAuthorizations() );
	}

	public function test_a_callback_for_an_already_paid_order_is_not_queued(): void {
		$order = $this->haveOrderAwaitingAuthorization();
		$order->set_date_paid( time() );
		$order->save();

		$status = ( new \KP_Callbacks() )->handle_authorization_payload( $this->callbackData() );

		$this->assertSame( 200, $status );
		$this->assertSame( [], $this->queuedAuthorizations() );
	}

	public function test_a_recognised_callback_is_queued_with_only_the_fields_the_job_reads(): void {
		$this->haveOrderAwaitingAuthorization();

		$payload           = $this->callbackData();
		$payload['extra']  = str_repeat( 'x', 1000 );

		$status = ( new \KP_Callbacks() )->handle_authorization_payload( $payload );

		$this->assertSame( 200, $status );

		$queued = $this->queuedAuthorizations();
		$this->assertCount( 1, $queued );
		$this->assertSame(
			[ [ 'session_id' => 'sess-1', 'authorization_token' => 'auth-token-1' ] ],
			$queued[0]->get_args(),
			'Nothing beyond what the job reads is carried into the queue.'
		);
	}

	public function test_a_repeated_callback_for_the_same_session_is_throttled(): void {
		$this->haveOrderAwaitingAuthorization( 'sess-throttled' );
		$callbacks = new \KP_Callbacks();

		$first  = $callbacks->handle_authorization_payload( $this->callbackData( 'sess-throttled' ) );
		$second = $callbacks->handle_authorization_payload( $this->callbackData( 'sess-throttled' ) );

		$this->assertSame( [ 200, 429 ], [ $first, $second ] );
		$this->assertCount( 1, $this->queuedAuthorizations( 'sess-throttled' ), 'The queue keeps one job per Klarna session.' );
	}

	/**
	 * A callback nothing can be done with is answered 200 however often it arrives: throttling it
	 * would answer the retry with a 429 and ask Klarna to keep sending a message the store ignores.
	 *
	 * @dataProvider provide_ignorable_callbacks
	 */
	public function test_a_repeated_callback_it_cannot_act_on_is_never_throttled( string $session_id, bool $already_paid ): void {
		$order = $this->haveOrderAwaitingAuthorization();

		if ( $already_paid ) {
			$order->set_date_paid( time() );
			$order->save();
		}

		$callbacks = new \KP_Callbacks();

		$first  = $callbacks->handle_authorization_payload( $this->callbackData( $session_id ) );
		$second = $callbacks->handle_authorization_payload( $this->callbackData( $session_id ) );

		$this->assertSame( [ 200, 200 ], [ $first, $second ] );
		$this->assertSame( [], $this->queuedAuthorizations( $session_id ) );
	}

	public function test_the_throttle_window_is_filterable(): void {
		$this->haveOrderAwaitingAuthorization( 'sess-filterable' );
		$callbacks = new \KP_Callbacks();

		// Already expired rather than zero: WC_Rate_Limiter::retried_too_soon() still blocks on
		// `time() <= expiry`, so a zero second window only lets the next call through once the
		// clock ticks over.
		$expired = static function (): int {
			return -1;
		};

		add_filter( 'kp_authorization_callback_rate_limit', $expired );

		try {
			$first  = $callbacks->handle_authorization_payload( $this->callbackData( 'sess-filterable' ) );
			$second = $callbacks->handle_authorization_payload( $this->callbackData( 'sess-filterable' ) );
		} finally {
			remove_filter( 'kp_authorization_callback_rate_limit', $expired );
		}

		$this->assertSame( [ 200, 200 ], [ $first, $second ] );
		$this->assertCount( 2, $this->queuedAuthorizations( 'sess-filterable' ) );
	}

	public function test_the_callback_endpoints_are_registered(): void {
		$callbacks = new \KP_Callbacks();

		$this->assertNotFalse(
			has_action( 'woocommerce_api_kp_wc_authorization', [ $callbacks, 'authorization_cb' ] ),
			'This is the merchant_urls.authorization endpoint KP hands to Klarna.'
		);
		$this->assertNotFalse(
			has_action( 'kp_wc_authorization', [ $callbacks, 'kp_wc_authorization' ] ),
			'The Action Scheduler hook the endpoint defers the work to.'
		);
	}

	private function haveOrderAwaitingAuthorization( string $session_id = 'sess-1' ): \WC_Order {
		$order = $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress(),
			]
		);

		$order->update_meta_data( '_kp_session_id', $session_id );
		$order->save();

		return $order;
	}

	private function callbackData( string $session_id = 'sess-1' ): array {
		return [ 'session_id' => $session_id, 'authorization_token' => 'auth-token-1' ];
	}

	/**
	 * The jobs the endpoint has put on the shared Action Scheduler queue for one session.
	 * Scoped to the session so a test can never read a sibling test's leftovers.
	 *
	 * @return array<int, \ActionScheduler_Action>
	 */
	private function queuedAuthorizations( string $session_id = 'sess-1' ): array {
		return array_values(
			as_get_scheduled_actions(
				[
					'hook'     => 'kp_wc_authorization',
					'group'    => 'klarna_authorization',
					'search'   => $session_id,
					'status'   => 'pending',
					'per_page' => -1,
				]
			)
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
