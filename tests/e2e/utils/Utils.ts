import { APIRequestContext, Page, request } from "@playwright/test";
const config = require('../playwright.config').default;

const {
	ADMIN_USERNAME,
	ADMIN_PASSWORD,
	CONSUMER_KEY,
	CONSUMER_SECRET,
	KLARNA_API_USERNAME,
	KLARNA_API_PASSWORD,
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

export const GetWcApiClient = async (): Promise<APIRequestContext> => {
	return await request.newContext({
		baseURL: `${config.use.baseURL}/wp-json/wc/v3/`,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				`${CONSUMER_KEY ?? 'admin'}:${CONSUMER_SECRET ?? 'password'}`
			).toString('base64')}`,
		},
	});
}

export const GetVersionNumbers = async (adminPage: Page) => {
	const apiClient = await GetWcApiClient();

	// Get the system status.
	const response = await apiClient.get('system_status');
	const status = await response.json();

	// Set the environment variables.
	process.env.WC_VERSION = status.environment.version.trim().split(' ')[0];
	process.env.WP_VERSION = status.environment.wp_version.trim().split(' ')[0];
	process.env.PHP_VERSION = status.environment.php_version.trim().split(' ')[0];
}

export const SetKpSettings = async (adminPage: Page) => {
	// Set api credentials and enable the gateway.
	if (KLARNA_API_USERNAME) {
		const apiClient = await GetWcApiClient();

		const settings = {
			enabled: true,
			settings: {
				testmode: "yes",
				logging: "yes",
				test_merchant_id_se: KLARNA_API_USERNAME,
				test_shared_secret_se: KLARNA_API_PASSWORD,
			}
		};

		// Update settings.
		await apiClient.post('payment_gateways/klarna_payments', { data: settings });
	}
}
