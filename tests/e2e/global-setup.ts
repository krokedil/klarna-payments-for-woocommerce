import { BrowserContext, chromium, FullConfig, Page } from '@playwright/test';
import { Setup } from './utils/Setup';
import { AdminLogin, GetVersionNumbers as SetVersionNumbers, SetKpSettings } from './utils/Utils';

let adminContext: BrowserContext;
let guestContext: BrowserContext;

let adminPage: Page;
let guestPage: Page;


const globalSetup = async (config: FullConfig) => {
	const { storageState, baseURL } = config.projects[0].use;

	process.env.ADMINSTATE = `${storageState}/admin/state.json`;
	process.env.GUESTSTATE = `${storageState}/guest/state.json`;

	await setupContexts(baseURL, storageState.toString());


	// Login to the admin page.
	await AdminLogin(adminPage);

	await SetKpSettings(adminPage);

	await SetVersionNumbers(adminPage);

	// Save contexts as states.
	await adminContext.storageState({ path: process.env.ADMINSTATE });
	await guestContext.storageState({ path: process.env.GUESTSTATE });

	await adminContext.close();
	await guestContext.close();

	// Setup the test data using the WC API.
	await Setup();
}

async function setupContexts(baseUrl: string, statesDir: string) {
	adminContext = await chromium.launchPersistentContext(`${statesDir}/admin`, { headless: true, baseURL: baseUrl });
	adminPage = await adminContext.newPage();
	guestContext = await chromium.launchPersistentContext(`${statesDir}/guest`, { headless: true, baseURL: baseUrl });
	guestPage = await guestContext.newPage();
}

export default globalSetup;
