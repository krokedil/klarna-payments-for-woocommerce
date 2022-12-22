import { test, expect } from '@playwright/test';
import { KlarnaPaymentsIframe } from '../locators/KlarnaPaymentsIFrame';
import { AdminSingleOrder } from '../pages/AdminSingleOrder';
import { Cart } from '../pages/Cart';
import { Checkout } from '../pages/Checkout';
import { OrderRecieved } from '../pages/OrderRecieved';
import { AdminLogin, GetWcApiClient } from '../utils/Utils';

test.describe('Order management @shortcode', () => {
	test.use({ storageState: process.env.GUESTSTATE });

	let orderId;

	test.afterEach(async ({ page }) => {
		// Delete the order from WooCommerce.
		const wcApiClient = await GetWcApiClient();
		await wcApiClient.delete(`orders/${orderId}`);

		// Clear all cookies.
		await page.context().clearCookies();
	});

	test('Can capture an order', async ({ page }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new Cart(page);
			const orderRecievedPage = new OrderRecieved(page);
			const checkoutPage = new Checkout(page);
			const iframe = new KlarnaPaymentsIframe(page)
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			await iframe.fillNin();
			await iframe.clickConfirm();

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Capture the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order captured')).toBe(true);
		});
	});

	test('Can cancel an order', async ({ page }) => {
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new Cart(page);
			const orderRecievedPage = new OrderRecieved(page);
			const checkoutPage = new Checkout(page);
			const iframe = new KlarnaPaymentsIframe(page)
			await cartPage.addtoCart(['simple-25']);

			await checkoutPage.goto();
			await checkoutPage.fillBillingAddress();
			await checkoutPage.placeOrder();

			await iframe.fillNin();
			await iframe.clickConfirm();

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Cancel the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.cancelOrder();

			expect(await adminSingleOrder.hasOrderNoteWithText('Klarna order cancelled')).toBe(true);
		});
	});

	test('Can refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new Cart(page);
			const orderRecievedPage = new OrderRecieved(page);
			const checkoutPage = new Checkout(page);
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
		});

		await test.step('Fully refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
		});
	});

	test('Can partially refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Klarna Payments.', async () => {
			const cartPage = new Cart(page);
			const orderRecievedPage = new OrderRecieved(page);
			const checkoutPage = new Checkout(page);
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
		});

		await test.step('Partially refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);
			expect(await adminSingleOrder.hasOrderNoteWithText('refunded via Klarna')).toBe(true);
		});
	});
});
