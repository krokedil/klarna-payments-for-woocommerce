import { Locator, Page } from '@playwright/test';
import { Address } from '../utils/Types';

export class Checkout {
	readonly page: Page;

	// -- Coupon form starts --
	readonly couponForm: Locator;
	readonly couponToggle: Locator;
	readonly couponCode: Locator;
	readonly applyCouponButton: Locator;
	// -- Coupon form end --

	// -- Order review start --
	readonly orderReview: Locator;
	readonly shippingMethodRadio: Locator;
	// -- Order review end --

	// -- Payment start --
	readonly payment: Locator;
	readonly paymentMethodRadio: Locator;
	readonly termsCheckbox: Locator;
	readonly placeOrderButton: Locator;
	// -- Payment end --

	// -- Checkout form start --
	readonly checkoutForm: Locator;
	readonly toggleShippingAddress: Locator;

	// Billing field locators.
	readonly billingFirstName: Locator;
	readonly billingLastName: Locator;
	readonly billingCompany: Locator;
	readonly billingCountry: Locator;
	readonly billingAddress1: Locator;
	readonly billingAddress2: Locator;
	readonly billingCity: Locator;
	readonly billingState: Locator;
	readonly billingPostcode: Locator;
	readonly billingPhone: Locator;
	readonly billingEmail: Locator;

	// Shipping field locators.
	readonly shippingFirstName: Locator;
	readonly shippingLastName: Locator;
	readonly shippingCompany: Locator;
	readonly shippingCountry: Locator;
	readonly shippingAddress1: Locator;
	readonly shippingAddress2: Locator;
	readonly shippingCity: Locator;
	readonly shippingState: Locator;
	readonly shippingPostcode: Locator;
	// -- Checkout form end --

	constructor(page: Page) {
		this.page = page;

		this.couponForm = page.locator('form[name="checkout_coupon"]');
		this.couponToggle = this.couponForm.locator('a.showcoupon');
		this.couponCode = this.couponForm.locator('#coupon_code');
		this.applyCouponButton = this.couponForm.locator('[name="apply_coupon"]');

		this.orderReview = page.locator('#order_review');
		this.shippingMethodRadio = this.orderReview.locator('input[name="shipping_method[0]"]');

		this.payment = page.locator('#payment');
		this.paymentMethodRadio = this.payment.locator('input[name="payment_method"]');
		this.termsCheckbox = this.payment.locator('#terms');
		this.placeOrderButton = this.payment.locator('#place_order');

		this.checkoutForm = page.locator('form[name="checkout"]');
		this.toggleShippingAddress = this.checkoutForm.locator('#ship-to-different-address-checkbox');

		this.billingFirstName = this.checkoutForm.locator('#billing_first_name');
		this.billingLastName = this.checkoutForm.locator('#billing_last_name');
		this.billingCompany = this.checkoutForm.locator('#billing_company');
		this.billingCountry = this.checkoutForm.locator('#billing_country');
		this.billingAddress1 = this.checkoutForm.locator('#billing_address_1');
		this.billingAddress2 = this.checkoutForm.locator('#billing_address_2');
		this.billingCity = this.checkoutForm.locator('#billing_city');
		this.billingState = this.checkoutForm.locator('#billing_state');
		this.billingPostcode = this.checkoutForm.locator('#billing_postcode');
		this.billingPhone = this.checkoutForm.locator('#billing_phone');
		this.billingEmail = this.checkoutForm.locator('#billing_email');

		this.shippingFirstName = this.checkoutForm.locator('#shipping_first_name');
		this.shippingLastName = this.checkoutForm.locator('#shipping_last_name');
		this.shippingCompany = this.checkoutForm.locator('#shipping_company');
		this.shippingCountry = this.checkoutForm.locator('#shipping_country');
		this.shippingAddress1 = this.checkoutForm.locator('#shipping_address_1');
		this.shippingAddress2 = this.checkoutForm.locator('#shipping_address_2');
		this.shippingCity = this.checkoutForm.locator('#shipping_city');
		this.shippingState = this.checkoutForm.locator('#shipping_state');
		this.shippingPostcode = this.checkoutForm.locator('#shipping_postcode');

	}

	async goto() {
		await this.page.goto('/checkout');
	}

	async applyCoupon(coupon: string) {
		await this.couponToggle.click();
		await this.couponCode.fill(coupon);
		await this.applyCouponButton.click()
	}

	async fillBillingAddress(billingAddress: Address = {}) {
		await this.billingFirstName.fill(billingAddress?.firstName ?? 'John');
		await this.billingLastName.fill(billingAddress?.lastName ?? 'Buck');
		await this.billingCompany.fill(billingAddress?.company ?? '');
		await this.billingCountry.selectOption(billingAddress?.country ?? 'SE');
		await this.billingAddress1.fill(billingAddress?.address1 ?? 'Test street 1');
		await this.billingAddress2.fill(billingAddress?.address2 ?? '');
		await this.billingCity.fill(billingAddress?.city ?? 'Test city');
		// Only fill billing state if it exists.
		if (await this.billingState.isVisible()) {
			await this.billingState.selectOption(billingAddress?.state ?? '');
		}
		await this.billingPostcode.fill(billingAddress?.postcode ?? '12345');
		await this.billingPhone.fill(billingAddress?.phone ?? '1234567890');
		await this.billingEmail.fill(billingAddress?.email ?? 'test@krokedil.se');
		await this.page.waitForResponse((response) => response.url().includes('update_order_review'));
	}

	async fillShippingAddress(shippingAddress: Address = {}) {
		await this.toggleShippingAddress.check();
		await this.shippingFirstName.fill(shippingAddress?.firstName ?? 'Jane');
		await this.shippingLastName.fill(shippingAddress?.lastName ?? 'Doe');
		await this.shippingCompany.fill(shippingAddress?.company ?? '');
		await this.shippingCountry.selectOption(shippingAddress?.country ?? 'SE');
		await this.shippingAddress1.fill(shippingAddress?.address1 ?? 'Test street 2');
		await this.shippingAddress2.fill(shippingAddress?.address2 ?? '');
		await this.shippingCity.fill(shippingAddress?.city ?? 'Test city 2');
		// Only fill shipping state if it exists.
		if (await this.shippingState.isVisible()) {
			await this.shippingState.selectOption(shippingAddress?.state ?? '');
		}
		await this.shippingPostcode.fill(shippingAddress?.postcode ?? '54321');
	}

	async selectShippingMethod(shippingMethod: string) {
		await this.shippingMethodRadio.getByLabel(shippingMethod).check();
	}

	async selectPaymentMethod(paymentMethod: string) {
		await this.paymentMethodRadio.getByLabel(paymentMethod).check();
	}

	async acceptTerms() {
		await this.termsCheckbox.check();
	}

	async placeOrder() {
		await this.placeOrderButton.click();
	}
}
