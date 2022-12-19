import { chromium, FullConfig } from '@playwright/test';
import { AdminLogin } from './utils/Utils';

async function globalSetup(config: FullConfig) {
	const { storageState, baseURL } = config.projects[0].use;
	const contextOptions = { baseURL };

	const browser = await chromium.launch({ headless: true });
	const adminContext = await browser.newContext(contextOptions);
	const adminPage = await adminContext.newPage();
	const guestContext = await browser.newContext(contextOptions);
	const guestPage = await guestContext.newPage();

	process.env.ADMINSTATE = `${storageState}adminStorageState.json`;
	process.env.GUESTSTATE = `${storageState}guestStorageState.json`;

	// Login to the admin page.
	await AdminLogin(adminPage);

	// Get the version of the plugin from the plugins page.
	await adminPage.goto('/wp-admin/plugins.php');

	await adminPage.locator('tr[data-slug=klarna-payments-for-woocommerce] td.plugin-title strong').textContent().then((text) => {
		process.env.PLUGIN_NAME = text;
	});

	await adminPage.locator('tr[data-slug=klarna-payments-for-woocommerce] div.plugin-version-author-uri').textContent().then((text) => {
		process.env.PLUGIN_VERSION = text.split(' ')[1];
	});

	// Set api credentials and enable the gateway.
	if (process.env.KLARNA_API_USERNAME) {
		adminPage.on('dialog', async (dialog) => {
			await dialog.accept();
		});
		await adminPage.goto('/wp-admin/admin.php?page=wc-settings&tab=checkout&section=klarna_payments');
		await adminPage.check('#woocommerce_klarna_payments_enabled');
		await adminPage.check('#woocommerce_klarna_payments_testmode');
		await adminPage.check('#woocommerce_klarna_payments_logging');
		// Toggle the credentials to show.
		await adminPage.click('#woocommerce_klarna_payments_credentials_se > a');
		await adminPage.fill('#woocommerce_klarna_payments_test_merchant_id_se', process.env.KLARNA_API_USERNAME);
		await adminPage.fill('#woocommerce_klarna_payments_test_shared_secret_se', process.env.KLARNA_API_PASSWORD);

		// Save settings and expect reload.
		await Promise.all([adminPage.click('button[name="save"]'), adminPage.waitForNavigation()]);
	}


	// While we are here, go to the status page and set environment variables for the WP, WooCommerce and PHP.
	await adminPage.goto('/wp-admin/admin.php?page=wc-status');
	const wpVersion = await adminPage.locator('tr', { has: adminPage.locator('td[data-export-label="WP Version"]') }).locator('td').nth(2).innerText();
	const wcVersion = await adminPage.locator('tr', { has: adminPage.locator('td[data-export-label="WC Version"]') }).locator('td').nth(2).innerText();
	const phpVersion = await adminPage.locator('tr', { has: adminPage.locator('td[data-export-label="PHP Version"]') }).locator('td').nth(2).innerText();

	// Split all versions on space and take the first to avoid any extra text.
	process.env.WP_VERSION = wpVersion.trim().split(' ')[0];
	process.env.WC_VERSION = wcVersion.trim().split(' ')[0];
	process.env.PHP_VERSION = phpVersion.trim().split(' ')[0];

	// Save signed-in state to 'storageState.json'.
	await adminPage.context().storageState({ path: process.env.ADMINSTATE });
	await adminContext.close();

	// Save guest state to 'storageState.json'.
	await guestPage.context().storageState({ path: process.env.GUESTSTATE });
	await guestContext.close();

	await browser.close();
}

export default globalSetup;
