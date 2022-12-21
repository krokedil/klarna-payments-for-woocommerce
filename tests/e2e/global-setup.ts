import { BrowserContext, chromium, FullConfig, Page } from '@playwright/test';
import { Setup } from './utils/Setup';
import { AdminLogin, GetVersionNumbers as SetVersionNumbers, SetKpSettings } from './utils/Utils';

let adminContext: BrowserContext;
let guestContext: BrowserContext;

let adminPage: Page;
let guestPage: Page;


const globalSetup = async (config: FullConfig) => {
	const { storageState, baseURL } = config.projects[0].use;

	process.env.ADMINSTATE = `${storageState}adminStorageState.json`;
	process.env.GUESTSTATE = `${storageState}guestStorageState.json`;

	await setupContexts(baseURL);

	// Login to the admin page.
	await AdminLogin(adminPage);

	await SetKpSettings(adminPage);

	await SetVersionNumbers(adminPage);

	await adminContext.close();
	await guestContext.close();

	// Setup the test data using the WC API.
	await Setup();
}

async function setupContexts(baseUrl: string) {
	adminContext = await chromium.launchPersistentContext(process.env.ADMINSTATE, { headless: true, baseURL: baseUrl });
	adminPage = await adminContext.newPage();
	guestContext = await chromium.launchPersistentContext(process.env.GUESTSTATE, { headless: true, baseURL: baseUrl });
	guestPage = await guestContext.newPage();
}

export default globalSetup;
