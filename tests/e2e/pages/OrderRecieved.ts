import { APIRequestContext, Locator, Page } from '@playwright/test';
import { GetWcApiClient } from '../utils/Utils';

export class OrderRecieved {
	readonly page: Page;

	readonly orderIdLocator: Locator;

	constructor(page: Page) {
		this.page = page;
	}

	async goto() {
		// No goto for this page, it's reached by placing an order and then redirected to it.
	}

	async getOrderId(): Promise<string> {
		const orderIdLocator: Locator = this.page.locator('li.woocommerce-order-overview__order.order > strong');
		const orderId = await orderIdLocator.innerText();
		return orderId;
	}

	async getOrder(): Promise<any> {
		const orderId = await this.getOrderId();
		const apiclient = await GetWcApiClient();
		const response = await apiclient.get(`orders/${orderId}`);
		const order = await response.json();
		return order;
	}
}
