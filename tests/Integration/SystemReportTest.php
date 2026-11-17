<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * What Klarna for WooCommerce adds to the WooCommerce system status report.
 *
 * @covers \KP_Status
 * @covers \Krokedil\Klarna\OrderManagement::report
 */
class SystemReportTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/**
	 * Covered by a single render, since the support package includes its view with include_once.
	 */
	public function test_the_report_shows_every_klarna_section_once_without_secrets(): void {
		$this->haveKlarnaCredentials(
			'se',
			[
				'merchant_id_no'   => 'mid-no',
				'kom'              => '',
				'kom_auto_capture' => 'yes',
				'kec_settings'     => '',
				'kec_enabled'      => 'no',
				'onsite_messaging' => '',
				'siwk'             => '',
			]
		);
		update_option( 'krokedil_debuglog_kp', wp_json_encode( [ [ 'title' => 'Legacy request title' ] ] ) );

		$report = $this->renderStatusReport();

		$this->assertSame( 1, substr_count( $report, 'request log</h2>' ), 'The request log is printed once, by the support package.' );
		$this->assertStringContainsString( 'Klarna for WooCommerce plugin settings', $report );
		$this->assertStringNotContainsString( 'Klarna Order Management for WooCommerce', $report, 'Order Management has no report box of its own.' );
		$this->assertStringContainsString( 'On order status change', $report, 'Order Management settings are part of the plugin settings box.' );

		// The report only prints a section whose key is saved, so each one is seeded above.
		foreach ( [ 'Klarna Order Management', 'Express Checkout', 'On-Site Messaging', 'Sign in with Klarna' ] as $section ) {
			$this->assertSame( 1, substr_count( $report, "<h2>{$section}</h2>" ), "The {$section} section is printed once." );
		}
		$this->assertStringNotContainsString( 'Legacy request title', $report, 'The legacy request log option is no longer read.' );
		$this->assertMatchesRegularExpression( '#Production countries:</td>\s*<td class="help"></td>\s*<td>NO</td>#', $report );
		$this->assertMatchesRegularExpression( '#Test countries:</td>\s*<td class="help"></td>\s*<td>SE</td>#', $report );
		$this->assertStringNotContainsString( 'secret-se', $report, 'Credentials never reach the report.' );
	}

	public function test_the_legacy_request_log_is_deleted_by_the_log_cleanup(): void {
		update_option( 'krokedil_debuglog_kp', '[]' );

		do_action( 'woocommerce_cleanup_logs' );

		$this->assertFalse( get_option( 'krokedil_debuglog_kp' ) );
	}

	public function test_order_management_reports_errors_through_the_plugin_report(): void {
		$this->assertSame( KP_WC()->report(), KP_WC()->order_management->report() );
	}

	private function renderStatusReport(): string {
		if ( ! function_exists( 'wc_help_tip' ) ) {
			require_once \WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		}

		ob_start();
		do_action( 'woocommerce_system_status_report' );

		return (string) ob_get_clean();
	}
}
