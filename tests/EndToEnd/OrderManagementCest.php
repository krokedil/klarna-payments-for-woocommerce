<?php

namespace Tests\EndToEnd;

use Tests\Support\Data\{TestProducts, TestTaxRates};
use Tests\Support\EndToEndTester;

/** Klarna's own view of an order as WooCommerce updates, captures, refunds and cancels it. */
class OrderManagementCest
{
	/**
	 * The notes a refused operation leaves, checked at the end of each lifecycle. Named
	 * rather than matched on "failed", which also catches the mail WooCommerce cannot send
	 * from the test environment.
	 */
	private const FAILURE_NOTES = [
		'Could not capture Klarna',
		'Could not cancel Klarna',
		'Could not refund Klarna',
		'Could not update Klarna',
		'Klarna order could not',
		'Klarna order failed',
		'was not sent because',
	];

	public function can_update_capture_and_refund_an_order(EndToEndTester $I): void
	{
		$orderId = $this->purchase($I, 2);

		$I->amEditingKlarnaOrder($orderId);
		$I->seeKlarnaOrderStatusIs('AUTHORIZED');

		// Item edits need an editable status, and on-hold has no Klarna hook of its own.
		$I->changeOrderStatusTo('on-hold');
		$I->reduceLineItemQuantityTo(1);

		$I->seeOrderNotes($orderId, [ 'Klarna order updated.' ]);
		$I->seeOrderMeta($orderId, [ '_order_total' => '99.99' ]);

		// Re-read, so the status below is Klarna's answer after the update rather than before it.
		$I->amEditingKlarnaOrder($orderId);
		$I->seeKlarnaOrderStatusIs('AUTHORIZED');

		$I->changeOrderStatusTo('completed');
		$I->seeOrderNotes($orderId, [ 'Klarna order captured' ]);
		$I->seeOrderMeta($orderId, [ '_wc_klarna_capture_id' => null ]);
		$I->seeKlarnaOrderStatusIs('CAPTURED');

		// The refund surviving is itself the assertion: wc_create_refund() deletes it again
		// when the gateway answers with a WP_Error, so Klarna took the body.
		$I->refundOrderViaKlarna('10.00');
		$I->seeRefundCount($orderId, 1);
		$I->seeOrderMeta($I->grabLatestRefundId($orderId), [ '_refund_amount' => '10.00' ]);
		$I->seeOrderNotes($orderId, [ 'Processing a refund of' ]);

		$I->dontSeeOrderNotes($orderId, self::FAILURE_NOTES);
	}

	public function can_cancel_an_order_from_the_metabox(EndToEndTester $I): void
	{
		$I->haveKlarnaSettingsInDatabase([ 'kom_auto_cancel' => 'no' ]);

		$orderId = $this->purchase($I);

		$I->amEditingKlarnaOrder($orderId);
		$I->seeKlarnaOrderStatusIs('AUTHORIZED');

		// With the automatic cancellation off, the status change alone must not reach Klarna.
		$I->changeOrderStatusTo('cancelled');
		$I->dontSeeOrderNotes($orderId, [ 'Klarna order cancelled' ]);
		$I->dontSeeOrderMeta($orderId, [ '_wc_klarna_cancelled' ]);
		$I->seeKlarnaOrderStatusIs('AUTHORIZED');

		$I->applyKlarnaOrderAction('kom_cancel');
		$I->seeOrderNotes($orderId, [ 'Klarna order cancelled.' ]);
		$I->seeOrderMeta($orderId, [ '_wc_klarna_cancelled' => 'yes' ]);
		$I->seeKlarnaOrderStatusIs('CANCELLED');

		$I->dontSeeOrderNotes($orderId, self::FAILURE_NOTES);
	}

	/**
	 * Buys one 25% VAT product as a Swedish customer, which is the only billing country
	 * the return fee below is offered in, and returns the finished order's id.
	 */
	private function purchase(EndToEndTester $I, int $quantity = 1): int
	{
		$I->haveStoreOptionsInDatabase([ 'woocommerce_prices_include_tax' => 'yes' ]);
		$I->haveTaxClassesInDatabase([ TestTaxRates::TAX_RATE_25 ]);
		$I->haveCartWith([ [ TestProducts::SIMPLE_25, $quantity ] ]);

		$I->amOnCheckoutPageWithKlarna();
		$I->fillBillingAddressForm();
		$I->placeKlarnaOrder();

		return $I->grabOrderIdFromThankYouPage();
	}
}
