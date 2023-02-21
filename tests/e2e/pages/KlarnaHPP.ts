//buy-button
import { Locator, Page } from '@playwright/test';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';

export class KlarnaHPP {
	readonly page: Page;

	readonly buyButton: Locator;

	readonly klarnaIframe: KlarnaPaymentsIframe;

	constructor(page: Page) {
		this.page = page;

		this.buyButton = page.locator('button#buy-button');

		this.klarnaIframe = new KlarnaPaymentsIframe(page);
	}

	async goto() {
		// There is no goto for Klarnas HPP. It is an automatic redirect from the checkout page.
	}

	async placeOrder() {
		// Wait for network idle.
		await this.page.waitForLoadState('networkidle');

		// Click the buy button.
		await this.buyButton.click();

		// Process the iframe.
		await this.klarnaIframe.fillNin();
		await this.klarnaIframe.clickConfirm();
	}
}
