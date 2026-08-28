<?php
/**
 * Constant declarations for PHPStan static analysis only.
 *
 * Not loaded at runtime — the real values are defined in klarna-payments-for-woocommerce.php.
 * These declarations exist so PHPStan knows the constants exist when analyzing files
 * that are pulled in via dynamic include_once and therefore can't be traced statically.
 *
 * @package WC_Klarna_Payments
 *
 * @phpcs:disable
 */

define( 'WC_KLARNA_PAYMENTS_VERSION', '0.0.0' );
define( 'WC_KLARNA_PAYMENTS_MIN_PHP_VER', '7.4.0' );
define( 'WC_KLARNA_PAYMENTS_MIN_WC_VER', '5.6.0' );
define( 'WC_KLARNA_PAYMENTS_MAIN_FILE', __FILE__ );
define( 'WC_KLARNA_PAYMENTS_PLUGIN_PATH', __DIR__ );
define( 'WC_KLARNA_PAYMENTS_PLUGIN_URL', 'https://example.com' );

// Missing constants that are set in WordPress but not in their stubs.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// Missing constants that are set in WooCommerce but not in their stubs.
define( 'WOOCOMMERCE_VERSION', '0.0.0' );
