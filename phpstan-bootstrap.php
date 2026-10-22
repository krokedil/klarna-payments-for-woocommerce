<?php
/**
 * Constant declarations for PHPStan static analysis only.
 *
 * Not loaded at runtime, the real values are defined in klarna-payments-for-woocommerce.php.
 * These declarations exist so PHPStan knows the constants exist when analyzing files
 * that are pulled in via dynamic include_once and therefore can't be traced statically.
 *
 * @phpcs:disable
 */

defined( 'WC_KLARNA_PAYMENTS_VERSION' ) || define( 'WC_KLARNA_PAYMENTS_VERSION', '0.0.0' );
defined( 'WC_KLARNA_PAYMENTS_MIN_PHP_VER' ) || define( 'WC_KLARNA_PAYMENTS_MIN_PHP_VER', '8.0.0' );
defined( 'WC_KLARNA_PAYMENTS_MIN_WC_VER' ) || define( 'WC_KLARNA_PAYMENTS_MIN_WC_VER', '5.6.0' );
defined( 'WC_KLARNA_PAYMENTS_MAIN_FILE' ) || define( 'WC_KLARNA_PAYMENTS_MAIN_FILE', __FILE__ );
defined( 'WC_KLARNA_PAYMENTS_PLUGIN_PATH' ) || define( 'WC_KLARNA_PAYMENTS_PLUGIN_PATH', __DIR__ );
defined( 'WC_KLARNA_PAYMENTS_PLUGIN_URL' ) || define( 'WC_KLARNA_PAYMENTS_PLUGIN_URL', 'https://example.com' );

// Missing constants that are set in WooCommerce but not in their stubs.
defined( 'WOOCOMMERCE_VERSION' ) || define( 'WOOCOMMERCE_VERSION', '0.0.0' );
