<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The Klarna order in the "Export order for support" file.
 *
 * @covers \Krokedil\Klarna\OrderManagement\OrderSupport
 */
class OrderSupportTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	public function test_the_export_includes_the_klarna_order_masked_like_the_logs(): void {
		$order = $this->haveKlarnaOrder( [ 'paid' => true ] );
		$this->willRetrieveKlarnaOrder( $this->klarnaOrderWithCustomer() );

		$klarna_order = $this->exportedKlarnaOrder( $order );

		$this->assertSame( 'klarna-order-123', $klarna_order['order_id'] );
		$this->assertSame( 'CAPTURED', $klarna_order['status'] );
		$this->assertSame( 'KP Test Product', $klarna_order['order_lines'][0]['name'] );
		$this->assertSame( '[REDACTED]', $klarna_order['billing_address']['given_name'] );
		$this->assertSame( '[REDACTED]', $klarna_order['billing_address']['email'] );
		$this->assertSame( '[REDACTED]', $klarna_order['billing_address']['street_address'] );
		$this->assertSame( 'Stockholm', $klarna_order['billing_address']['city'] );
		$this->assertSame( '11122', $klarna_order['billing_address']['postal_code'] );

		$encoded = (string) wp_json_encode( $klarna_order );
		$this->assertStringNotContainsString( 'Testperson', $encoded );
		$this->assertStringNotContainsString( 'customer@example.com', $encoded );
		$this->assertStringNotContainsString( '19770111-6050', $encoded );
	}

	public function test_masking_can_be_switched_off_with_the_log_masking_filter(): void {
		$order = $this->haveKlarnaOrder( [ 'paid' => true ] );
		$this->willRetrieveKlarnaOrder( $this->klarnaOrderWithCustomer() );
		add_filter( 'krokedil_wp_api_mask_log_data', '__return_false' );

		$klarna_order = $this->exportedKlarnaOrder( $order );

		$this->assertSame( 'Testperson-se', $klarna_order['billing_address']['given_name'] );
	}

	public function test_an_order_without_a_klarna_order_id_says_so_without_calling_klarna(): void {
		$order = $this->haveOrder( [ 'billing' => $this->swedishAddress() ] );

		$this->assertSame( [ 'error' => 'The order has no Klarna order ID.' ], $this->exportedKlarnaOrder( $order ) );
		$this->assertKlarnaRequestCount( 0 );
	}

	public function test_a_failed_lookup_says_why_without_the_request_args(): void {
		$order = $this->haveKlarnaOrder( [ 'paid' => true ] );
		$this->willRespondWith( [ 'error_code' => 'NO_SUCH_ORDER', 'error_messages' => [ 'Order not found.' ] ], 404, 'ordermanagement/v1/orders' );

		$klarna_order = $this->exportedKlarnaOrder( $order );

		$this->assertSame( [ 'error' => 'Could not retrieve the order from Klarna: Order not found.' ], $klarna_order );
	}

	/** @return array<string, mixed> */
	private function exportedKlarnaOrder( \WC_Order $order ): array {
		$export = KP_WC()->order_management->order_support->export_orders( $order );

		return $export['orders'][ $order->get_id() ]['integration_order'];
	}

	/** @return array<string, mixed> */
	private function klarnaOrderWithCustomer(): array {
		return [
			'status'          => 'CAPTURED',
			'order_lines'     => [ [ 'name' => 'KP Test Product', 'quantity' => 1, 'total_amount' => 12500 ] ],
			'billing_address' => [
				'given_name'     => 'Testperson-se',
				'family_name'    => 'Approved',
				'email'          => 'customer@example.com',
				'phone'          => '+46765260000',
				'street_address' => 'Stårgatan 1',
				'postal_code'    => '11122',
				'city'           => 'Stockholm',
				'country'        => 'SE',
			],
			'customer'        => [ 'national_identification_number' => '19770111-6050' ],
			'captures'        => [ [ 'capture_id' => 'capture-1', 'captured_amount' => 12500 ] ],
		];
	}
}
