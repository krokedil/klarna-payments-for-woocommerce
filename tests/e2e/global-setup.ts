import { AdminLogin, GetSystemReportData, GetWcApiClient, Setup } from '@krokedil/wc-test-helper';
import { BrowserContext, chromium, FullConfig, Page, request } from '@playwright/test';
import { SetKpSettings } from './utils/Utils';

const {
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

let adminContext: BrowserContext;
let guestContext: BrowserContext;

let adminPage: Page;
let guestPage: Page;


const globalSetup = async (config: FullConfig) => {
	if (process.env.BASE_URL === undefined || process.env.CI === '1') {
		// Get the base URL from ngrok and set it as an env variable.
		process.env.BASE_URL = await getBaseUrl();
	}

	const baseURL = process.env.BASE_URL;

	const wcApiClient = await GetWcApiClient(baseURL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');

	const { storageState } = config.projects[0].use;

	process.env.ADMINSTATE = `${storageState}/admin/state.json`;
	process.env.GUESTSTATE = `${storageState}/guest/state.json`;

	const GetSystemReportDataLocalTest = async () => {

		process.env.WC_VERSION = '5.5.2';
		process.env.WP_VERSION = '5.8.1';
		process.env.PHP_VERSION = '7.4';

		process.env.PLUGIN_NAME = 'Klarna Payments for WooCommerce';
		process.env.PLUGIN_VERSION = '4.0.2';

	}

	await setupContexts(baseURL, storageState.toString());

	// Login to the admin page.
	await AdminLogin(adminPage);

	// Set Klarna settings.
	await SetKpSettings(wcApiClient);

	// Get system report data, and save them to env variables.
	await GetSystemReportDataLocalTest();

	// Save contexts as states.
	await adminContext.storageState({ path: process.env.ADMINSTATE });
	await guestContext.storageState({ path: process.env.GUESTSTATE });

	await adminContext.close();
	await guestContext.close();

	// Setup the test data using the WC API.
	await Setup(wcApiClient);
}



async function setupContexts(baseUrl: string, statesDir: string) {
	adminContext = await chromium.launchPersistentContext(`${statesDir}/admin`, { headless: true, baseURL: baseUrl });
	adminPage = await adminContext.newPage();
	guestContext = await chromium.launchPersistentContext(`${statesDir}/guest`, { headless: true, baseURL: baseUrl });
	guestPage = await guestContext.newPage();
}

async function getBaseUrl() {
	const client = await request.newContext({
		baseURL: `http://localhost:4444/api/tunnels`,
	});

	const res = await client.get('');

	const data = await res.json();

	// Return the public url.
	return data.tunnels[0].public_url;
}


export default globalSetup;
