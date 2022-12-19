import { APIRequestContext, Page, request } from "@playwright/test";
const config = require('../playwright.config').default;

const {
	ADMIN_USERNAME,
	ADMIN_PASSWORD,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

export const AdminLogin = async (page: Page) => {
	await page.goto('/wp-admin');
	await page.locator('#user_login').click();
	await page.locator('#user_login').fill(ADMIN_USERNAME ?? 'admin');
	await page.locator('#user_pass').click();
	await page.locator('#user_pass').fill(ADMIN_PASSWORD ?? 'password');
	await page.getByRole('button', { name: 'Log in' }).click();
	await page.waitForLoadState('networkidle');
}

export const GetApiClient = async (): Promise<APIRequestContext> => {
	return await request.newContext({
		baseURL: `${config.use.baseURL}/wp-json/wc/v3/`,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				`${CONSUMER_KEY ?? 'admin'}:${CONSUMER_SECRET ?? 'password'}`
			).toString('base64')}`,
		},
	});
}

export const getTestOrder = () => {
	return {
		billing: {
			first_name: "John",
			last_name: "Doe",
			address_1: "969 Market",
			address_2: "",
			city: "San Francisco",
			state: "CA",
			postcode: "94103",
			country: "US",
			email: "e2e@krokedil.se"
		},
		shipping: {
			first_name: "John",
			last_name: "Doe",
			address_1: "969 Market",
			address_2: "",
			city: "San Francisco",
			state: "CA",
			postcode: "94103",
			country: "US",
		},
		line_items: [],
		shipping_lines: [
			{
				method_id: "flat_rate",
				method_title: "Flat Rate",
				total: "10.00",
			},
		],
		fee_lines: [
			{
				name: "Fee",
				total: "10.00",
			},
		],
		payment_method: "bacs",
		payment_method_title: "Direct Bank Transfer",
		status: "",
	};
};

export const getTestProduct = () => {
	return {
		name: "Test Product",
		type: "simple",
		regular_price: "9.99",
		stock_quantity: 10,
		manage_stock: true,
		backorders: 'notify'
	};
};
