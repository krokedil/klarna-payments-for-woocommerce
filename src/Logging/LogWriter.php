<?php
namespace Krokedil\Klarna\Logging;

use KrokedilKlarnaPaymentsDeps\Krokedil\WpApi\Logger as ApiLogger;
use WC_Log_Levels;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the API package log entries through the plugin's own logger.
 *
 * The package masks the entry and then hands it to whatever sits in Logger::$log, which it
 * types as a WC_Logger. Extending that is what lets the plugin keep its own logger, and with
 * it the log level, the source context and the enabled flag.
 */
class LogWriter extends \WC_Logger {
	/**
	 * Route the package log entries through this writer.
	 *
	 * @return void
	 */
	public static function register() {
		ApiLogger::$log = new self( array() );
	}

	/**
	 * Write one masked entry from the API package.
	 *
	 * @param string $level The log level the package read from the response.
	 * @param string $message The masked, json encoded entry.
	 * @param array  $context The log context, unused since the plugin logger sets its own source.
	 * @return void
	 */
	public function log( $level, $message, $context = array() ) {
		\KP_WC()->logger()->log( $message, WC_Log_Levels::is_valid_level( $level ) ? $level : WC_Log_Levels::INFO );
	}
}
