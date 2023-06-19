import { FrameLocator, Locator, Page, expect } from '@playwright/test';

export class KlarnaHPP {
	readonly page: Page;

	readonly dialogDiv: Locator;

	readonly paymentMethodRadio: Locator;
	readonly paymentMethodButton: Locator;

	readonly continueWithBankIdButton: Locator;
	readonly confirmAndPayButton: Locator;
	readonly skipSmoothCheckoutButton: Locator;
	readonly iframe: FrameLocator;

	constructor(page: Page) {
		this.page = page;

		this.iframe = page.frameLocator('#klarna-apf-iframe');
		this.dialogDiv = page.locator('#dialog');

		this.continueWithBankIdButton = this.iframe.getByTestId('kaf-button');
		this.confirmAndPayButton = this.iframe.getByTestId('confirm-and-pay');

		this.paymentMethodRadio = this.iframe.locator('input[type="radio"]');
		this.paymentMethodButton = this.iframe.getByTestId('select-payment-category');

		this.skipSmoothCheckoutButton = this.iframe.getByTestId('SmoothCheckoutPopUp:skip');
	}

	async fillNin(nin: string = '410321-9202') {

	}

	async continueWithBankId() {
		await this.continueWithBankIdButton.click();

		// Wait for 200 response from call to /profile/seNoLogin
		await this.page.waitForResponse(response => response.url().includes('/profile/seNoLogin') && response.status() === 200);

		// Wait for 200 response from /payment_methods call
		const paymentMethodResponse = await this.page.waitForResponse(response => response.url().includes('/payment_methods') && response.status() === 200);

		// Parse the response and check if a payment method is already selected.
		const body = await paymentMethodResponse.json();
		if (!body.payment_categories.some(category => category.selected)) {
			// Select the first payment method.
			await this.paymentMethodRadio.first().click();
			await this.paymentMethodButton.click();
		}

		await expect(this.dialogDiv).toHaveCount(0)
	}

	async confirmAndPay() {
		await this.confirmAndPayButton.click();
	}

	async skipSmoothCheckout() {
		// Wait and see if the skipSmoothCheckoutButton appears, if it does click it, else just continue.
		if (await this.skipSmoothCheckoutButton.isVisible()) {
			await this.skipSmoothCheckoutButton.click();
		}

	}

	async placeOrder() {
		await this.continueWithBankId();
		await this.confirmAndPay();
		await this.skipSmoothCheckout();
	}
}
