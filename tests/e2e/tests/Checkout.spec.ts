import { test, expect, APIRequestContext } from '@playwright/test';
import { GetApiClient } from '../utils/Utils';


test.describe('Checkout', () => {
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

	test('Should see KP on checkout page.', async ({ page }) => {
		await page.goto('/checkout');
		await expect(page.locator('#payment_method_klarna_payments_pay_later')).toBeVisible();
	});
});
