import { test, expect, APIRequestContext } from '@playwright/test';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';
import { Cart } from '../pages/Cart';
import { Checkout } from '../pages/Checkout';

test.describe('Guest Checkout', () => {
	test.use({ storageState: process.env.GUESTSTATE });

	let orderId;

	test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

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

	test('Can buy products with different tax rates', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-12', 'simple-06', 'simple-00']);

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

	test('Can buy products that dont require shipping', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-virtual-downloadable-25', 'simple-virtual-downloadable-12', 'simple-virtual-downloadable-06', 'simple-virtual-downloadable-00']);

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

	test('Can buy variable products', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['variable-25-blue', 'variable-12-red', 'variable-12-red', 'variable-25-black', 'variable-12-black']);

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

	test('Can place order with separate shipping address', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		const checkoutPage = new Checkout(page);

		// Go to the checkout page.
		await checkoutPage.goto();

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Fill in the shipping address.
		await checkoutPage.fillShippingAddress();

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

	test('Can place order with Company name in both billing and shipping address', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		const checkoutPage = new Checkout(page);

		// Go to the checkout page.
		await checkoutPage.goto();

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress({ company: 'Test Company Billing' });

		// Fill in the shipping address.
		await checkoutPage.fillShippingAddress({ company: 'Test Company Shipping' });

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

	test('Can change shipping method', async ({ page }) => {
		const cartPage = new Cart(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		const checkoutPage = new Checkout(page);

		// Go to the checkout page.
		await checkoutPage.goto();

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Change the shipping method.
		await checkoutPage.selectShippingMethod('Flat rate');

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
