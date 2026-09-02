<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/** Blocks and records outbound HTTP for the Integration suite. */
trait CanInterceptHttp {

	/**
	 * Every intercepted request, in order.
	 *
	 * @var array<int, array>
	 */
	private $interceptedHttpRequests = [];

	/**
	 * Responses queued by willRespondWith(), consumed in order.
	 *
	 * @var array<int, array>
	 */
	private $queuedHttpResponses = [];

	/** Starts intercepting. Safe to call more than once. */
	protected function interceptHttp(): void {
		if ( has_filter( 'pre_http_request', [ $this, 'interceptHttpRequest' ] ) ) {
			return;
		}

		add_filter( 'pre_http_request', [ $this, 'interceptHttpRequest' ], 10, 3 );
	}

	/** The `pre_http_request` callback. Public so WordPress can call it. */
	public function interceptHttpRequest( $preempt, $args, $url ) {
		$body = $args['body'] ?? null;

		$this->interceptedHttpRequests[] = [
			'url'     => $url,
			'method'  => $args['method'] ?? 'GET',
			'headers' => $args['headers'] ?? [],
			'body'    => $body,
			'json'    => is_string( $body ) ? json_decode( $body, true ) : null,
		];

		foreach ( $this->queuedHttpResponses as $index => $queued ) {
			if ( null !== $queued['url_contains'] && false === strpos( $url, $queued['url_contains'] ) ) {
				continue;
			}

			unset( $this->queuedHttpResponses[ $index ] );

			return $queued['response'];
		}

		return new \WP_Error(
			'kp_test_http_blocked',
			sprintf(
				'Outbound HTTP is blocked in the Integration suite. Queue a response with willRespondWith() if this call is expected. URL: %s',
				$url
			)
		);
	}

	/** Queues a canned response. */
	protected function willRespondWith( array $body, int $status = 200, ?string $url_contains = null, array $headers = [] ): void {
		$this->queuedHttpResponses[] = [
			'url_contains' => $url_contains,
			'response'     => [
				'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( $headers ),
				'body'     => wp_json_encode( $body ),
				'response' => [
					'code'    => $status,
					'message' => get_status_header_desc( $status ),
				],
				'cookies'  => [],
				'filename' => null,
			],
		];
	}

	/** Every request intercepted so far. */
	protected function httpRequests(): array {
		return $this->interceptedHttpRequests;
	}

	/**
	 * Just the requests aimed at Klarna. WooCommerce core makes requests of its
	 * own that assertions about KP have to ignore.
	 */
	protected function klarnaRequests(): array {
		return array_values(
			array_filter(
				$this->interceptedHttpRequests,
				static function ( $request ) {
					return false !== strpos( $request['url'], 'klarna.com' );
				}
			)
		);
	}

	/** The Klarna requests whose URL contains the given fragment. */
	protected function klarnaRequestsTo( string $url_contains ): array {
		return array_values(
			array_filter(
				$this->klarnaRequests(),
				static function ( $request ) use ( $url_contains ) {
					return false !== strpos( $request['url'], $url_contains );
				}
			)
		);
	}

	/** The one Klarna request aimed at the given endpoint. */
	protected function klarnaRequestTo( string $url_contains ): array {
		$matching = $this->klarnaRequestsTo( $url_contains );

		if ( 1 !== count( $matching ) ) {
			$this->fail(
				sprintf(
					'Expected exactly one Klarna request to "%s", got %d. Requests made: %s',
					$url_contains,
					count( $matching ),
					$this->describeKlarnaRequests()
				)
			);
		}

		return $matching[0];
	}

	/** Asserts how many Klarna requests were made, optionally to one endpoint. */
	protected function assertKlarnaRequestCount( int $expected, string $url_contains = '', ?string $message = null ): void {
		$matching = '' === $url_contains
			? $this->klarnaRequests()
			: $this->klarnaRequestsTo( $url_contains );

		$this->assertCount(
			$expected,
			$matching,
			trim( ( $message ?? '' ) . ' Requests made: ' . $this->describeKlarnaRequests() )
		);
	}

	/** The Klarna requests made so far, for a failure message. */
	private function describeKlarnaRequests(): string {
		$urls = array_column( $this->klarnaRequests(), 'url' );

		return empty( $urls ) ? 'none' : implode( ', ', $urls );
	}

	/** Forgets recorded requests and queued responses. */
	protected function resetHttpInterception(): void {
		$this->interceptedHttpRequests = [];
		$this->queuedHttpResponses     = [];
	}

	/** Asserts that nothing called Klarna. */
	protected function assertNoKlarnaRequests( string $message = '' ): void {
		$this->assertSame(
			[],
			array_column( $this->klarnaRequests(), 'url' ),
			$message ? $message : 'Expected no requests to Klarna.'
		);
	}
}
