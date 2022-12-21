import { test, expect, APIRequestContext } from '@playwright/test';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';
import { Checkout } from '../pages/Checkout';
import { GetApiClient } from '../utils/Utils';

test.describe.serial('Guest Checkout', () => {
	test.use({ storageState: process.env.GUESTSTATE });

	let apiClient: APIRequestContext;
	let productId: number;

	test.beforeAll(async () => {
		apiClient = await GetApiClient();

		// Create a product to use for the tests.
		const productResponse = await apiClient.post('products', {
			data: {
				name: "Partial Delivery Product Admin Single Order Page",
				type: "simple",
				regular_price: "9.99",
				stock_quantity: 10,
				manage_stock: true,
				backorders: 'notify'
			}
		});
		const productJson = await productResponse.json();
		productId = productJson.id;
	});

	test.beforeEach(async ({ page }) => {
		// Add the product to the cart.
		await page.goto(`/cart/?add-to-cart=${productId}`);
	});

	test.afterEach(async ({ page }) => {
		// Delete all cookies to ensure that we have a fresh session.
		await page.context().clearCookies();
	});

	test.afterAll(async () => {
		// Delete the product created for the tests.
		await apiClient.delete(`products/${productId}`, { data: { force: true } });
	});

	test('Can place a Klarna payments order', async ({ page }) => {
		const checkoutPage = new Checkout(page);

		// Go to the checkout page.
		await checkoutPage.goto();

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Place the order.
		await checkoutPage.placeOrder();

		const iframe = new KlarnaPaymentsIframe(page)

		// Fill in the NIN.
		await iframe.fillNin();

		// Confirm the order.
		await iframe.clickConfirm();

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);
	});
});
