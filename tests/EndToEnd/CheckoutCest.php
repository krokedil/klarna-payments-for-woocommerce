<?php

namespace Tests\EndToEnd;

use Tests\Support\Data\{TestProducts, TestTaxRates};
use Tests\Support\EndToEndTester;

class CheckoutCest
{
	public function can_purchase_simple_product_inc_vat(EndToEndTester $I): void
	{
		$I->haveInDatabase('wp_options', [
			'option_name' => 'woocommerce_prices_include_tax',
			'option_value' => 'yes',
		]);

		$I->haveTaxClassInDatabase(TestTaxRates::TAX_RATE_25);
		$product_id = $I->haveProductInDatabase(TestProducts::SIMPLE_25);

		$I->amOnPage("/checkout/?add-to-cart=$product_id");
		$I->waitForElement('#payment_method_klarna_payments_pay_later', 15);

		$I->fillBillingAddressForm();
		$I->click('#place_order');

		$I->processKlarnaIframe('Pay in 30 days');

		$I->waitForText("Order received", 20);

		$I->verifyOrderOnThankYouPage('klarna_payments', '99.99');
	}

	public function can_purchase_simple_product_ex_vat(EndToEndTester $I): void
	{
		$I->haveInDatabase('wp_options', [
			'option_name' => 'woocommerce_prices_include_tax',
			'option_value' => 'no',
		]);

		$I->haveTaxClassInDatabase(TestTaxRates::TAX_RATE_25);
		$product_id = $I->haveProductInDatabase(TestProducts::SIMPLE_25);

		$I->amOnPage("/checkout/?add-to-cart=$product_id");
		$I->waitForElement('#payment_method_klarna_payments_pay_later', 15);

		$I->fillBillingAddressForm();
		$I->click('#place_order');

		$I->processKlarnaIframe('Pay in 30 days');

		$I->waitForText("Order received", 20);

		$I->verifyOrderOnThankYouPage('klarna_payments', '124.99');
	}
}
