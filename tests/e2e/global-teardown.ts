import { FullConfig } from '@playwright/test';
import { Teardown } from './utils/Setup';

const globalTeardown = async (config: FullConfig) => {
	// Destroy the test data using the WC API.
	await Teardown();
}

export default globalTeardown;
