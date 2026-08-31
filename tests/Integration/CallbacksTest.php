<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The Klarna authorization callback. process_hpp_redirect() reads its arguments
 * with filter_input( INPUT_GET ), which stays null in CLI, so it belongs to E2E.
 *
 * @covers \KP_Callbacks::kp_wc_authorization
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

	/** The order management lookup that names the payment method. */
	private function willFetchKlarnaOrder(): void {
		$this->willRespondWith(
			[ 'initial_payment_method' => [ 'description' => 'Pay Later' ] ],
			200,
			'ordermanagement/v1/orders'
		);
	}
}
