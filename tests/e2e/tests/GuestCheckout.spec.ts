import { test, expect, APIRequestContext } from '@playwright/test';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';
import { Cart } from '../pages/Cart';
import { Checkout } from '../pages/Checkout';
import { OrderRecieved } from '../pages/OrderRecieved';
import { GetWcApiClient } from '../utils/Utils';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';


test.describe('Guest Checkout @shortcode', () => {
	test.use({ storageState: process.env.GUESTSTATE });

	const paymentMethodId = 'klarna_payments';

	let orderId: string;

	test.afterEach(async () => {
		// Delete the order from WooCommerce.
		const wcApiClient = await GetWcApiClient();
		await wcApiClient.delete(`orders/${orderId}`);
	});

	test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

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

	test('Can buy products with different tax rates', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-12', 'simple-06', 'simple-00']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

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

	test('Can buy products that don\'t require shipping', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-virtual-downloadable-25', 'simple-virtual-downloadable-12', 'simple-virtual-downloadable-06', 'simple-virtual-downloadable-00']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

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

	test('Can buy variable products', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['variable-25-blue', 'variable-12-red', 'variable-12-red', 'variable-25-black', 'variable-12-black']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

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

	test('Can place order with separate shipping address', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Fill in the shipping address.
		await checkoutPage.fillShippingAddress();

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

	test('Can place order with Company name in both billing and shipping address', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress({ company: 'Test Company Billing' });

		// Fill in the shipping address.
		await checkoutPage.fillShippingAddress({ company: 'Test Company Shipping' });

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

	test('Can change shipping method', async ({ page }) => {
		const cartPage = new Cart(page);
		const orderRecievedPage = new OrderRecieved(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress();

		// Change the shipping method.
		await checkoutPage.selectShippingMethod('Flat rate');

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

	test('Can handle special characters in address field', async ({ page }) => {
		const cartPage = new Cart(page);
		const checkoutPage = new Checkout(page);
		const iframe = new KlarnaPaymentsIframe(page)

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		await checkoutPage.hasPaymentMethodId(paymentMethodId);

		// Fill in the billing address.
		await checkoutPage.fillBillingAddress(
			{
				firstName: 'Test ÅÄÖ',
				lastName: 'Test @£$€{]}{[}~^`´',
			}
		);

		// Place the order.
		await checkoutPage.placeOrder();

		await iframe.hasError('There has been an error with your address');
	});
});
