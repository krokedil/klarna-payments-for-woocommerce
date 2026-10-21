<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\OrderManagement\Request\Post\RequestPostRefund;
use Tests\Support\IntegrationTestCase;

/**
 * Klarna rejects any request whose order lines do not add up to its amounts to the
 * minor unit. Every store and cart in SCENARIOS is run through each request that
 * carries order lines. Nothing reaches Klarna: the responses are canned.
 *
 * @covers \KP_Order_Data::adjust_rounding
 * @covers \KP_Order_Data::align_tax_rates
 * @covers \KP_Order_Data::get_klarna_order_lines_array
 * @covers \Krokedil\Klarna\OrderManagement\Request\Post\RequestPostCapture
 * @covers \Krokedil\Klarna\OrderManagement\Request\Post\RequestPostRefund
 * @covers \KP_Create_Recurring
 */
class RoundingTest extends IntegrationTestCase {

	protected ?string $storeProfile = null;

	/**
	 * Each item is `[ price, quantity, rate ]`, where rate is 'standard', '12', '6' or 'none'.
	 * The standard rate is 25% VAT in Sweden and 8.5% sales tax in the US.
	 */
	private const SCENARIOS = [
		'half-cent VAT on three lines, VAT added, rounded per line'       => [
			'items' => [ [ '9.99', 1 ], [ '19.99', 1 ], [ '29.99', 1 ] ],
		],
		'half-cent VAT on three lines, VAT added, rounded at subtotal'    => [
			'items'             => [ [ '9.99', 1 ], [ '19.99', 1 ], [ '29.99', 1 ] ],
			'round_at_subtotal' => true,
		],
		'half-cent VAT on three lines, VAT included, rounded per line'    => [
			'items'       => [ [ '9.99', 1 ], [ '19.99', 1 ], [ '29.99', 1 ] ],
			'include_tax' => true,
		],
		'half-cent VAT on three lines, VAT included, rounded at subtotal' => [
			'items'             => [ [ '9.99', 1 ], [ '19.99', 1 ], [ '29.99', 1 ] ],
			'include_tax'       => true,
			'round_at_subtotal' => true,
		],
		'six at 16.99 with shipping'                                      => [
			'items'    => [ [ '16.99', 6 ] ],
			'shipping' => '49.00',
		],
		'six at 16.99 with shipping, rounded at subtotal'                 => [
			'items'             => [ [ '16.99', 6 ] ],
			'shipping'          => '49.00',
			'round_at_subtotal' => true,
		],
		'VAT at 25, 12 and 6 percent, rounded at subtotal'                => [
			'items'             => [ [ '19.99', 3 ], [ '33.33', 2, '12' ], [ '7.77', 1, '6' ] ],
			'round_at_subtotal' => true,
		],
		'VAT at 25, 12 and 6 percent, VAT included'                       => [
			'items'       => [ [ '19.99', 3 ], [ '33.33', 2, '12' ], [ '7.77', 1, '6' ] ],
			'include_tax' => true,
		],
		'a 15 percent coupon over odd prices'                             => [
			'items'  => [ [ '9.99', 3 ], [ '14.49', 1 ] ],
			'coupon' => [ 'percent', '15' ],
		],
		'a fixed cart coupon split three ways, VAT included'              => [
			'items'       => [ [ '9.99', 1 ], [ '9.99', 1 ], [ '9.99', 1 ] ],
			'coupon'      => [ 'fixed_cart', '10' ],
			'include_tax' => true,
		],
		'odd shipping and a taxable fee'                                  => [
			'items'    => [ [ '99.99', 1 ] ],
			'shipping' => '49.99',
			'fee'      => '9.99',
		],
		'an untaxed line next to taxed ones'                              => [
			'items' => [ [ '9.99', 1 ], [ '4.99', 3, 'none' ], [ '12.49', 1, '6' ] ],
		],
		'twenty cheap lines, rounded at subtotal'                         => [
			'items'             => [
				[ '0.99', 1 ], [ '1.49', 1 ], [ '0.99', 1 ], [ '1.49', 1 ], [ '0.99', 1 ],
				[ '1.49', 1 ], [ '0.99', 1 ], [ '1.49', 1 ], [ '0.99', 1 ], [ '1.49', 1 ],
				[ '0.99', 1 ], [ '1.49', 1 ], [ '0.99', 1 ], [ '1.49', 1 ], [ '0.99', 1 ],
				[ '1.49', 1 ], [ '0.99', 1 ], [ '1.49', 1 ], [ '0.99', 1 ], [ '1.49', 1 ],
			],
			'round_at_subtotal' => true,
		],
		'a 10 percent coupon, VAT included, rounded at subtotal'          => [
			'items'             => [ [ '16.99', 6 ], [ '7.77', 1, '6' ] ],
			'coupon'            => [ 'percent', '10' ],
			'include_tax'       => true,
			'round_at_subtotal' => true,
		],
		'US sales tax on odd prices, rounded at subtotal'                 => [
			'items'             => [ [ '9.99', 1 ], [ '19.99', 2 ], [ '29.99', 1 ] ],
			'shipping'          => '12.49',
			'round_at_subtotal' => true,
			'us'                => true,
		],
		'US sales tax on odd prices, rounded per line'                    => [
			'items' => [ [ '9.99', 1 ], [ '19.99', 2 ], [ '29.99', 1 ] ],
			'us'    => true,
		],
	];

	protected function tearDown(): void {
		WC()->session->__unset( 'klarna_interoperability_data' );

		parent::tearDown();
	}

	/**
	 * @dataProvider provide_scenarios
	 */
	public function test_a_payment_session_adds_up( string $scenario ): void {
		$spec = $this->haveStoreFor( $scenario );
		$this->simulateCheckoutPage();
		// Detached so cart fixtures do not fire sessions of their own.
		remove_action( 'woocommerce_after_calculate_totals', [ KP_WC()->session, 'get_session' ], 999999 );
		$this->haveCartFor( $spec );
		$this->resetHttpInterception();

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$body = $this->klarnaRequestTo( '/payments/v1/sessions' )['json'];

		$this->assertSame( $this->minor( WC()->cart->get_total( 'edit' ) ), $body['order_amount'], 'The session amount is the cart total.' );
		$this->assertLinesAddUp( $body, $body['order_amount'], $spec );
	}

	/**
	 * @dataProvider provide_scenarios
	 */
	public function test_an_order_line_update_adds_up( string $scenario ): void {
		$spec  = $this->haveStoreFor( $scenario );
		$order = $this->haveOrderFor( $spec, [ 'status' => 'on-hold', 'paid' => true ] );

		$this->willRetrieveKlarnaOrder();
		$this->willAcceptTheUpdate();
		KP_WC()->order_management->update_klarna_order_items( $order->get_id(), [], false );

		$body = $this->klarnaRequestTo( '/authorization' )['json'];

		$this->assertSame( $this->minor( $order->get_total() ), $body['order_amount'], 'The updated amount is the order total.' );
		$this->assertLinesAddUp( $body, $body['order_amount'], $spec );
	}

	/**
	 * @dataProvider provide_scenarios
	 */
	public function test_a_capture_adds_up( string $scenario ): void {
		$spec  = $this->haveStoreFor( $scenario );
		$order = $this->haveOrderFor( $spec, [ 'status' => 'processing', 'paid' => true ] );

		$this->willRetrieveKlarnaOrder( [ 'remaining_authorized_amount' => $this->minor( $order->get_total() ) ] );
		$this->willCapture();
		KP_WC()->order_management->capture_klarna_order( $order->get_id() );

		$body = $this->klarnaRequestTo( '/captures' )['json'];

		$this->assertSame( $this->minor( $order->get_total() ), (int) $body['captured_amount'], 'The captured amount is the order total.' );
		$this->assertLinesAddUp( $body, (int) $body['captured_amount'], $spec );
	}

	/**
	 * @dataProvider provide_scenarios
	 */
	public function test_a_subscription_renewal_adds_up( string $scenario ): void {
		$spec   = $this->haveStoreFor( $scenario );
		$parent = $this->haveOrderFor( $spec, [] );

		$subscription = $this->haveSubscriptionFor( $parent );
		\KP_Subscription::save_recurring_token( $parent->get_id(), 'customer-token-1' );
		$renewal = $this->haveRenewalOrderFor( $this->reload( $subscription ), $this->orderArgsFor( $spec ) );
		$this->applyOrderCoupon( $renewal, $spec );

		$this->willRespondWith( [ 'order_id' => 'klarna-renewal-1', 'authorized_payment_method' => [ 'type' => 'invoice' ] ], 200, '/tokens/customer-token-1/order' );
		$this->willRetrieveKlarnaOrder( [ 'order_id' => 'klarna-renewal-1' ] );

		ob_start();
		( new \KP_Subscription() )->process_scheduled_payment( $renewal->get_total(), $renewal );
		ob_end_clean();

		$body = $this->klarnaRequestTo( '/tokens/customer-token-1/order' )['json'];

		$this->assertSame( $this->minor( $renewal->get_total() ), $body['order_amount'], 'The renewal is charged the order total.' );
		$this->assertLinesAddUp( $body, $body['order_amount'], $spec );
	}

	/**
	 * The return fee row is ClickUp 869c9x3cx: Klarna answered REFUND_NOT_ALLOWED,
	 * "Sum of order line totals does not match expected amount".
	 *
	 * @dataProvider provide_refunds
	 */
	public function test_a_refund_adds_up( string $scenario, string $refund, bool $return_fee ): void {
		$spec  = $this->haveStoreFor( $scenario );
		$order = $this->haveOrderFor( $spec, [ 'status' => 'processing', 'paid' => true ] );
		$order->update_meta_data( '_wc_klarna_capture_id', 'capture-123' );
		$order->save();

		if ( 'one unit' === $refund ) {
			$item_id    = array_key_first( $order->get_items() );
			$wc_refund = $this->haveRefundForItems( $order, [ $item_id ], '', [ $item_id => 1 ] );
		} else {
			$wc_refund = $this->haveRefundForItems( $order );
		}

		// 10.00 + 25% VAT, posted from the refund screen and parsed by get_return_fee_from_post(), which needs a real request.
		$fee  = $return_fee ? [ 'amount' => '10.00', 'tax_amount' => '2.50', 'tax_rate_id' => $spec['standard_rate_id'] ] : [];
		$args = [
			'order_id'      => $order->get_id(),
			'refund_amount' => (float) $wc_refund->get_amount() - ( $return_fee ? 12.50 : 0 ),
			'refund_reason' => '',
			'refund_id'     => $wc_refund->get_id(),
		];
		if ( $return_fee ) {
			$args['return_fee'] = $fee;
		}

		$this->willRespondWith( [ 'refund_id' => 'refund-1' ], 201, '/refunds' );
		( new RequestPostRefund( KP_WC()->order_management, $args ) )->request();

		$body = $this->klarnaRequestTo( '/refunds' )['json'];

		$this->assertSame(
			(int) $body['refunded_amount'],
			(int) array_sum( array_column( $body['order_lines'], 'total_amount' ) ),
			'Klarna rejects a refund whose order lines do not sum to the refunded amount.'
		);
	}

	/**
	 * @dataProvider provide_rounding_rules
	 */
	public function test_a_rounding_difference_is_moved_onto_the_lines( array $lines, int $total, ?int $total_tax, array $expected ): void {
		$this->assertSame( $expected, \KP_Order_Data::adjust_rounding( $lines, $total, $total_tax ) );
	}

	/**
	 * @dataProvider provide_tax_rate_rules
	 */
	public function test_a_line_gets_the_tax_rate_its_tax_amount_implies( array $line, array $expected ): void {
		$this->assertSame( [ $expected ], \KP_Order_Data::align_tax_rates( [ $line ] ) );
	}

	/** @return array<string, array{array, array}> */
	public static function provide_tax_rate_rules(): array {
		return [
			'a line that matches its rate is left alone'          => [ self::taxed( 10000, 2000, 2500 ), self::taxed( 10000, 2000, 2500 ) ],
			'compound tax gets the rate it adds up to'            => [ self::taxed( 11233, 1235, 1200 ), self::taxed( 11233, 1235, 1235 ) ],
			'a negative fee gets the rate its tax implies'        => [ self::taxed( -1185, -185, 2500 ), self::taxed( -1185, -185, 1850 ) ],
			'one minor unit past the closest rate gives it up'    => [ self::taxed( 37350, 7500, 2500 ), self::taxed( 37350, 7501, 2513 ) ],
			'a large line no rate can describe keeps its tax'     => [ self::taxed( 1000000, 72141, 778 ), self::taxed( 1000000, 72141, 777 ) ],
		];
	}

	private static function taxed( int $total, int $tax, int $rate ): array {
		return [ 'tax_rate' => $rate, 'total_amount' => $total, 'total_tax_amount' => $tax ];
	}

	/** @return array<string, array{string}> */
	public static function provide_scenarios(): array {
		$rows = [];
		foreach ( array_keys( self::SCENARIOS ) as $name ) {
			$rows[ $name ] = [ $name ];
		}

		return $rows;
	}

	/** @return array<string, array{string, string, bool}> */
	public static function provide_refunds(): array {
		return [
			'six at 16.99, everything, return fee'                => [ 'six at 16.99 with shipping', 'everything', true ],
			'six at 16.99, everything'                            => [ 'six at 16.99 with shipping', 'everything', false ],
			'six at 16.99, one unit'                              => [ 'six at 16.99 with shipping', 'one unit', false ],
			'six at 16.99 rounded at subtotal, everything'        => [ 'six at 16.99 with shipping, rounded at subtotal', 'everything', false ],
			'half-cent VAT added, everything'                     => [ 'half-cent VAT on three lines, VAT added, rounded per line', 'everything', false ],
			'half-cent VAT included, everything, return fee'      => [ 'half-cent VAT on three lines, VAT included, rounded per line', 'everything', true ],
			'three VAT rates, everything, return fee'             => [ 'VAT at 25, 12 and 6 percent, rounded at subtotal', 'everything', true ],
			'a 15 percent coupon, everything'                     => [ 'a 15 percent coupon over odd prices', 'everything', false ],
			'a 15 percent coupon, one unit'                       => [ 'a 15 percent coupon over odd prices', 'one unit', false ],
			'odd shipping and a taxable fee, everything'          => [ 'odd shipping and a taxable fee', 'everything', false ],
			'twenty cheap lines, everything'                      => [ 'twenty cheap lines, rounded at subtotal', 'everything', false ],
			'US sales tax, everything'                            => [ 'US sales tax on odd prices, rounded at subtotal', 'everything', false ],
		];
	}

	/** @return array<string, array{array, int, ?int, array}> */
	public static function provide_rounding_rules(): array {
		$big   = self::line( 1, 10000, 0, 10000, 2000 );
		$small = self::line( 1, 1000, 0, 1000, 200 );

		return [
			'lines that already add up'               => [ [ $big, $small ], 11000, 2200, [ $big, $small ] ],
			'one too few, onto the largest line'      => [ [ $big, $small ], 11001, 2200, [ self::line( 1, 10001, 0, 10001, 2000 ), $small ] ],
			'one too many, as a discount'             => [ [ $big, $small ], 10999, 2200, [ self::line( 1, 10000, 1, 9999, 2000 ), $small ] ],
			'a discounted line gives back discount'   => [
				[ self::line( 2, 5000, 10, 9990, 1998 ) ],
				9991,
				1998,
				[ self::line( 2, 5000, 9, 9991, 1998 ) ],
			],
			'two units with no discount are skipped'  => [
				[ self::line( 2, 5000, 0, 10000, 2000 ), $small ],
				11001,
				2200,
				[ self::line( 2, 5000, 0, 10000, 2000 ), self::line( 1, 1001, 0, 1001, 200 ) ],
			],
			'a tax difference moves only the tax'     => [ [ $big, $small ], 11000, 2199, [ self::line( 1, 10000, 0, 10000, 1999 ), $small ] ],
			'more than a cent per line is left alone' => [ [ $big, $small ], 11003, 2200, [ $big, $small ] ],
			'a tax mismatch blocks the amount too'    => [ [ $big, $small ], 11001, 2205, [ $big, $small ] ],
			'a gift card covering everything'         => [
				[ $big, self::line( 1, -9999, 0, -9999, 0, 'gift_card' ) ],
				0,
				2000,
				[ self::line( 1, 10000, 1, 9999, 2000 ), self::line( 1, -9999, 0, -9999, 0, 'gift_card' ) ],
			],
			'sales tax and gift cards are not used'   => [
				[ self::line( 1, -500, 0, -500, 0, 'gift_card' ), self::line( 1, 800, 0, 800, 0, 'sales_tax' ), self::line( 1, 1000, 0, 1000, 0 ) ],
				1301,
				null,
				[ self::line( 1, -500, 0, -500, 0, 'gift_card' ), self::line( 1, 800, 0, 800, 0, 'sales_tax' ), self::line( 1, 1001, 0, 1001, 0 ) ],
			],
		];
	}

	private static function line( int $quantity, int $unit_price, int $discount, int $total, int $tax, string $type = 'physical' ): array {
		return [
			'quantity'              => $quantity,
			'tax_rate'              => $tax > 0 ? 2500 : 0,
			'total_amount'          => $total,
			'total_discount_amount' => $discount,
			'total_tax_amount'      => $tax,
			'type'                  => $type,
			'unit_price'            => $unit_price,
		];
	}

	/** Configures the store, tax rates, credentials and customer for a scenario, and returns the scenario with its tax class slugs resolved. */
	private function haveStoreFor( string $scenario ): array {
		$spec = array_merge(
			[ 'include_tax' => false, 'round_at_subtotal' => false, 'shipping' => null, 'fee' => null, 'coupon' => null, 'us' => false ],
			self::SCENARIOS[ $scenario ]
		);

		$this->deleteAllTaxRates();
		$flags = [ 'prices_include_tax' => $spec['include_tax'], 'round_at_subtotal' => $spec['round_at_subtotal'] ];

		if ( $spec['us'] ) {
			$spec['standard_rate_id'] = $this->configureUsStore();
			$this->configureStore( array_merge( [ 'country' => 'US:CA', 'currency' => 'USD' ], $flags ) );
			$this->haveKlarnaCredentials( 'us' );
			$spec['address'] = $this->usAddress();
		} else {
			$spec['standard_rate_id'] = $this->configureSwedishStore();
			$this->configureStore( $flags );
			$this->haveKlarnaCredentials( 'se' );
			$spec['address'] = $this->swedishAddress();
		}

		$this->reloadPaymentGateways();
		$this->haveCustomerAddress( $spec['address'], $spec['address'] );

		$classes = [ 'standard' => '' ];
		foreach ( $spec['items'] as $item ) {
			$rate = $item[2] ?? 'standard';
			if ( ! isset( $classes[ $rate ] ) && 'none' !== $rate ) {
				$classes[ $rate ] = $this->haveTaxClass( "Reduced rate {$rate}", "{$rate}.0000" );
			}
		}

		$spec['products'] = [];
		foreach ( $spec['items'] as $item ) {
			$rate               = $item[2] ?? 'standard';
			$spec['products'][] = [
				$this->haveSimpleProduct(
					[
						'price'      => $item[0],
						'tax_status' => 'none' === $rate ? 'none' : 'taxable',
						'tax_class'  => $classes[ $rate ] ?? '',
					]
				),
				$item[1],
			];
		}

		return $spec;
	}

	private function haveCartFor( array $spec ): void {
		$this->haveCartWith( $spec['products'] );

		if ( $spec['fee'] ) {
			$this->haveCartFee( 'Handling fee', (float) $spec['fee'], true );
		}

		if ( $spec['shipping'] ) {
			$this->haveChosenFlatRateShipping( $spec['us'] ? 'US' : 'SE', $spec['shipping'] );
		}

		if ( $spec['coupon'] ) {
			WC()->cart->apply_coupon( $this->haveScenarioCoupon( $spec['coupon'] ) );
		}

		$this->recalculateCart();
	}

	private function haveOrderFor( array $spec, array $overrides ): \WC_Order {
		$klarna = $spec['us'] ? [ 'country' => 'US' ] : true;
		$order  = $this->haveOrder( array_merge( $this->orderArgsFor( $spec ), [ 'klarna' => $klarna ], $overrides ) );
		$this->applyOrderCoupon( $order, $spec );

		return $order;
	}

	private function orderArgsFor( array $spec ): array {
		return [
			'items'            => $spec['products'],
			'shipping'         => $spec['shipping'] ? [ 'total' => $spec['shipping'] ] : null,
			'fees'             => $spec['fee'] ? [ [ 'total' => $spec['fee'] ] ] : [],
			'billing'          => $spec['address'],
			'shipping_address' => $spec['address'],
			'currency'         => $spec['us'] ? 'USD' : 'SEK',
		];
	}

	private function applyOrderCoupon( \WC_Order $order, array $spec ): void {
		if ( ! $spec['coupon'] ) {
			return;
		}

		$order->apply_coupon( $this->haveScenarioCoupon( $spec['coupon'] ) );
		$order->save();
	}

	/** Creates a coupon of the given `[ type, amount ]` and returns its code. */
	private function haveScenarioCoupon( array $coupon ): string {
		static $sequence = 0;
		++$sequence;

		return $this->haveCoupon( "rounding-{$sequence}", $coupon[0], (float) $coupon[1] );
	}

	private function assertLinesAddUp( array $body, int $amount, array $spec ): void {
		$lines = $body['order_lines'];

		$this->assertSame(
			$amount,
			(int) array_sum( array_column( $lines, 'total_amount' ) ),
			'Klarna rejects a request whose order lines do not sum to its amount.'
		);

		if ( ! isset( $body['order_tax_amount'] ) ) {
			return;
		}

		// With separate sales tax the lines carry no tax and the sales tax line holds all of it.
		$line_tax = $spec['us']
			? (int) array_sum( array_column( wp_list_filter( $lines, [ 'type' => 'sales_tax' ] ), 'total_amount' ) )
			: (int) array_sum( array_column( $lines, 'total_tax_amount' ) );

		$this->assertSame( $body['order_tax_amount'], $line_tax, 'Klarna rejects a request whose order lines do not sum to its tax amount.' );
	}

	private function minor( $amount ): int {
		return (int) round( (float) $amount * 100 );
	}
}
