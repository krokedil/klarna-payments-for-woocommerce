<?php
/**
 * Shared test bootstrap, required by tests/EndToEnd/_bootstrap.php.
 *
 * Runs at SUITE_INIT, which is too late to scaffold WordPress, so the install lives in
 * composer's post-autoload-dump-dev hook. What stays here: a sentinel so the file is safe to
 * require twice, and re-syncing tests/_mu-plugins/ into the install on every run, because
 * Installation::scaffold does not touch wp-content/mu-plugins.
 */

if (defined('KP_TEST_BOOTSTRAP_DONE')) {
    return;
}
define('KP_TEST_BOOTSTRAP_DONE', true);

$kp_mu_source  = dirname(__DIR__) . '/tests/_mu-plugins';
$kp_mu_plugins = dirname(__DIR__) . '/tests/_wordpress/wp-content/mu-plugins';

if (is_dir($kp_mu_plugins) && is_dir($kp_mu_source)) {
    foreach (glob($kp_mu_source . '/*.php') as $kp_src) {
        $kp_dest = $kp_mu_plugins . '/' . basename($kp_src);
        if (! is_file($kp_dest) || filemtime($kp_src) > filemtime($kp_dest)) {
            copy($kp_src, $kp_dest);
        }
    }
}

unset($kp_mu_source, $kp_mu_plugins, $kp_src, $kp_dest);

// Load the env vars from .env in the tests/ directory. This is where the test WP installation's URL, DB connection string, and other config live.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Ensure the WP_HOME and WP_SITE constants are set for the test environment in the wp-config.php file.
$wpConfig = dirname(__DIR__) . '/tests/_wordpress/wp-config.php';
if (is_file($wpConfig)) {
	$wpUrl = $_ENV['WORDPRESS_URL'];
	echo "Ensuring WP_HOME and WP_SITEURL in {$wpConfig} are set to {$wpUrl}\n";

	$wpConfigContents = file_get_contents($wpConfig);
	// Add the defines if absent; otherwise swap the getenv('WORDPRESS_URL') form for the literal URL.
	if (strpos($wpConfigContents, "define('WP_HOME'") === false || strpos($wpConfigContents, "define('WP_SITEURL'") === false) {
		$wpConfigContents = str_replace(
			"<?php",
			"<?php\n\n" .
			"define('WP_HOME', '{$wpUrl}');\n" .
			"define('WP_SITEURL', '{$wpUrl}');\n",
			$wpConfigContents
		);
		file_put_contents($wpConfig, $wpConfigContents);
	} else {
		$wpConfigContents = preg_replace(
			"/define\('WP_HOME',\s*getenv\('WORDPRESS_URL'\)\);/",
			"define('WP_HOME', '{$wpUrl}');",
			$wpConfigContents
		);
		$wpConfigContents = preg_replace(
			"/define\('WP_SITEURL',\s*getenv\('WORDPRESS_URL'\)\);/",
			"define('WP_SITEURL', '{$wpUrl}');",
			$wpConfigContents
		);
		file_put_contents($wpConfig, $wpConfigContents);
	}
}
