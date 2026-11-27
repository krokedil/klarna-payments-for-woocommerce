<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The KOM metabox actions dropdown.
 *
 * @covers \Krokedil\Klarna\OrderManagement\MetaBox
 */
class MetaBoxTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * The dropdown should only offer an action the handler would carry out.
	 *
	 * @dataProvider provide_order_states
	 */
	public function test_the_dropdown_only_offers_an_action_the_order_can_take( string $op, string $state, bool $expected ): void {
		$order = $this->haveCapturableKlarnaOrder();

		if ( 'disconnected' === $state ) {
			$order->update_meta_data( '_kom_disconnect', 'yes' );
		} elseif ( 'unpaid' === $state ) {
			$order->set_date_paid( null );
		}
		$order->save();

		$klarna_order = (object) [ 'status' => 'AUTHORIZED', 'fraud_status' => 'ACCEPTED' ];
		$actions      = [ 'capture' => true, 'cancel' => true, 'sync' => true, 'any' => true ];
		$method       = "want_output_{$op}";

		$this->assertSame( $expected, KP_WC()->order_management->metabox->$method( $order->get_id(), $klarna_order, $actions ) );
	}

	/** @return array<string, array{0: string, 1: string, 2: bool}> */
	public function provide_order_states(): array {
		return [
			'capture, eligible'     => [ 'capture', 'eligible', true ],
			'capture, disconnected' => [ 'capture', 'disconnected', false ],
			'capture, unpaid'       => [ 'capture', 'unpaid', false ],
			'cancel, eligible'      => [ 'cancel', 'eligible', true ],
			'cancel, disconnected'  => [ 'cancel', 'disconnected', false ],
			'cancel, unpaid'        => [ 'cancel', 'unpaid', false ],
		];
	}
}
