require('dotenv').config()
import type { PlaywrightTestConfig } from '@playwright/test';
import { devices } from '@playwright/test';

const {
	BASE_URL,
	SLOW_MO,
	VIDEO,
	CI,
} = process.env;

const config: PlaywrightTestConfig = {
	fullyParallel: true,
	testDir: './tests',
	timeout: 60 * 1000,
	expect: {
		timeout: 10 * 1000
	},
	forbidOnly: !!CI,
	retries: 1,
	workers: CI ? 4 : undefined,
	reporter: CI ? [['list'], ['github'], ['./reporter/slackReporter.ts'], ['./reporter/githubReporter.ts']] : [['list'], ['html']],
	globalSetup: require.resolve('./global-setup'),
	globalTeardown: require.resolve('./global-teardown'),
	use: {
		actionTimeout: 0,
		trace: CI ? 'off' : 'retain-on-failure',
		storageState: './states/',
		baseURL: BASE_URL ?? 'http://localhost:8080',
		video: {
			mode: VIDEO ? 'on' : 'off',
			size: {
				width: 1920,
				height: 1080,
			},
		},
		launchOptions: {
			slowMo: SLOW_MO ? parseInt(SLOW_MO, 1000) : 0,
		},
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
			},
		},
	],
};

export default config;
