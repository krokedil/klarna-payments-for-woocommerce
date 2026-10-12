<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Records what the plugin writes to its WooCommerce log, so masking can be asserted on it. */
trait CanCaptureLogs {

	/**
	 * Every captured message, in order.
	 *
	 * @var array<int, string>
	 */
	private $capturedLogMessages = [];

	/**
	 * Whether the logger's enabled flag was forced on, so tearDown can put it back.
	 *
	 * @var bool
	 */
	private $forcedLoggingOn = false;

	/**
	 * Turn logging on for this test and start capturing.
	 *
	 * The plugin's logger reads the setting once, at bootstrap, where the suite has no
	 * credentials and therefore no logging. Setting the option covers the callers that
	 * read it per call; the flag on the instance has to be flipped directly.
	 */
	protected function haveLoggingEnabled(): void {
		$settings = get_option( self::KP_SETTINGS_OPTION, [] );
		$this->setKlarnaSettings( array_merge( is_array( $settings ) ? $settings : [], [ 'logging' => 'yes' ] ) );
		$this->setLoggerEnabled( true );

		$this->forcedLoggingOn = true;
		$this->captureLogs();
	}

	/** Puts the logger back the way the suite bootstrapped it. */
	protected function restoreLogging(): void {
		if ( $this->forcedLoggingOn ) {
			$this->setLoggerEnabled( false );
			$this->forcedLoggingOn = false;
		}

		if ( has_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ] ) ) {
			remove_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ], 10 );
		}

		$this->capturedLogMessages = [];
	}

	private function setLoggerEnabled( bool $enabled ): void {
		$logger   = \KP_WC()->logger();
		$property = new \ReflectionProperty( $logger, 'enabled' );
		$property->setAccessible( true );
		$property->setValue( $logger, $enabled );
	}

	/** Starts capturing. Safe to call more than once. */
	protected function captureLogs(): void {
		$this->capturedLogMessages = [];

		if ( has_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ] ) ) {
			return;
		}

		add_filter( 'woocommerce_logger_log_message', [ $this, 'captureLogMessage' ], 10, 3 );
	}

	/** The `woocommerce_logger_log_message` callback. Public so WordPress can call it. */
	public function captureLogMessage( $message, $level, $context ) {
		if ( 'klarna_payments' === ( $context['source'] ?? '' ) ) {
			$this->capturedLogMessages[] = (string) $message;
		}

		return $message;
	}

	/**
	 * Every captured message as one string, which is what a leak assertion reads.
	 *
	 * Re-encoded without the escaping, so a search reads the value the customer entered
	 * rather than `G\u00f6teborg`.
	 */
	protected function loggedText(): string {
		$readable = [];

		foreach ( $this->capturedLogMessages as $message ) {
			$entry      = json_decode( $message, true );
			$readable[] = null === $entry
				? $message
				: wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		return implode( "\n", $readable );
	}

	/**
	 * The decoded entry whose title matches, since a single call logs several requests.
	 *
	 * @return array The decoded log entry.
	 */
	protected function loggedEntry( string $title ): array {
		foreach ( $this->capturedLogMessages as $message ) {
			$entry = json_decode( $message, true );

			if ( is_array( $entry ) && ( $entry['title'] ?? '' ) === $title ) {
				return $entry;
			}
		}

		$this->fail( sprintf( 'No log entry titled "%s". Titles logged: %s', $title, $this->loggedTitles() ) );
	}

	/** The titles of every decoded entry, for a failure message. */
	private function loggedTitles(): string {
		$titles = [];

		foreach ( $this->capturedLogMessages as $message ) {
			$entry    = json_decode( $message, true );
			$titles[] = is_array( $entry ) ? ( $entry['title'] ?? '(none)' ) : '(not an entry)';
		}

		return empty( $titles ) ? '(nothing was logged)' : implode( ', ', $titles );
	}

	/**
	 * Assert none of the given values reached the log.
	 *
	 * @param array<string, string> $values Description of the value, keyed by what to look for.
	 */
	protected function assertNotLogged( array $values ): void {
		$logged = $this->loggedText();

		$this->assertNotSame( '', $logged, 'Nothing was logged, so the assertion would pass for the wrong reason.' );

		foreach ( $values as $value => $description ) {
			$this->assertStringNotContainsString( (string) $value, $logged, "The log leaked the {$description}." );
		}
	}
}
