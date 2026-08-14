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

/*
 * Set WP_HOME and WP_SITEURL from the request, in wp-config.php.
 *
 * They have to be constants rather than option filters: wp_plugin_directory_constants()
 * freezes WP_CONTENT_URL and WP_PLUGIN_URL from siteurl before mu-plugins load, so every
 * wp-content asset URL is decided before a filter could reach it.
 *
 * A request through the ngrok tunnel answers as WORDPRESS_URL, which is what Klarna's SDK
 * needs. One served straight off the built-in server answers as itself, which is how the
 * EndToEnd suite reaches wp-admin without the tunnel; see the note in
 * tests/_mu-plugins/01-https-proxy.php.
 */
$wpConfig = dirname(__DIR__) . '/tests/_wordpress/wp-config.php';
if (is_file($wpConfig)) {
	$wpUrl = $_ENV['WORDPRESS_URL'];
	echo "Ensuring WP_HOME and WP_SITEURL in {$wpConfig} follow the request, defaulting to {$wpUrl}\n";

	/*
	 * Only an explicit localhost request is served as itself. Anything else, including
	 * everything arriving through the tunnel, keeps answering as WORDPRESS_URL: the host the
	 * agent forwards upstream is its own internal endpoint rather than the public one, and
	 * matching on that would answer the checkout over http, which breaks Klarna's SDK and
	 * reads as the iframe misbehaving.
	 */
	$block = <<<PHP
	// >>> KP tests: a request straight to the built-in server answers as itself.
	if (! defined('WP_HOME')) {
	    \$kp_host = \$_SERVER['HTTP_HOST'] ?? '';
	    \$kp_url  = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?\$/', \$kp_host)
	        ? 'http://' . \$kp_host
	        : '{$wpUrl}';

	    define('WP_HOME', \$kp_url);
	    define('WP_SITEURL', \$kp_url);
	}
	// <<< KP tests
	PHP;

	$contents = file_get_contents($wpConfig);

	// Drop whatever a previous run left behind, then re-insert.
	$contents = preg_replace('/\n?\/\/ >>> KP tests:.*?\/\/ <<< KP tests\n/s', '', $contents);
	$contents = preg_replace("/\n?define\('WP_(HOME|SITEURL)',[^;]*\);/", '', $contents);
	$contents = preg_replace('/^<\?php/', "<?php\n\n" . $block . "\n", $contents, 1);

	file_put_contents($wpConfig, $contents);
}
