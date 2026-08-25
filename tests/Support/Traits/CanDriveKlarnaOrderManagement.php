<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Canned Klarna order management responses for the Integration suite. */
trait CanDriveKlarnaOrderManagement {

	/** Queues the response to the Klarna order lookup every OM path starts with. */
	protected function willRetrieveKlarnaOrder( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'order_id'                    => 'klarna-order-123',
					'status'                      => 'AUTHORIZED',
					'fraud_status'                => 'ACCEPTED',
					'remaining_authorized_amount' => 25000,
					'purchase_currency'           => 'SEK',
				],
				$overrides
			),
			200,
			'ordermanagement/v1/orders'
		);
	}

	/**
	 * Queues a successful capture. Klarna answers with an empty body and the
	 * capture id in a `capture-id` header.
	 */
	protected function willCapture( string $capture_id = 'capture-123' ): void {
		$this->willRespondWith( [], 201, '/captures', [ 'capture-id' => $capture_id ] );
	}

	/** Queues a successful cancellation. */
	protected function willCancel(): void {
		$this->willRespondWith( [], 204, '/cancel' );
	}

	/** Queues a successful order line update. */
	protected function willAcceptTheUpdate(): void {
		$this->willRespondWith( [], 204, '/authorization' );
	}

	/** Queues a rejected request, in the shape Klarna reports errors. */
	protected function willRejectWith( string $url_contains, string $message, int $status = 400, array $body = [] ): void {
		$this->willRespondWith(
			array_merge( [ 'error_messages' => [ $message ] ], $body ),
			$status,
			$url_contains
		);
	}
}
