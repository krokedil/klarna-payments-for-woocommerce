<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\ExpressCheckout\Api\Controllers\Notifications;
use Krokedil\Klarna\ExpressCheckout\Api\Notifications\PaymentStateCompleted;
use Krokedil\Klarna\ExpressCheckout\OneStepCheckout;
use Tests\Support\IntegrationTestCase;

/**
 * What Klarna Express Checkout leaves in the log and on the order, so a failed purchase can be traced.
 *
 * @covers \Krokedil\Klarna\ExpressCheckout\Logger
 * @covers \Krokedil\Klarna\ExpressCheckout\Api\Controllers\Notifications
 * @covers \Krokedil\Klarna\ExpressCheckout\Api\Notifications\PaymentStateCompleted
 * @covers \Krokedil\Klarna\ExpressCheckout\OneStepCheckout::create_order
 */
class KecLoggingTest extends IntegrationTestCase {

	private const SIGNING_KEY_ID = 'signing-key-id-1';
	private const SIGNING_KEY    = 'kec-signing-key-never-logged';

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveLoggingEnabled();
		update_option(
			'kec_signing_key',
			[
				'signing_key_id' => self::SIGNING_KEY_ID,
				'signing_key'    => self::SIGNING_KEY,
			]
		);
	}

	protected function tearDown(): void {
		delete_option( 'kec_signing_key' );
		OneStepCheckout::unset_sessions();

		parent::tearDown();
	}

	public function test_a_correctly_signed_request_is_accepted(): void {
		$body = '{"metadata":{}}';

		$this->assertTrue( ( new Notifications() )->validate_request( $this->notificationRequest( $body, self::SIGNING_KEY_ID, $this->sign( $body ) ) ) );
	}

	/**
	 * @dataProvider provide_rejected_requests
	 */
	public function test_a_rejected_notification_request_logs_why( ?string $key_id, ?string $signature, bool $key_stored, string $reason ): void {
		if ( ! $key_stored ) {
			delete_option( 'kec_signing_key' );
		}

		$accepted = ( new Notifications() )->validate_request( $this->notificationRequest( '{"metadata":{}}', $key_id, $signature ) );

		$this->assertFalse( $accepted );
		$this->assertSame( $reason, $this->loggedEntry( 'KEC: Notification request rejected' )['context']['reason'] );
		$this->assertStringNotContainsString( self::SIGNING_KEY, $this->loggedText(), 'The signing key signs every notification and must never be logged.' );
	}

	/** @return array<string, array{0: ?string, 1: ?string, 2: bool, 3: string}> */
	public function provide_rejected_requests(): array {
		return [
			'no signature header'         => [ self::SIGNING_KEY_ID, null, true, 'missing_headers' ],
			'no signing key stored'       => [ self::SIGNING_KEY_ID, 'abc', false, 'no_signing_key_stored' ],
			'signed with another key'     => [ 'another-key-id', 'abc', true, 'signing_key_id_mismatch' ],
			'signature does not match'    => [ self::SIGNING_KEY_ID, 'abc', true, 'signature_mismatch' ],
		];
	}

	public function test_a_notification_for_an_unknown_payment_request_is_logged_as_failed(): void {
		$body = wp_json_encode(
			[
				'metadata' => [
					'event_id'      => 'event-unknown-order',
					'event_type'    => 'payment.request.state-change.completed',
					'event_version' => 'v2',
				],
				'payload'  => [
					'payment_request_id' => 'krn:payment-request:unknown',
					'state'              => 'COMPLETED',
				],
			]
		);

		$response = ( new Notifications() )->handle_notification( $this->notificationRequest( $body ) );
		$entry    = $this->loggedEntry( 'KEC: Notification could not be handled, answered with 500 so Klarna retries' );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'krn:payment-request:unknown', $entry['reference']['payment_request_id'] );
		$this->assertStringContainsString( 'No draft order found', $entry['context']['error'] );
	}

	public function test_a_completed_payment_is_noted_on_the_order_and_logged_under_its_reference(): void {
		$order = $this->haveOrder(
			[
				'items'       => [ $this->haveSimpleProduct() ],
				'status'      => 'pending',
				'created_via' => 'klarna_express_checkout',
			]
		);
		$order->update_meta_data( '_kec_payment_request_id', 'krn:payment-request:completed' );
		$order->update_meta_data( '_kec_unique_id', 'kec_one_step_completed' );
		$order->save();

		add_filter( 'kec_acquiring_partner_integrations', '__return_empty_array', 100 );

		( new PaymentStateCompleted() )->handle_notification(
			[
				'payment_request_id' => 'krn:payment-request:completed',
				'state'              => 'COMPLETED',
			]
		);

		$this->assertOrderHasNote( $order, 'payment completed notification received' );
		$this->assertOrderHasNote( $order, 'no acquiring partner integration matches' );
		$this->assertSame(
			[
				'payment_request_id' => 'krn:payment-request:completed',
				'kec_unique_id'      => 'kec_one_step_completed',
				'order_id'           => (string) $order->get_id(),
			],
			$this->loggedEntry( 'KEC: Completed payment processed' )['reference'],
			'Every ID the purchase is known by stays readable after masking.'
		);
	}

	public function test_the_one_step_order_is_logged_against_its_payment_request(): void {
		WC()->session->set( 'kec_one_step_unique_id', 'kec_one_step_created' );
		$this->haveCartWith( [ [ $this->haveSimpleProduct(), 1 ] ] );

		$order = OneStepCheckout::create_order( 'krn:payment-request:created' );

		$this->assertSame(
			[
				'payment_request_id' => 'krn:payment-request:created',
				'kec_unique_id'      => 'kec_one_step_created',
				'order_id'           => (string) $order->get_id(),
			],
			$this->loggedEntry( 'KEC: One-step order created for the payment request' )['reference']
		);
	}

	private function notificationRequest( string $body, ?string $key_id = null, ?string $signature = null ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/klarna/v1/kec/notifications' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $body );

		if ( null !== $key_id ) {
			$request->set_header( 'Klarna-Signing-Key-Id', $key_id );
		}

		if ( null !== $signature ) {
			$request->set_header( 'Klarna-Signature', $signature );
		}

		return $request;
	}

	private function sign( string $body ): string {
		return hash_hmac( 'sha256', $body, self::SIGNING_KEY );
	}
}
