<?php
/**
 * Plugin Name: KP Tests, inject Klarna test credentials
 * Description: Overlays the `woocommerce_klarna_payments_settings` option with credentials read from env vars at request time, so the dump.sql can ship without secrets and credentials can be swapped per-environment (dev/CI) without re-generating the dump.
 *
 * EndToEnd only: codeception.yml hands the credential env vars to the built-in server, so
 * they are absent in the codecept process. The Integration suite sets its own settings per
 * test, and forcing enabled/testmode there would override what a test just configured.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('option_woocommerce_klarna_payments_settings', static function ($settings) {
    $regions = [
        'se' => ['mid' => 'KLARNA_TEST_MID_SE', 'secret' => 'KLARNA_TEST_SECRET_SE'],
    ];

    $credentials = [];

    foreach ($regions as $cc => $env) {
        $mid    = getenv($env['mid']);
        $secret = getenv($env['secret']);
        if (is_string($mid) && $mid !== '' && is_string($secret) && $secret !== '') {
            $credentials['test_merchant_id_' . $cc]   = $mid;
            $credentials['test_shared_secret_' . $cc] = $secret;
        }
    }

    // Nothing to inject, leave the settings exactly as stored.
    if (empty($credentials)) {
        return $settings;
    }

    if (! is_array($settings)) {
        $settings = [];
    }

    $settings['enabled']  = 'yes';
    $settings['testmode'] = 'yes';

    return array_merge($settings, $credentials);
});
