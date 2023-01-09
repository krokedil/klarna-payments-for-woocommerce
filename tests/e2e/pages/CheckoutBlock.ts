import { expect, Locator, Page } from '@playwright/test';
import { Address } from '../utils/Types';

export class CheckoutBlock {
	readonly page: Page;

	// -- Payment start --
	readonly placeOrderButton: Locator;
	// -- Payment end --

	// -- Checkout form start --
	readonly checkoutForm: Locator;
	readonly toggleBillingAddress: Locator;

	// Billing field locators.
	readonly billingFirstName: Locator;
	readonly billingLastName: Locator;
	readonly billingCountry: Locator;
	readonly billingAddress1: Locator;
	readonly billingAddress2: Locator;
	readonly billingCity: Locator;
	readonly billingPostcode: Locator;
	readonly billingPhone: Locator;

	// Shipping field locators.
	readonly shippingFirstName: Locator;
	readonly shippingLastName: Locator;
	readonly shippingCountry: Locator;
	readonly shippingAddress1: Locator;
	readonly shippingAddress2: Locator;
	readonly shippingCity: Locator;
	readonly shippingPostcode: Locator;
	readonly shippingEmail: Locator;
	readonly shippingPhone: Locator;
	// -- Checkout form end --

	constructor(page: Page) {
		this.page = page;

		this.placeOrderButton = this.page.locator('button.wc-block-components-checkout-place-order-button');

		this.checkoutForm = page.locator('form.wc-block-components-form.wc-block-checkout__form');
		this.toggleBillingAddress = this.checkoutForm.locator('div.wc-block-checkout__use-address-for-billing input[type="checkbox"]');

		this.billingFirstName = this.checkoutForm.locator('#billing-first_name');
		this.billingLastName = this.checkoutForm.locator('#billing-last_name');
		this.billingCountry = this.checkoutForm.locator('#billing-country').getByLabel('Country/Region');
		this.billingAddress1 = this.checkoutForm.locator('#billing-address_1');
		this.billingAddress2 = this.checkoutForm.locator('#billing-address_2');
		this.billingCity = this.checkoutForm.locator('#billing-city');
		this.billingPostcode = this.checkoutForm.locator('#billing-postcode');
		this.billingPhone = this.checkoutForm.locator('#phone');

		this.shippingFirstName = this.checkoutForm.locator('#shipping-first_name');
		this.shippingLastName = this.checkoutForm.locator('#shipping-last_name');
		this.shippingCountry = this.checkoutForm.locator('#shipping-country').getByLabel('Country/Region');
		this.shippingAddress1 = this.checkoutForm.locator('#shipping-address_1');
		this.shippingAddress2 = this.checkoutForm.locator('#shipping-address_2');
		this.shippingCity = this.checkoutForm.locator('#shipping-city');
		this.shippingPostcode = this.checkoutForm.locator('#shipping-postcode');
		this.shippingEmail = this.checkoutForm.locator('#email');
		this.shippingPhone = this.checkoutForm.locator('#shipping-phone');
	}

	async goto() {
		await this.page.goto('/checkout-block');
	}

	async fillBillingAddress(billingAddress: Address = {}) {
		await this.toggleBillingAddress.uncheck();

		await this.billingFirstName.fill(billingAddress?.firstName ?? 'John');
		await this.billingLastName.fill(billingAddress?.lastName ?? 'Buck');
		await this.billingCountry.fill(billingAddress?.country ?? 'Sweden');
		await this.billingAddress1.fill(billingAddress?.address1 ?? 'Test street 1');
		await this.billingAddress2.fill(billingAddress?.address2 ?? '');
		await this.billingCity.fill(billingAddress?.city ?? 'Test city');
		await this.billingPostcode.fill(billingAddress?.postcode ?? '12345');
		await this.billingPhone.fill(billingAddress?.phone ?? '1234567890');
	}

	async fillShippingAddress(shippingAddress: Address = {}) {
		await this.shippingFirstName.fill(shippingAddress?.firstName ?? 'Jane');
		await this.shippingLastName.fill(shippingAddress?.lastName ?? 'Doe');
		await this.shippingCountry.fill(shippingAddress?.country ?? 'Sweden');
		await this.shippingAddress1.fill(shippingAddress?.address1 ?? 'Test street 2');
		await this.shippingAddress2.fill(shippingAddress?.address2 ?? '');
		await this.shippingCity.fill(shippingAddress?.city ?? 'Test city 2');
		await this.shippingPostcode.fill(shippingAddress?.postcode ?? '54321');
		await this.shippingEmail.fill(shippingAddress?.email ?? 'test@krokedil.se');
	}

	async placeOrder() {
		await this.placeOrderButton.click();
	}

	async hasPaymentMethodId(paymentMethodId: string) {
		// Expect to find a radio input that contains the payment method id in the element id.
		await expect(this.page.locator(`input[id*="${paymentMethodId}"]`)).not.toBeUndefined;
	}
}
