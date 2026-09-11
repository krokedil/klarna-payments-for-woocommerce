<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\IntegrationTestCase;

/**
 * What reaches the log file. Both request layers log every call they make, so the
 * assertion that matters is which parts of a Klarna payload a support log keeps.
 *
 * @covers \Krokedil\Klarna\Logging\LogMasking
 * @covers \Krokedil\Klarna\Logging\LogWriter
 * @covers \KP_Requests::mask_request_url
 * @covers \KP_Logger::log
 */
class LogMaskingTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** The customer details the fixtures put into every payload. */
	private const PERSONAL_DATA = [
		'karl@example.com' => 'billing email',
		'+46701234567'     => 'billing phone',
		'Karlsson'         => 'family name',
		'Storgatan 1'      => 'street address',
		'Lgh 1102'         => 'second address line',
	];

	protected function setUp(): void {
		parent::setUp();

		$this->haveLoggingEnabled();
	}

	public function test_a_session_request_logs_no_customer_data(): void {
		$this->haveSessionForACart();

		$this->assertNotLogged( self::PERSONAL_DATA );
	}

	public function test_a_session_request_logs_no_credentials(): void {
		$this->haveSessionForACart();

		$request = $this->loggedEntry( 'Create session' )['request'];

		$this->assertSame( '[REDACTED]', $request['headers']['Authorization'] );
	}

	/**
	 * Masking a whole payload is easy and useless. These are the fields a support
	 * case is actually read for, and they have to survive.
	 *
	 * @dataProvider provide_fields_kept_readable
	 */
	public function test_the_log_keeps_what_support_reads( string $value, string $description ): void {
		$this->haveSessionForACart();

		$this->assertStringContainsString( $value, $this->loggedText(), "The log lost the {$description}." );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_fields_kept_readable(): array {
		return [
			'the city'        => [ 'Göteborg', 'billing city' ],
			'the postal code' => [ '41106', 'billing postal code' ],
			// Basket contents are kept deliberately: an order line is what a dispute is about.
			'the order lines' => [ 'KP Test Product', 'product name' ],
			// The session id is an identifier, not a credential, and is how logs are correlated.
			'the session id'  => [ 'sess-1', 'Klarna session id' ],
		];
	}

	public function test_an_authorization_token_never_reaches_the_log(): void {
		$this->havePlacedOrder();

		$entry = $this->loggedEntry( 'Place order' );

		$this->assertStringEndsWith( '/payments/v1/authorizations/[REDACTED]/order', $entry['request_url'] );
		$this->assertStringNotContainsString( 'auth-token-1', $this->loggedText() );
	}

	public function test_the_order_received_url_never_reaches_the_log(): void {
		$this->haveHostedPaymentPage();

		$entry = $this->loggedEntry( 'Create HPP' );

		// The success URL carries the order key, which is enough to read the order.
		$this->assertSame( '[REDACTED]', $entry['request']['body']['merchant_urls'] );
	}

	/**
	 * The order management layer logs what Klarna returns, which is the richest
	 * customer record the plugin ever handles.
	 */
	public function test_an_order_management_response_logs_no_customer_data(): void {
		$order = $this->haveKlarnaOrder( [ 'paid' => true, 'billing' => $this->swedishAddress() ] );

		$this->willRetrieveKlarnaOrder(
			[
				'billing_address' => [
					'given_name'     => 'Karl',
					'family_name'    => 'Karlsson',
					'email'          => 'karl@example.com',
					'phone'          => '+46701234567',
					'street_address' => 'Storgatan 1',
					'postal_code'    => '41106',
					'city'           => 'Göteborg',
				],
				'customer'        => [
					'date_of_birth'                    => '1980-01-01',
					'national_identification_number'   => '800101-1234',
				],
			]
		);
		$this->willCancel();

		KP_WC()->order_management->cancel_klarna_order( $order->get_id(), false );

		$this->assertNotLogged(
			self::PERSONAL_DATA + [
				'1980-01-01'  => 'date of birth',
				'800101-1234' => 'national identification number',
			]
		);
	}

	/**
	 * The allow list is what makes the masking hold when Klarna adds a field, so it has to
	 * reach the order management layer too and not just the payments one.
	 */
	public function test_an_order_management_response_masks_a_field_no_rule_names(): void {
		$order = $this->haveKlarnaOrder( [ 'paid' => true, 'billing' => $this->swedishAddress() ] );

		$this->willRetrieveKlarnaOrder(
			[
				// Neither is named by a rule or a key name: only the allow list catches them.
				'billing_address' => [ 'title' => 'Mr', 'city' => 'Göteborg' ],
				'customer'        => [ 'type' => 'person', 'organization_entity_type' => 'LIMITED_COMPANY' ],
			]
		);
		$this->willCancel();

		KP_WC()->order_management->cancel_klarna_order( $order->get_id(), false );

		$entry = $this->loggedEntry( 'Retrieve Klarna order' )['response']['body'];

		$this->assertSame( '[REDACTED]', $entry['billing_address']['title'] );
		$this->assertSame( '[REDACTED]', $entry['customer']['organization_entity_type'] );
		$this->assertSame( 'Göteborg', $entry['billing_address']['city'], 'The allow list kept the city.' );
		$this->assertSame( 'person', $entry['customer']['type'], 'The allow list kept the customer type.' );
	}

	/** Drives a create-session the way the checkout page does. */
	private function haveSessionForACart(): void {
		$this->simulateCheckoutPage();
		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->haveCartWith( [ $this->haveSimpleProduct( [ 'name' => 'KP Test Product', 'sku' => 'kp-test-1', 'price' => '100.00' ] ) ] );

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$this->assertSame( 'sess-1', KP_WC()->session->get_klarna_session_id(), 'Fixture failed to create a session.' );
	}

	private function havePlacedOrder(): void {
		$order = $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'name' => 'KP Test Product', 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress(),
			]
		);

		$this->willPlaceOrder();
		KP_WC()->api->place_order( 'SE', 'auth-token-1', $order->get_id() );
	}

	private function haveHostedPaymentPage(): void {
		$order = $this->haveOrder(
			[
				'items'   => [ $this->haveSimpleProduct( [ 'name' => 'KP Test Product', 'price' => '100.00' ] ) ],
				'billing' => $this->swedishAddress(),
			]
		);

		$this->willCreateHpp();
		KP_WC()->api->create_hpp( 'SE', 'sess-1', $order->get_id() );
	}
}
