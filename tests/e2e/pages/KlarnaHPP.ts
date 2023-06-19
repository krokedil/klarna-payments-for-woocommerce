import { FrameLocator, Locator, Page, expect } from '@playwright/test';

export class KlarnaHPP {
	readonly page: Page;

	readonly loginSelectorDiv: Locator;
	readonly dialogDiv: Locator;

	readonly continueWithBankIdButton: Locator;
	readonly confirmAndPayButton: Locator;
	readonly skipSmoothCheckoutButton: Locator;
	readonly iframe: FrameLocator;

	constructor(page: Page) {
		this.page = page;

		this.iframe = page.frameLocator('#klarna-apf-iframe');

		this.loginSelectorDiv = this.iframe.locator('#loginSelectionView');
		this.dialogDiv = this.iframe.locator('#dialog');

		this.continueWithBankIdButton = this.iframe.getByTestId('kaf-button');
		this.confirmAndPayButton = this.iframe.getByTestId('confirm-and-pay');

		this.skipSmoothCheckoutButton = this.iframe.getByTestId('SmoothCheckoutPopUp:skip');
	}

	async fillNin(nin: string = '410321-9202') {

	}

	async continueWithBankId() {
		await this.continueWithBankIdButton.click();

		// Wait for the loginSelectionView to no longer be on the page.
		await expect(this.loginSelectorDiv).toHaveCount(0)

		// Wait for the dialog to also be gone.
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
