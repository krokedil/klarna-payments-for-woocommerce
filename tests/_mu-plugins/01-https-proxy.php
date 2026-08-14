<?php
/**
 * Plugin Name: KP Tests, HTTPS proxy handling
 * Description: Makes a request that arrived through the ngrok tunnel behave as if it were served over HTTPS at the public host. Loaded only inside the Codeception test WP install.
 *
 * Without it is_ssl() returns false (the built-in server speaks HTTP), so Klarna's SDK
 * refuses to load and WP generates http:// URLs that Chrome blocks as mixed content.
 *
 * Only for requests that actually came through the tunnel. A request served straight off
 * the built-in server answers as itself, which is how the EndToEnd suite reaches wp-admin:
 * only Klarna's SDK needs the tunnel, and an admin screen behind it stalls, because enough
 * of its ~150 subresources never answer that the screen never finishes loading.
 *
 * The matching WP_HOME / WP_SITEURL are set in wp-config.php by tests/_bootstrap.php,
 * which is early enough for the wp-content asset URLs. Do not move that here.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

// Read off WP_HOME rather than X-Forwarded-Proto, which ngrok does not always send, so this
// agrees with the URL wp-config.php already settled on.
if (defined('WP_HOME') && strpos(WP_HOME, 'https://') === 0) {
    $_SERVER['HTTPS'] = 'on';
}
