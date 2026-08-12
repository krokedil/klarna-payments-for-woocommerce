<?php
/**
 * Plugin Name: KP Tests, HTTPS proxy handling
 * Description: Makes the test WP install behave as if it is served over HTTPS at the ngrok URL. Loaded only inside the Codeception test WP install.
 *
 * Without it is_ssl() returns false (the built-in server speaks HTTP), so Klarna's SDK
 * refuses to load and WP generates http:// URLs that Chrome blocks as mixed content.
 *
 * Honours ngrok's X-Forwarded-Proto, rewrites HTTP_HOST to the public host, and forces
 * siteurl/home to WORDPRESS_URL so the dump survives a different dev's ngrok subdomain.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

if (! empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

$kp_test_url = getenv('WORDPRESS_URL');
if (is_string($kp_test_url) && $kp_test_url !== '') {
    add_filter('pre_option_siteurl', static function () use ($kp_test_url) {
        return $kp_test_url;
    });
    add_filter('pre_option_home', static function () use ($kp_test_url) {
        return $kp_test_url;
    });
}
