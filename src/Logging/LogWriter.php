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
	 * @param string $handle The log source, which the plugin logger sets for itself.
	 * @param string $message The masked, json encoded entry.
	 * @param string $level The log level, unused since the plugin logger decides it.
	 * @return bool
	 */
	public function add( $handle, $message, $level = WC_Log_Levels::NOTICE ) {
		\KP_WC()->logger()->info( $message );

		return true;
	}
}
