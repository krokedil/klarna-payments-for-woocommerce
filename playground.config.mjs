/**
 * WordPress Playground dev-environment config for this plugin.
 * Consumed by @krokedil/wp-playground-tools — see its README for the full schema.
 */
import { envSecret } from '@krokedil/wp-playground-tools';

export default {
	slug: 'klarna-payments-for-woocommerce',

	// Claimed in the org port registry (wp-playground-tools README).
	basePort: 8890,

	// The Klarna settings screen — matches the smoke assertion in
	// .github/plugin-meta.json.
	landingPage:
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=klarna_payments',

	// The plugin requires both autoloaders (wpify-scoper writes the scoped
	// Krokedil packages to dependencies/). vendor/ may exist while
	// dependencies/ is empty, so both markers are needed to trigger install.
	// Installing pulls private krokedil/* repos over SSH.
	composer: {
		markers: ['vendor/autoload.php', 'dependencies/scoper-autoload.php'],
	},

	// No build config: blocks/build/ output is committed to git. Rebuild
	// manually with `npx wp-scripts build` when working on blocks/src/.

	options: {
		all: {
			woocommerce_klarna_payments_settings: {
				enabled: 'yes',
				testmode: 'yes',
				available_countries: ['se'],
				checkout_flow: 'redirect',
				logging: 'yes',
				// Klarna playground credentials from .env (see .env.example) —
				// missing values warn by name and leave the gateway unconfigured.
				test_merchant_id_se: envSecret('KLARNA_TEST_MERCHANT_ID_SE'),
				test_shared_secret_se: envSecret('KLARNA_TEST_SHARED_SECRET_SE'),
				test_client_id_se: envSecret('KLARNA_TEST_CLIENT_ID_SE'),
			},
		},
	},
};
