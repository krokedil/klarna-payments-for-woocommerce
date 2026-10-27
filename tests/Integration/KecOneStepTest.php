<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\ExpressCheckout\OneStepCheckout;
use Tests\Support\IntegrationTestCase;

/**
 * Which order a one-step Klarna Express Checkout builds its cart into.
 *
 * @covers \Krokedil\Klarna\ExpressCheckout\OneStepCheckout::get_wc_order
 */
class KecOneStepTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * @dataProvider provide_session_orders
	 */
	public function test_a_completed_order_is_not_reused_for_the_next_purchase( array $args, bool $reused ): void {
		$order = $this->haveOrder( array_merge( [ 'items' => [ $this->haveSimpleProduct() ] ], $args ) );
		WC()->session->set( 'kec_one_step_order_id', $order->get_id() );

		$picked = \Closure::bind( static fn() => OneStepCheckout::get_wc_order(), null, OneStepCheckout::class )();

		$this->assertSame( $reused, $order->get_id() === $picked->get_id() );
	}

	/** @return array<string, array{0: array, 1: bool}> */
	public function provide_session_orders(): array {
		return [
			'pending, unpaid'          => [ [ 'status' => 'pending' ], true ],
			'processing, paid'         => [ [ 'status' => 'processing', 'paid' => true ], false ],
			'on-hold, awaiting review' => [ [ 'status' => 'on-hold' ], false ],
		];
	}
}
