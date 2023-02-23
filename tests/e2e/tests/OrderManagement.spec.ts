import { AdminLogin, GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { test, expect, APIRequestContext } from '@playwright/test';
import { gt, valid } from 'semver';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';
import { KlarnaHPP } from '../pages/KlarnaHPP';

const {
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

test.describe('Order management @shortcode', () => {
	let wcApiClient: APIRequestContext;

	let orderId;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async () => {
		// Delete the order from WooCommerce.
		await wcApiClient.delete(`orders/${orderId}`);
	});

	test('Can capture an order', async ({ browser }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			const iframe = new KlarnaPaymentsIframe(page)
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			await iframe.fillNin();
			await iframe.clickConfirm();

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
			page.close();
		});

		await test.step('Capture the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();
			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order captured')).toBe(true);
			page.close();
		});
	});

	test('Can cancel an order', async ({ browser }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			const iframe = new KlarnaPaymentsIframe(page)
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			await iframe.fillNin();
			await iframe.clickConfirm();

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
			page.close();
		});

		await test.step('Cancel the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.cancelOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order cancelled')).toBe(true);
			page.close();
		});
	});

	test('Can refund an order', async ({ browser }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			const iframe = new KlarnaPaymentsIframe(page)
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			await iframe.fillNin();
			await iframe.clickConfirm();

			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
			page.close();
		});

		await test.step('Fully refund the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
			page.close();
		});
	});

	test('Can partially refund an order', async ({ browser }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			const iframe = new KlarnaPaymentsIframe(page)
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			await iframe.fillNin();
			await iframe.clickConfirm();

			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
			page.close();
		});

		await test.step('Partially refund the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
			page.close();
		});
	});
});

test.describe('Order management @checkoutBlock', () => {
	test.skip(
		valid(process.env.WC_VERSION) && // And it is not an empty string
		!gt(process.env.WC_VERSION, '6.0.0'), // And
		'Skipping tests with checkout blocks for WooCommerce < 6.0.0');

	let wcApiClient: APIRequestContext;

	let orderId;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async () => {
		// Delete the order from WooCommerce.
		await wcApiClient.delete(`orders/${orderId}`);
	});

	test('Can capture an order', async ({ browser }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaHPP(page);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);
			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
			page.close();
		});

		await test.step('Capture the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order captured')).toBe(true);
			page.close();
		});
	});

	test('Can cancel an order', async ({ browser }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaHPP(page);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);

			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
			page.close();
		});

		await test.step('Cancel the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.cancelOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order cancelled')).toBe(true);
			page.close();
		});
	});

	test('Can refund an order', async ({ browser }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaHPP(page);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);
			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
			page.close();
		});

		await test.step('Fully refund the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
			page.close();
		});
	});

	test('Can partially refund an order', async ({ browser }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const context = await browser.newContext({ storageState: process.env.GUESTSTATE });
			const page = await context.newPage();
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.CheckoutBlock(page);
			const klarnaHPP = new KlarnaHPP(page);

			// Add products to the cart.
			await cartPage.addtoCart(['simple-25']);

			// Go to the checkout page.
			await checkoutPage.goto();

			// Fill in the Address fields.
			await checkoutPage.fillShippingAddress();
			await checkoutPage.fillBillingAddress();

			// Place the order.
			await checkoutPage.placeOrder();

			// Expect to end up on the Klarna HPP page.
			await expect(page).toHaveURL(/pay\.playground\.klarna\.com/);
			await klarnaHPP.placeOrder();

			// Verify that the order was placed.
			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
			page.close();
		});

		await test.step('Partially refund the order.', async () => {
			const context = await browser.newContext({ storageState: process.env.ADMINSTATE });
			const page = await context.newPage();

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
			page.close();
		});
	});
});
