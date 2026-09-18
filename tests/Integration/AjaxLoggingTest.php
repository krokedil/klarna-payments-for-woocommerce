<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * The bounds on the endpoint the checkout page reports client side problems to.
 * kp_wc_log_js() itself reads the message with filter_input( INPUT_POST ), which
 * stays null in CLI, so the handler belongs to E2E.
 *
 * @covers \KP_AJAX::truncate_log_js_message
 * @covers \KP_AJAX::log_js_budget_spent
 * @covers \KP_AJAX::log_js_identity
 */
class AjaxLoggingTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// The notice about dropped messages goes through KP_Logger, which reads 'logging'
		// off the raw settings option without a fallback.
		$this->setKlarnaSettings( [ 'logging' => 'yes' ] );
	}

	public function test_a_message_within_the_cap_is_logged_as_sent(): void {
		$message = str_repeat( 'a', \KP_AJAX::LOG_JS_MAX_LENGTH );

		$this->assertSame( $message, \KP_AJAX::truncate_log_js_message( $message ) );
	}

	public function test_an_oversized_message_is_cut_down_to_the_cap(): void {
		$bounded = \KP_AJAX::truncate_log_js_message( str_repeat( 'a', 50000 ) );

		$this->assertStringEndsWith( ' [truncated]', $bounded );
		$this->assertSame(
			\KP_AJAX::LOG_JS_MAX_LENGTH,
			mb_strlen( str_replace( ' [truncated]', '', $bounded ) ),
			'What survives the cut is the first LOG_JS_MAX_LENGTH characters.'
		);
	}

	public function test_an_oversized_message_is_never_cut_mid_character(): void {
		// Four byte characters: a byte-wise cut would leave a broken one at the end.
		$bounded = \KP_AJAX::truncate_log_js_message( str_repeat( '😀', 50000 ) );

		$this->assertSame( $bounded, wp_check_invalid_utf8( $bounded, true ) );
	}

	public function test_a_session_may_log_up_to_its_budget(): void {
		$spent = [];

		for ( $i = 0; $i < \KP_AJAX::LOG_JS_MAX_MESSAGES; $i++ ) {
			$spent[] = \KP_AJAX::log_js_budget_spent( 'sess-budget' );
		}

		$this->assertSame( [], array_filter( $spent ), 'Nothing is dropped inside the budget.' );
	}

	public function test_a_session_past_its_budget_is_dropped(): void {
		for ( $i = 0; $i < \KP_AJAX::LOG_JS_MAX_MESSAGES; $i++ ) {
			\KP_AJAX::log_js_budget_spent( 'sess-flooded' );
		}

		$this->assertTrue( \KP_AJAX::log_js_budget_spent( 'sess-flooded' ) );
		$this->assertTrue( \KP_AJAX::log_js_budget_spent( 'sess-flooded' ), 'And stays dropped.' );
	}

	/**
	 * A message reported before a Klarna session exists still has to be counted against
	 * something. It must not be the WooCommerce customer id, which a request sending no
	 * session cookie is handed afresh every time.
	 */
	public function test_a_message_without_a_klarna_session_is_counted_against_the_caller(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

		for ( $i = 0; $i < \KP_AJAX::LOG_JS_MAX_MESSAGES; $i++ ) {
			$this->assertFalse( \KP_AJAX::log_js_budget_spent( null ) );
		}

		$this->assertTrue( \KP_AJAX::log_js_budget_spent( null ), 'The same caller keeps the same budget across requests.' );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.11';

		$this->assertFalse( \KP_AJAX::log_js_budget_spent( null ), 'A different caller has its own budget.' );
	}

	/**
	 * The budget and the log line name the same thing, so a dropped message can be traced back
	 * to the messages that spent the budget.
	 */
	public function test_a_message_is_labelled_by_whatever_it_is_counted_against(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';

		$this->assertSame( 'sess-labelled', \KP_AJAX::log_js_identity( 'sess-labelled' ) );
		$this->assertSame( '203.0.113.20', \KP_AJAX::log_js_identity( null ), 'A message reported before a Klarna session exists is labelled by its caller.' );
	}

	public function test_one_session_cannot_spend_another_sessions_budget(): void {
		for ( $i = 0; $i < \KP_AJAX::LOG_JS_MAX_MESSAGES; $i++ ) {
			\KP_AJAX::log_js_budget_spent( 'sess-noisy' );
		}

		$this->assertFalse( \KP_AJAX::log_js_budget_spent( 'sess-quiet' ) );
	}
}
