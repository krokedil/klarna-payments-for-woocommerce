import { test, expect, APIRequestContext } from '@playwright/test';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';
import { Cart } from '../pages/Cart';
import { Checkout } from '../pages/Checkout';
import { OrderRecieved } from '../pages/OrderRecieved';
import { GetWcApiClient } from '../utils/Utils';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';

test.describe('Customer Checkout @shortcode', () => {

	test.use({ storageState: process.env.GUESTSTATE });

	const paymentMethodId = 'klarna_payments';

	let customerId;
	let username;
	let orderId;

	test.beforeEach(async ({ page }) => {
		const apiClient = await GetWcApiClient();

		let randSuffix = Math.floor(Math.random() * 1000000);
		username = `testCustomer_${randSuffix}`;

		// Create a customer account.
		const customerResponse = await apiClient.post('customers', {
			data: {
				email: `testCustomer_${username}@krokedil.se`,
				first_name: 'Test',
				last_name: 'Customer',
				username: username,
				password: 'password',
				billing: {
					first_name: 'Test',
					last_name: 'Customer',
					address_1: 'Teststreet 1',
					address_2: '',
					city: 'TestCity',
					postcode: '12345',
					country: 'SE',
					email: 'testCustomer@krokedil.se',
					phone: '123456789',
				},
				shipping: {
					first_name: 'Test',
					last_name: 'Customer',
					address_1: 'Teststreet 1',
					address_2: '',
					city: 'TestCity',
					state: '',
					postcode: '12345',
					country: 'SE',
				}
			},
		});

		const customer = await customerResponse.json();
		customerId = customer.id;

		// Login as the customer.
		await page.goto('/my-account/');
		await page.fill('#username', username);
		await page.fill('#password', 'password');

		await Promise.all([
			page.waitForNavigation(),
			page.click('text=Log in'),
		]);
	});

	test.afterEach(async ({ page }) => {
		// Delete the customer account.
		const apiClient = await GetWcApiClient();
		await apiClient.delete(`customers/${customerId}`);

		// Delete the order.
		await apiClient.delete(`orders/${orderId}`);

		// Clear all cookies.
		await page.context().clearCookies();
	});

	test('Customer can checkout with Klarna Payments with prefilled address', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Place the order.
		await checkoutPage.placeOrder();

		// Fill in the NIN.
		await iframe.fillNin();

		// Confirm the order.
		await iframe.clickConfirm();

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});
});
