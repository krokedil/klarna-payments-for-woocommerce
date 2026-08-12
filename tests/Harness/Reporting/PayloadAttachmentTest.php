<?php

declare(strict_types=1);

namespace Tests\Harness\Reporting;

use Tests\Support\IntegrationTestCase;

/**
 * Pins that recorded Klarna traffic reaches the report, and reaches it
 * scrubbed. A leak here would land in a downloadable CI artifact.
 */
class PayloadAttachmentTest extends IntegrationTestCase {

	public function test_describes_recorded_requests_as_scrubbed_json(): void {
		wp_remote_post(
			'https://api.playground.klarna.com/payments/v1/sessions',
			[
				'headers' => [ 'Authorization' => 'Basic ' . base64_encode( 'K123456_abc:sup3r-s3cret-value' ) ],
				'body'    => wp_json_encode( [ 'order_amount' => 25000 ] ),
			]
		);

		$json = $this->describeHttpRequestsForReport();

		$this->assertNotNull( $json );
		$this->assertStringContainsString( 'payments/v1/sessions', $json );
		$this->assertStringContainsString( '25000', $json );
		$this->assertStringNotContainsString( base64_encode( 'K123456_abc:sup3r-s3cret-value' ), $json );
	}

	public function test_describes_nothing_when_no_request_was_made(): void {
		$this->assertNull( $this->describeHttpRequestsForReport() );
	}
}
