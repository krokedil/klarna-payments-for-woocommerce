<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * Capture, cancel, update and refund. The four operations differ in endpoint and
 * body but share their guards, so most of this file is one provider over all four.
 *
 * @covers \Krokedil\Klarna\OrderManagement
 * @covers \Krokedil\Klarna\OrderManagement\Request
 * @covers \WC_Gateway_Klarna_Payments::process_refund
 */
class OrderManagementTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	private const ENDPOINTS = [
		'capture' => [ '/captures', 'POST' ],
		'cancel'  => [ '/cancel', 'POST' ],
		'update'  => [ '/authorization', 'PATCH' ],
		'refund'  => [ '/refunds', 'POST' ],
	];

	private const AUTO_SETTINGS = [
		'capture' => 'kom_auto_capture',
		'cancel'  => 'kom_auto_cancel',
		'update'  => 'kom_auto_update',
	];

	/** @dataProvider provide_operations */
	public function test_the_operation_reaches_klarna( string $op ): void {
		$order = $this->haveOrderFor( $op );
		$this->willSucceed( $op );

		$this->assertTrue( $this->perform( $op, $order ) );

		list( $path, $method ) = self::ENDPOINTS[ $op ];
		$request               = $this->klarnaRequestTo( $path );

		$this->assertSame( $method, $request['method'] );
		$this->assertStringEndsWith( '/ordermanagement/v1/orders/klarna-order-123' . $path, $request['url'] );
		$this->assertStringEndsWith(
			'/ordermanagement/v1/orders/klarna-order-123',
			$this->klarnaRequests()[0]['url'],
			'Every operation looks the Klarna order up first.'
		);
		$this->assertKlarnaRequestCount( 2 );
	}

	/** @return array<string, array{0: string}> */
	public function provide_operations(): array {
		return [ 'capture' => [ 'capture' ], 'cancel' => [ 'cancel' ], 'update' => [ 'update' ], 'refund' => [ 'refund' ] ];
	}

	/**
	 * @dataProvider provide_request_bodies
	 */
	public function test_the_request_body_matches_the_snapshot( string $op, string $store ): void {
		if ( 'us' === $store ) {
			$this->configureUsStoreForOrderManagement();
		}

		$order = $this->haveOrderFor( $op, 'us' === $store );
		$this->willSucceed( $op );
		$this->perform( $op, $order );

		$this->assertRequestMatchesSnapshot(
			$this->klarnaRequestTo( self::ENDPOINTS[ $op ][0] ),
			sprintf( 'om-%s-%s', $op, $store ),
			[ '<refund-id>' => $this->refundIdOf( $order ), '<order-number>' => $order->get_order_number() ]
		);
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_request_bodies(): array {
		return [
			'capture, SE' => [ 'capture', 'se' ],
			'capture, US' => [ 'capture', 'us' ],
			'update, SE'  => [ 'update', 'se' ],
			'refund, SE'  => [ 'refund', 'se' ],
			'refund, US'  => [ 'refund', 'us' ],
		];
	}

	public function test_the_amounts_always_add_up_to_the_order_lines(): void {
		$order = $this->haveCapturableKlarnaOrder(
			[
				'items'    => [
					[ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 2 ],
					[ $this->haveSimpleProduct( [ 'price' => '49.50' ] ), 1 ],
				],
				'shipping' => [ 'total' => '50.00' ],
				'fees'     => [ [ 'total' => '10.00' ] ],
				'status'   => 'on-hold',
			]
		);

		$this->willSucceed( 'update' );
		$this->perform( 'update', $order );

		$body = $this->klarnaRequestTo( '/authorization' )['json'];

		$this->assertEquals(
			$body['order_amount'],
			array_sum( array_column( $body['order_lines'], 'total_amount' ) ),
			'Klarna rejects a request whose lines do not add up to the amount.'
		);
		$this->assertEquals(
			$body['order_tax_amount'],
			array_sum( array_column( $body['order_lines'], 'total_tax_amount' ) )
		);
	}

	/**
	 * The WooCommerce hook each operation is wired to in production.
	 *
	 * @dataProvider provide_wiring
	 */
	public function test_the_woocommerce_hook_drives_the_operation( string $op, string $trigger ): void {
		$order = $this->haveOrderFor( $op );
		$this->willSucceed( $op );

		if ( 'saved_items' === $trigger ) {
			do_action( 'woocommerce_saved_order_items', $order->get_id(), [] );
		} else {
			$order->update_status( $trigger );
		}

		$this->assertKlarnaRequestCount( 1, self::ENDPOINTS[ $op ][0] );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_wiring(): array {
		return [
			'completing the order captures' => [ 'capture', 'completed' ],
			'cancelling the order cancels'  => [ 'cancel', 'cancelled' ],
			'saving the items updates'      => [ 'update', 'saved_items' ],
		];
	}

	/**
	 * @dataProvider provide_switchable_operations
	 */
	public function test_an_operation_is_skipped_when_it_is_switched_off( string $op, $expected ): void {
		$this->haveKlarnaCredentials( 'se', [ self::AUTO_SETTINGS[ $op ] => 'no' ] );

		$order = $this->haveOrderFor( $op );

		$this->assertSame( $expected, $this->perform( $op, $order ) );
		$this->assertNoKlarnaRequests();
	}

	/**
	 * A merchant clicking the button in wp-admin overrides the automatic setting.
	 *
	 * @dataProvider provide_switchable_operations
	 */
	public function test_a_merchant_action_overrides_the_setting( string $op ): void {
		$this->haveKlarnaCredentials( 'se', [ self::AUTO_SETTINGS[ $op ] => 'no' ] );

		$order = $this->haveOrderFor( $op );
		$this->willSucceed( $op );

		$this->assertTrue( $this->perform( $op, $order, true ) );
		$this->assertKlarnaRequestCount( 1, self::ENDPOINTS[ $op ][0] );
	}

	/** @return array<string, array{0: string, 1: bool|null}> */
	public function provide_switchable_operations(): array {
		return [
			// Capture notes the skip and returns null; the other two treat it as a no-op success.
			'capture' => [ 'capture', null ],
			'cancel'  => [ 'cancel', true ],
			'update'  => [ 'update', true ],
		];
	}

	/**
	 * The guards that stop an operation before Klarna is ever contacted.
	 *
	 * @dataProvider provide_ineligible_orders
	 */
	public function test_an_ineligible_order_is_left_alone( string $op, string $guard, ?string $error_code, ?string $note ): void {
		$order = $this->haveOrderFor( $op );
		$this->applyGuard( $order, $guard );

		$result = $this->perform( $op, $order );

		if ( null === $error_code ) {
			$this->assertNull( $result );
		} else {
			$this->assertWpErrorCode( $error_code, $result );
		}

		$this->assertNoKlarnaRequests();

		if ( null !== $note ) {
			$this->assertOrderHasNote( $order, $note );
		}
	}

	/** @return array<string, array{0: string, 1: string, 2: string|null, 3: string|null}> */
	public function provide_ineligible_orders(): array {
		$disconnected = 'Klarna %s request was not sent because order management is disabled for this order.';

		return [
			'capture, another gateway'   => [ 'capture', 'other-gateway', null, null ],
			'cancel, another gateway'    => [ 'cancel', 'other-gateway', 'not_klarna_order', null ],
			'update, another gateway'    => [ 'update', 'other-gateway', null, null ],
			'refund, another gateway'    => [ 'refund', 'other-gateway', null, null ],
			'capture, disconnected'      => [ 'capture', 'disconnected', 'order_sync_off', sprintf( $disconnected, 'capture' ) ],
			'cancel, disconnected'       => [ 'cancel', 'disconnected', 'order_sync_off', null ],
			'update, disconnected'       => [ 'update', 'disconnected', 'order_sync_off', null ],
			'refund, disconnected'       => [ 'refund', 'disconnected', 'order_sync_off', null ],
			'capture, unpaid'            => [ 'capture', 'unpaid', 'not_paid', null ],
			'cancel, unpaid'             => [ 'cancel', 'unpaid', 'not_paid', null ],
			'update, unpaid'             => [ 'update', 'unpaid', 'not_paid', null ],
			'capture, already captured'  => [ 'capture', 'captured', 'already_captured', 'Klarna order has already been captured.' ],
			'capture, no klarna order id' => [ 'capture', 'no-klarna-id', 'klarna_id_missing', 'Klarna order ID is missing, Klarna order could not be captured at this time.' ],
			'cancel, rejected in pending flow' => [ 'cancel', 'pending-to-cancelled', 'rejected_in_pending_flow', null ],
			'update, status not allowed' => [ 'update', 'processing', 'not_allowed_status', null ],
			'refund, never captured'     => [ 'refund', 'no-capture-id', 'not_captured', 'Klarna order has not been captured and cannot be refunded.' ],
		];
	}

	public function test_an_order_without_a_klarna_order_id_is_put_on_hold(): void {
		$order = $this->haveOrderFor( 'capture' );
		$this->applyGuard( $order, 'no-klarna-id' );

		$this->perform( 'capture', $order );

		$this->assertSame( 'on-hold', $this->statusOf( $order ) );
	}

	/**
	 * What Klarna's own status says the operation may do.
	 *
	 * @dataProvider provide_klarna_statuses
	 */
	public function test_the_klarna_order_status_decides_whether_the_operation_runs(
		string $op,
		array $klarna_order,
		$expected,
		?string $note
	): void {
		$order = $this->haveOrderFor( $op );

		$this->willRetrieveKlarnaOrder( $klarna_order );

		$result = $this->perform( $op, $order );

		if ( is_string( $expected ) ) {
			$this->assertWpErrorCode( $expected, $result );
		} else {
			$this->assertSame( $expected, $result );
		}

		$this->assertKlarnaRequestCount( 1, '', 'Only the lookup; the operation itself must not be sent.' );

		if ( null !== $note ) {
			$this->assertOrderHasNote( $order, $note );
		}
	}

	/** @return array<string, array{0: string, 1: array, 2: mixed, 3: string|null}> */
	public function provide_klarna_statuses(): array {
		return [
			'capture, cancelled at klarna'  => [ 'capture', [ 'status' => 'CANCELLED' ], 'klarna_order_cancelled', 'Klarna order failed to capture, the order has already been canceled' ],
			'capture, pending fraud review' => [ 'capture', [ 'fraud_status' => 'PENDING' ], 'pending_fraud_review', 'Klarna order is pending review and could not be captured at this time.' ],
			'cancel, fully captured'        => [ 'cancel', [ 'status' => 'CAPTURED' ], 'already_captured', 'The Klarna order cannot be cancelled due to it already being captured.' ],
			'cancel, partially captured'    => [ 'cancel', [ 'status' => 'PART_CAPTURED' ], 'already_captured', 'The Klarna order cannot be cancelled due to it already being captured.' ],
			'cancel, already cancelled'     => [ 'cancel', [ 'status' => 'CANCELLED' ], 'already_cancelled', 'Klarna order has already been cancelled.' ],
			'update, cancelled at klarna'   => [ 'update', [ 'status' => 'CANCELLED' ], true, null ],
			'update, fully captured'        => [ 'update', [ 'status' => 'CAPTURED' ], true, null ],
			'refund, never captured'        => [ 'refund', [ 'status' => 'AUTHORIZED' ], 'not_captured', 'Klarna order has not been captured and cannot be refunded.' ],
			'refund, cancelled'             => [ 'refund', [ 'status' => 'CANCELLED' ], 'not_captured', 'Klarna order has not been captured and cannot be refunded.' ],
			'refund, expired'               => [ 'refund', [ 'status' => 'EXPIRED' ], 'not_captured', 'Klarna order has not been captured and cannot be refunded.' ],
		];
	}

	/**
	 * @dataProvider provide_lookup_failures
	 */
	public function test_a_failed_lookup_stops_the_operation( string $op, string $note ): void {
		$order = $this->haveOrderFor( $op );

		// No response queued, so the lookup is the blocked request.
		$this->assertWpErrorCode( 'object_error', $this->perform( $op, $order ) );
		$this->assertKlarnaRequestCount( 1, '', 'Acting on an order whose state is unknown is not safe.' );
		$this->assertOrderHasNote( $order, $note );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_lookup_failures(): array {
		return [
			'capture' => [ 'capture', 'Klarna order could not be captured due to an error.' ],
			'cancel'  => [ 'cancel', 'Klarna order could not be cancelled due to an error.' ],
			'update'  => [ 'update', 'Klarna order could not be updated due to an error.' ],
		];
	}

	/**
	 * @dataProvider provide_rejections
	 */
	public function test_klarna_rejecting_the_operation_is_noted( string $op, string $reason, string $error_code, string $note, ?string $unset_meta ): void {
		$order = $this->haveOrderFor( $op );

		$this->willRetrieveKlarnaOrder( $this->lookupFor( $op ) );
		$this->willRejectWith( self::ENDPOINTS[ $op ][0], $reason );

		$this->assertWpErrorCode( $error_code, $this->perform( $op, $order ) );
		$this->assertOrderHasNote( $order, $note );

		if ( null !== $unset_meta ) {
			$this->assertSame( '', $this->reload( $order )->get_meta( $unset_meta ), 'A failed operation must not look like it succeeded.' );
		}
	}

	/** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string|null}> */
	public function provide_rejections(): array {
		return [
			'capture' => [ 'capture', 'Order is not in a capturable state.', 'capture_failed', 'Could not capture Klarna order. Order is not in a capturable state.', '_wc_klarna_capture_id' ],
			'cancel'  => [ 'cancel', 'Order has already been captured', 'unknown_error', 'Could not cancel Klarna order. Order has already been captured.', '_wc_klarna_cancelled' ],
			'update'  => [ 'update', 'Amount exceeds the authorized amount', 'unknown_error', 'Could not update Klarna order lines: Amount exceeds the authorized amount.', null ],
			'refund'  => [ 'refund', 'Refund amount is higher than remaining captured amount', 'refund_failed', 'Could not refund Klarna order. Refund amount is higher than remaining captured amount.', null ],
		];
	}

	/**
	 * @dataProvider provide_body_filters
	 */
	public function test_the_request_body_can_be_filtered( string $op, string $filter, string $key, $value ): void {
		$order = $this->haveOrderFor( $op );

		// The refund filter is handed the order lines themselves, not the whole body.
		$replaces_everything = 'refund' === $op;

		add_filter(
			$filter,
			static function ( $data ) use ( $key, $value, $replaces_everything ) {
				if ( $replaces_everything ) {
					return $value;
				}

				$data[ $key ] = $value;

				return $data;
			}
		);

		$this->willSucceed( $op );
		$this->perform( $op, $order );

		$this->assertSame( $value, $this->klarnaRequestTo( self::ENDPOINTS[ $op ][0] )['json'][ $key ] );
	}

	/** @return array<string, array{0: string, 1: string, 2: string, 3: mixed}> */
	public function provide_body_filters(): array {
		return [
			'capture' => [ 'capture', 'kom_order_capture_args', 'captured_amount', 1 ],
			'update'  => [ 'update', 'kom_order_update_args', 'order_amount', 1 ],
			'refund'  => [ 'refund', 'kom_refund_order_args', 'order_lines', [ [ 'type' => 'replaced' ] ] ],
		];
	}

	/**
	 * @dataProvider provide_success_records
	 */
	public function test_a_successful_operation_records_itself_on_the_order( string $op, string $note, ?array $meta ): void {
		$order = $this->haveOrderFor( $op );
		$this->willSucceed( $op );
		$this->perform( $op, $order );

		$this->assertOrderHasNote( $order, $note );

		if ( null !== $meta ) {
			$this->assertSame( $meta[1], $this->reload( $order )->get_meta( $meta[0] ) );
		}
	}

	/** @return array<string, array{0: string, 1: string, 2: array|null}> */
	public function provide_success_records(): array {
		return [
			'capture records the capture id' => [ 'capture', 'Capture ID: capture-123', [ '_wc_klarna_capture_id', 'capture-123' ] ],
			'cancel flags the order'         => [ 'cancel', 'Klarna order cancelled.', [ '_wc_klarna_cancelled', 'yes' ] ],
			'update notes the edit'          => [ 'update', 'Klarna order updated.', null ],
		];
	}

	/**
	 * The store is Swedish and the order is in euros, so the symbol proves the note
	 * reads the order rather than the store.
	 */
	public function test_a_refund_note_quotes_the_orders_own_currency(): void {
		$order = $this->haveOrderFor( 'refund' );
		$this->willSucceed( 'refund' );
		$this->perform( 'refund', $order );

		$this->assertOrderHasNote(
			$order,
			'Processing a refund of ' . wc_price( 125.00, [ 'currency' => 'EUR' ] ) . ' with Klarna.'
		);
	}

	public function test_force_full_capture_lets_klarna_decide_the_amount(): void {
		$this->haveKlarnaCredentials( 'se', [ 'kom_force_full_capture' => 'yes' ] );

		$order = $this->haveOrderFor( 'capture' );

		$this->willRetrieveKlarnaOrder( [ 'remaining_authorized_amount' => 31337 ] );
		$this->willCapture();

		$this->assertTrue( $this->perform( 'capture', $order ) );

		$body = $this->klarnaRequestTo( '/captures' )['json'];
		$this->assertSame( 31337, $body['captured_amount'] );
		$this->assertArrayNotHasKey( 'order_lines', $body, 'Lines that do not match the authorised amount would be rejected.' );
	}

	public function test_a_capture_made_outside_woocommerce_is_adopted(): void {
		$order = $this->haveOrderFor( 'capture' );

		$this->willRetrieveKlarnaOrder(
			[
				'status'   => 'CAPTURED',
				'captures' => [ [ 'capture_id' => 'capture-out-of-band', 'captured_at' => '2026-01-02T10:00:00Z' ] ],
			]
		);

		$this->assertWpErrorCode( 'already_captured', $this->perform( 'capture', $order ) );
		$this->assertSame(
			'capture-out-of-band',
			$this->reload( $order )->get_meta( '_wc_klarna_capture_id' ),
			'A capture made elsewhere is adopted, so the order can still be refunded.'
		);
	}

	public function test_the_allowed_update_statuses_can_be_widened_by_a_filter(): void {
		$order = $this->haveOrderFor( 'update' );
		$order->set_status( 'processing' );
		$order->save();

		add_filter(
			'kom_allowed_update_statuses',
			static function ( $statuses ) {
				$statuses[] = 'processing';
				return $statuses;
			}
		);

		$this->willSucceed( 'update' );

		$this->assertTrue( $this->perform( 'update', $order ) );
		$this->assertKlarnaRequestCount( 1, '/authorization' );
	}

	public function test_saving_a_new_recurring_token_never_reaches_klarna(): void {
		$order = $this->haveOrderFor( 'update' );
		$order->update_meta_data( \KP_Subscription::RECURRING_TOKEN, 'token-old' );
		$order->save();

		$this->typeOrderAsSubscription( $order );

		$result = KP_WC()->order_management->update_klarna_order_items(
			$order->get_id(),
			[ \KP_Subscription::RECURRING_TOKEN => 'token-new' ]
		);

		$this->assertTrue( $result );
		$this->assertSame( 'token-new', $this->reload( $order )->get_meta( \KP_Subscription::RECURRING_TOKEN ) );
		$this->assertOrderHasNote( $order, 'updated the subscription recurring token from "token-old" to "token-new"' );
		$this->assertNoKlarnaRequests( 'The merchant cannot have edited the lines in the same save.' );
	}

	public function test_refunding_through_the_gateway_refunds_the_klarna_order(): void {
		$order = $this->haveOrderFor( 'refund' );
		$this->willSucceed( 'refund' );

		$gateway = WC()->payment_gateways()->payment_gateways()['klarna_payments'];

		$this->assertTrue( $gateway->process_refund( $order->get_id(), '125.00', 'Damaged in transit' ) );

		$body = $this->klarnaRequestTo( '/refunds' )['json'];
		$this->assertSame( 'Damaged in transit', $body['description'] );
		$this->assertEquals( 12500, $body['refunded_amount'] );
	}

	public function test_a_return_fee_is_named_in_the_refund_note(): void {
		$order = $this->haveOrderFor( 'refund' );

		// Stands in for RequestPostRefund's own callback, which reads $_POST and so never runs under CLI.
		add_filter( 'klarna_applied_return_fees', static fn() => [ 'amount' => 50.0, 'tax_amount' => 12.5 ] );

		$this->willSucceed( 'refund' );
		$this->perform( 'refund', $order );

		$this->assertOrderHasNote(
			$order,
			sprintf(
				'Processing a refund of %1$s with Klarna (original amount of %2$s - return fee of %3$s).',
				wc_price( 125.00, [ 'currency' => 'EUR' ] ),
				wc_price( 187.50, [ 'currency' => 'EUR' ] ),
				wc_price( 62.50, [ 'currency' => 'EUR' ] )
			)
		);
	}

	/**
	 * @return bool|null|\WP_Error
	 */
	private function perform( string $op, \WC_Order $order, bool $action = false ) {
		$om = KP_WC()->order_management;

		switch ( $op ) {
			case 'capture':
				return $om->capture_klarna_order( $order->get_id(), $action );
			case 'cancel':
				return $om->cancel_klarna_order( $order->get_id(), $action );
			case 'update':
				return $om->update_klarna_order_items( $order->get_id(), [], $action );
			default:
				// The leading false seeds the filter refund_klarna_order() is hooked to.
				return $om->refund_klarna_order( false, $order->get_id(), '125.00', '' );
		}
	}

	/**
	 * The order state each operation expects: paid and processing for a capture,
	 * on-hold for an update, captured with a refund object for a refund.
	 */
	private function haveOrderFor( string $op, bool $us = false ): \WC_Order {
		$address = $us ? $this->usAddress() : $this->swedishAddress();
		$klarna  = $us ? [ 'country' => 'US' ] : true;

		if ( 'cancel' === $op ) {
			return $this->haveKlarnaOrder( [ 'paid' => true, 'billing' => $address, 'klarna' => $klarna ] );
		}

		if ( 'refund' === $op ) {
			// 2 x 100.00 + 25% VAT, so one unit is exactly 125.00. Billed in euros, unlike the store.
			$order = $this->haveKlarnaOrder(
				[
					'items'    => [ [ $this->haveSimpleProduct( [ 'name' => 'KP Test Product', 'sku' => 'kp-test-1', 'price' => '100.00' ] ), 2 ] ],
					'currency' => $us ? 'USD' : 'EUR',
					'billing'  => $address,
					'klarna'   => $klarna,
				]
			);
			$order->update_meta_data( '_wc_klarna_capture_id', 'capture-123' );
			$order->save();

			$item_id = array_key_first( $order->get_items() );
			$this->haveRefundForItems( $order, [ $item_id ], '', [ $item_id => 1 ] );

			return $order;
		}

		return $this->haveCapturableKlarnaOrder(
			[
				'items'   => [ [ $this->haveSimpleProduct( [ 'name' => 'KP Test Product', 'sku' => 'kp-test-1', 'price' => '100.00' ] ), 2 ] ],
				'status'  => 'update' === $op ? 'on-hold' : 'processing',
				'billing' => $address,
				'klarna'  => $klarna,
			]
		);
	}

	/** Queues the lookup plus the successful response for the operation. */
	private function willSucceed( string $op ): void {
		$this->willRetrieveKlarnaOrder( $this->lookupFor( $op ) );

		switch ( $op ) {
			case 'capture':
				$this->willCapture();
				return;
			case 'cancel':
				$this->willCancel();
				return;
			case 'update':
				$this->willAcceptTheUpdate();
				return;
			default:
				$this->willRespondWith( [ 'refund_id' => 'refund-1' ], 201, '/refunds' );
		}
	}

	/** A refund needs the Klarna order to be holding captured money. */
	private function lookupFor( string $op ): array {
		return 'refund' === $op ? [ 'status' => 'CAPTURED' ] : [];
	}

	private function applyGuard( \WC_Order $order, string $guard ): void {
		switch ( $guard ) {
			case 'other-gateway':
				$order->set_payment_method( 'cod' );
				break;
			case 'disconnected':
				$order->update_meta_data( '_kom_disconnect', 'yes' );
				break;
			case 'unpaid':
				$order->set_date_paid( null );
				break;
			case 'captured':
				$order->update_meta_data( '_wc_klarna_capture_id', 'capture-already-done' );
				break;
			case 'no-capture-id':
				$order->delete_meta_data( '_wc_klarna_capture_id' );
				break;
			case 'no-klarna-id':
				$order->update_meta_data( '_wc_klarna_order_id', '' );
				$order->set_transaction_id( '' );
				break;
			case 'pending-to-cancelled':
				$order->update_meta_data( '_wc_klarna_pending_to_cancelled', 'yes' );
				break;
			case 'processing':
				$order->set_status( 'processing' );
				break;
		}

		$order->save();
	}

	private function configureUsStoreForOrderManagement(): void {
		$this->deleteAllTaxRates();
		$this->configureUsStore();
		$this->haveKlarnaCredentials( 'us' );
	}

	private function refundIdOf( \WC_Order $order ): int {
		$refunds = $order->get_refunds();

		return empty( $refunds ) ? 0 : $refunds[0]->get_id();
	}

	/** Makes wc_get_order() hand back a subscription for this order id. */
	private function typeOrderAsSubscription( \WC_Order $order ): void {
		$subscription_id = $order->get_id();

		add_filter(
			'woocommerce_order_class',
			static function ( $classname, $order_type, $order_id ) use ( $subscription_id ) {
				return (int) $order_id === $subscription_id ? SubscriptionTypedOrder::class : $classname;
			},
			10,
			3
		);
	}
}

/**
 * A WooCommerce order that reports itself as a subscription. Only the type is faked.
 */
class SubscriptionTypedOrder extends \WC_Order {

	public function get_type() {
		return 'shop_subscription';
	}
}
