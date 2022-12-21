import { FrameLocator, Locator, Page } from '@playwright/test';

export class KlarnaPaymentsIframe {
	readonly page: Page;

	readonly iframe: FrameLocator;
	readonly ninInput: Locator;
	readonly confirmButton: Locator;

	constructor(page: Page) {
		this.page = page;

		this.iframe = page.frameLocator('iframe[id*="fullscreen"][title="Klarna"]');
		this.ninInput = this.iframe.locator('input[name="nationalIdentificationNumber"]');
		this.confirmButton = this.iframe.locator('button[id*="approval-form-continue-button"]');
	}

	async fillNin(nin: string = '410321-9202') {
		await this.ninInput.fill(nin);
	}

	async clickConfirm() {
		await this.confirmButton.click();
	}
}
