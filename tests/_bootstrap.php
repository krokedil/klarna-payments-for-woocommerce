<?php
/**
 * Shared test bootstrap, required by tests/EndToEnd/_bootstrap.php.
 *
 * Runs at SUITE_INIT, which is too late to scaffold WordPress, so the install lives in
 * composer's post-autoload-dump-dev hook. What stays here: a sentinel so the file is safe
 * to require twice, syncing tests/_mu-plugins/ into the install, and pinning the site URL.
 */

if (defined('KP_TEST_BOOTSTRAP_DONE')) {
    return;
}
define('KP_TEST_BOOTSTRAP_DONE', true);

$kp_mu_source  = dirname(__DIR__) . '/tests/_mu-plugins';
$kp_mu_plugins = dirname(__DIR__) . '/tests/_wordpress/wp-content/mu-plugins';

if (is_dir($kp_mu_plugins) && is_dir($kp_mu_source)) {
    $kp_wanted = [];

    foreach (glob($kp_mu_source . '/*.php') as $kp_src) {
        $kp_wanted[] = basename($kp_src);
        $kp_dest     = $kp_mu_plugins . '/' . basename($kp_src);
        if (! is_file($kp_dest) || filemtime($kp_src) > filemtime($kp_dest)) {
            copy($kp_src, $kp_dest);
        }
    }

    // Drop the ones an earlier checkout installed and this one no longer has, matched on
    // our own plugin header so wp-browser's SQLite drop-in is left alone.
    foreach (glob($kp_mu_plugins . '/*.php') as $kp_installed) {
        if (in_array(basename($kp_installed), $kp_wanted, true)) {
            continue;
        }
        if (strpos((string) file_get_contents($kp_installed), 'Plugin Name: KP Tests,') !== false) {
            unlink($kp_installed);
        }
    }
}

unset($kp_mu_source, $kp_mu_plugins, $kp_src, $kp_dest, $kp_wanted, $kp_installed);

// Load the env vars from .env in the tests/ directory. This is where the test WP installation's URL, DB connection string, and other config live.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/*
 * Pin WP_HOME and WP_SITEURL to WORDPRESS_URL, the local built-in server. Constants rather
 * than option filters, because wp_plugin_directory_constants() freezes WP_CONTENT_URL from
 * siteurl before mu-plugins load; written here rather than committed, because WPDb reloads
 * a dump carrying whoever generated it. The tunnel is dealt with in
 * tests/_mu-plugins/01-klarna-public-url.php.
 */
$wpConfig = dirname(__DIR__) . '/tests/_wordpress/wp-config.php';
if (is_file($wpConfig)) {
	$wpUrl = rtrim($_ENV['WORDPRESS_URL'], '/');
	echo "Pinning WP_HOME and WP_SITEURL in {$wpConfig} to {$wpUrl}\n";

	$block = <<<PHP
	// >>> KP tests: the site answers as WORDPRESS_URL, tunnelled requests included.
	if (! defined('WP_HOME')) {
	    define('WP_HOME', '{$wpUrl}');
	    define('WP_SITEURL', '{$wpUrl}');
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
