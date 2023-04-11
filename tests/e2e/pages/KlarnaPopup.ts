import { Locator, Page, expect } from '@playwright/test';

export class KlarnaPopup {
    readonly page: Page;

    readonly loginSelectorDiv: Locator;
    readonly dialogDiv: Locator;

    readonly continueWithBankIdButton: Locator;
    readonly confirmAndPayButton: Locator;
    readonly skipSmoothCheckoutButton: Locator;

    constructor(page: Page) {
        this.page = page;

        this.loginSelectorDiv = page.locator('#loginSelectionView');
        this.dialogDiv = page.locator('#dialog');

        this.continueWithBankIdButton = page.getByTestId('kaf-button');
        this.confirmAndPayButton = page.getByTestId('confirm-and-pay');
        this.skipSmoothCheckoutButton = page.getByTestId('SmoothCheckoutPopUp:skip');
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
        await this.skipSmoothCheckoutButton.click();
    }

    async placeOrder() {
        await this.continueWithBankId();
        await this.confirmAndPay();
        await this.skipSmoothCheckout();
    }
}
