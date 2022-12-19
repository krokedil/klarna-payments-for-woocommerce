import { Reporter } from "@playwright/test/types/testReporter";
import * as core from '@actions/core';

class GithubReporter implements Reporter {
    async onEnd(result) {
        // Print annotation to github actions with the versions used in the test.
        core.notice(process.env.WP_VERSION, { title: 'WordPress Version' });
        core.notice(process.env.WC_VERSION, { title: 'WooCommerce Version' });
        core.notice(process.env.PHP_VERSION, { title: 'PHP Version' });
    }
}

export default GithubReporter;
