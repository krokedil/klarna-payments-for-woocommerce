import { expect, FrameLocator, Locator, Page } from '@playwright/test';

export class KlarnaPaymentsIframe {
	readonly page: Page;

	readonly iframe: FrameLocator;
	readonly ninInput: Locator;
	readonly confirmButton: Locator;

	readonly title: Locator;

	constructor(page: Page) {
		this.page = page;

		this.iframe = page.frameLocator('iframe[id*="fullscreen"][title="Klarna"]');
		this.ninInput = this.iframe.locator('input[name="nationalIdentificationNumber"]');
		this.confirmButton = this.iframe.locator('button[id*="approval-form-continue-button"]');

		this.title = this.iframe.locator('h1[role="heading"]');
	}

	async fillNin(nin: string = '410321-9202') {
		await this.ninInput.isVisible();
		await this.ninInput.click();
		await this.ninInput.type(nin);
	}

	async clickConfirm() {
		await this.confirmButton.click();
	}

	async getTitle(): Promise<string> {
		const title = await this.title.innerText();
		return title;
	}

	async hasError(errorMessage?: string): Promise<void> {
		const title = await this.getTitle();

		if (errorMessage) {
			expect(title).toContain(errorMessage);
		}
		else {
			expect(title).toContain('error');
		}
	}
}
