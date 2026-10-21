<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\ExpressCheckout\OneStepCheckout;
use Tests\Support\IntegrationTestCase;

/**
 * How WooCommerce amounts survive the trip to Klarna minor units, across prices, taxes, discounts, shipping and fees.
 *
 * @covers \KP_Order_Data
 * @covers \Krokedil\Klarna\ExpressCheckout\OneStepCheckout
 * @covers \Krokedil\Klarna\OrderManagement\Request\Post\RequestPostCapture
 * @covers \Krokedil\Klarna\OrderManagement\Request\Post\RequestPostRefund
 */
class AmountRoundingTest extends IntegrationTestCase {

	protected ?string $storeProfile = 'se';

	/** Tax classes every basket can use, next to the 25% standard rate the profile sets up. */
	private const TAX_CLASSES = [
		'Reduced 12' => '12.0000',
		'Reduced 6'  => '6.0000',
		'Zero rate'  => '0.0000',
	];

	/** The baskets pinned as whole request bodies, so a reviewer sees the actual numbers. */
	private const SNAPSHOT_BASKETS = [
		'one item, prices excl. tax',
		'one item, prices incl. tax',
		'mixed vat rates',
		'flat rate shipping',
		'percent coupon',
		'fixed cart coupon',
		'taxable cart fee',
		'us sales tax',
	];

	protected function setUp(): void {
		parent::setUp();

		foreach ( self::TAX_CLASSES as $name => $rate ) {
			$this->haveTaxClass( $name, $rate );
		}

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		$this->reloadPaymentGateways();
		$this->simulateCheckoutPage();

		// Detached so cart fixtures do not fire sessions of their own.
		remove_action( 'woocommerce_after_calculate_totals', [ KP_WC()->session, 'get_session' ], 999999 );

		$this->resetHttpInterception();
	}

	protected function tearDown(): void {
		WC()->session->__unset( 'kec_one_step_unique_id' );
		WC()->session->__unset( 'kec_one_step_order_id' );

		parent::tearDown();
	}

	/** @dataProvider provide_baskets */
	public function test_the_session_amounts_follow_klarnas_rules( array $basket ): void {
		$this->arrangeCart( $basket );

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$this->assertKlarnaAmountsAreConsistent(
			$this->sessionBody(),
			(float) WC()->cart->get_total( 'edit' ),
			(float) WC()->cart->get_total_tax()
		);
	}

	/** @dataProvider provide_baskets */
	public function test_the_order_amounts_follow_klarnas_rules( array $basket ): void {
		if ( ! empty( $basket['cart_only'] ) ) {
			$this->markTestSkipped( $basket['cart_only'] );
		}

		$order = $this->arrangeOrder( $basket );

		$body = ( new \KP_Order_Data( 'b2c', $order->get_id() ) )->get_klarna_order_object();

		$this->assertKlarnaAmountsAreConsistent( $body, (float) $order->get_total(), (float) $order->get_total_tax() );
		$this->assertNoKlarnaRequests();
	}

	/** @dataProvider provide_snapshot_baskets */
	public function test_the_common_baskets_match_the_snapshot( string $name ): void {
		$this->arrangeCart( $this->provide_baskets()[ $name ][0] );

		$this->willCreateSession();
		KP_WC()->session->get_session();

		$this->assertRequestMatchesSnapshot(
			$this->klarnaRequestTo( '/payments/v1/sessions' ),
			'rounding-' . sanitize_title( $name ),
			$this->productIdPlaceholders()
		);
	}

	/** @return array<string, array{0: string}> */
	public function provide_snapshot_baskets(): array {
		return array_combine( self::SNAPSHOT_BASKETS, array_map( static fn( $name ) => [ $name ], self::SNAPSHOT_BASKETS ) );
	}

	/**
	 * The one-step Express Checkout builds its own amounts instead of going through KP_Order_Data.
	 *
	 * @dataProvider provide_baskets
	 */
	public function test_the_kec_one_step_amount_matches_the_cart( array $basket ): void {
		if ( ! empty( $basket['one_step'] ) ) {
			$this->markTestSkipped( $basket['one_step'] );
		}

		$rate_id = $this->arrangeCart( $basket );

		if ( null === $rate_id ) {
			$body               = OneStepCheckout::get_initiate_body();
			$body['lineItems'] = $body['supplementaryPurchaseData']['lineItems'];
		} else {
			$body = OneStepCheckout::get_changed_shipping_option_response( $rate_id );
		}

		$line_total = array_sum( array_column( $body['lineItems'], 'totalAmount' ) );
		$wc_total   = $this->toMinor( WC()->cart->get_total( 'edit' ) );

		$this->assertSame(
			[ 'line items add up to' => $body['amount'], 'WooCommerce charges' => $body['amount'] ],
			[ 'line items add up to' => $line_total, 'WooCommerce charges' => $wc_total ],
			'The one-step amount has to equal both its own line items and what WooCommerce will charge. Lines sent: ' . wp_json_encode( $body['lineItems'] )
		);
		$this->assertNoKlarnaRequests();
	}

	/** @dataProvider provide_baskets */
	public function test_a_capture_charges_the_order_total( array $basket ): void {
		if ( ! empty( $basket['cart_only'] ) ) {
			$this->markTestSkipped( $basket['cart_only'] );
		}

		$order = $this->arrangeKlarnaOrder( $basket, [ 'paid' => true, 'status' => 'processing' ] );

		$this->willRetrieveKlarnaOrder( [ 'purchase_currency' => $order->get_currency(), 'remaining_authorized_amount' => $this->toMinor( $order->get_total() ) ] );
		$this->willCapture();
		KP_WC()->order_management->capture_klarna_order( $order->get_id() );

		$body = $this->klarnaRequestTo( '/captures' )['json'];

		$this->assertSame( $this->toMinor( $order->get_total() ), $body['captured_amount'], 'The capture has to match what WooCommerce charged.' );
		$this->assertOrderLinesAddUpTo( $body['order_lines'] ?? [], $body['captured_amount'], 'captured_amount' );
	}

	/**
	 * Refunds build their lines with their own arithmetic instead of KP_Order_Data.
	 *
	 * @dataProvider provide_refunds
	 */
	public function test_a_refund_adds_up( string $basket_name, string $refund ): void {
		$order = $this->arrangeKlarnaOrder( $this->provide_baskets()[ $basket_name ][0], [ 'paid' => true, 'status' => 'completed' ] );
		$order->update_meta_data( '_wc_klarna_capture_id', 'capture-123' );
		$order->save();

		$wc_refund = $this->haveRefund( $order, $refund );

		$this->willRetrieveKlarnaOrder( [ 'status' => 'CAPTURED', 'purchase_currency' => $order->get_currency() ] );
		$this->willRespondWith( [ 'refund_id' => 'refund-1' ], 201, '/refunds' );
		KP_WC()->order_management->refund_klarna_order( false, $order->get_id(), $wc_refund->get_amount(), '' );

		$body = $this->klarnaRequestTo( '/refunds' )['json'];

		$this->assertSame( $this->toMinor( $wc_refund->get_amount() ), $body['refunded_amount'], 'The refund has to match what WooCommerce refunded.' );

		if ( isset( $body['order_lines'] ) ) {
			$this->assertOrderLinesAddUpTo( $body['order_lines'], $body['refunded_amount'], 'refunded_amount', true );
		}
	}

	/** @return array<string, array{0: string, 1: string}> */
	public function provide_refunds(): array {
		return [
			'everything, six of one item'            => [ 'six of one item with shipping', 'everything' ],
			'one unit of three at an odd price'      => [ 'percent coupon on odd prices', 'one-unit' ],
			'one unit of three, incl. tax'           => [ 'incl. tax .99 pricing', 'one-unit' ],
			'everything, mixed vat rates'            => [ 'mixed vat rates', 'everything' ],
			'one unit, round at subtotal'            => [ 'mixed rates, incl. tax, round at subtotal', 'one-unit' ],
			'everything, fixed cart coupon split'    => [ 'fixed cart coupon split across mixed rates', 'everything' ],
			'shipping only'                          => [ 'flat rate shipping', 'shipping' ],
			'shipping at the reduced rate only'      => [ 'shipping 9.99 at the reduced rate', 'shipping' ],
			'fee only'                               => [ 'fee with odd amount', 'fee' ],
			'an amount with no lines'                => [ 'one item, prices excl. tax', 'amount' ],
			'everything, us sales tax'               => [ 'us sales tax', 'everything' ],
			'one unit, us rate with three decimals'  => [ 'us rate with three decimals', 'one-unit' ],
		];
	}

	/**
	 * Each basket is a plain spec, so the cart, order and capture tests all run the same matrix.
	 * Rates are SE 25% standard, plus the reduced-12, reduced-6 and zero-rate classes.
	 *
	 * @return array<string, array{0: array}>
	 */
	public function provide_baskets(): array {
		$baskets = [
			// Common baskets.
			'one item, prices excl. tax'                  => [ 'items' => [ [ '100.00' ] ] ],
			'one item, prices incl. tax'                  => [ 'store' => [ 'prices_include_tax' => true ], 'items' => [ [ '125.00' ] ] ],
			'three of one item'                           => [ 'items' => [ [ '59.50', 3 ] ] ],
			'six of one item with shipping'               => [ 'items' => [ [ '16.99', 6 ] ], 'shipping' => [ 'cost' => '49.00' ] ],
			'mixed vat rates'                             => [ 'items' => [ [ '100.00' ], [ '100.00', 1, 'reduced-12' ], [ '100.00', 1, 'reduced-6' ], [ '100.00', 1, 'zero-rate' ] ] ],
			'zero-rated item only'                        => [ 'items' => [ [ '199.00', 1, 'zero-rate' ] ] ],
			'flat rate shipping'                          => [ 'items' => [ [ '100.00' ] ], 'shipping' => [ 'cost' => '49.00' ] ],
			'shipping that costs nothing'                 => [ 'items' => [ [ '100.00' ] ], 'shipping' => [ 'cost' => '0.00' ] ],
			'percent coupon'                              => [ 'items' => [ [ '100.00', 2 ] ], 'coupons' => [ [ 'percent', 10 ] ] ],
			'fixed cart coupon'                           => [ 'items' => [ [ '100.00', 2 ] ], 'coupons' => [ [ 'fixed_cart', 50 ] ] ],
			'fixed product coupon'                        => [ 'items' => [ [ '100.00', 2 ] ], 'coupons' => [ [ 'fixed_product', 15 ] ] ],
			'taxable cart fee'                            => [ 'items' => [ [ '100.00' ] ], 'fees' => [ [ 25.00, true ] ] ],
			'us sales tax'                                => [ 'profile' => 'us', 'items' => [ [ '100.00', 2 ] ], 'shipping' => [ 'cost' => '10.00' ] ],
			'store without tax'                           => [ 'profile' => 'se-no-tax', 'items' => [ [ '99.99', 3 ] ] ],
			'variable product variation'                  => [ 'items' => [ [ '149.90', 2, '', 'variation' ] ] ],

			// Prices and tax.
			'incl. tax .99 pricing'                       => [ 'store' => [ 'prices_include_tax' => true ], 'items' => [ [ '99.99', 3 ] ] ],
			'one öre item'                                => [ 'items' => [ [ '0.01' ] ] ],
			'one öre item, prices incl. tax'              => [ 'store' => [ 'prices_include_tax' => true ], 'items' => [ [ '0.01', 3 ] ] ],
			'price stored with four decimals'             => [ 'items' => [ [ '79.9920', 3 ] ] ],
			'many .99 items, excl. tax'                   => [ 'items' => array_fill( 0, 10, [ '9.99' ] ) ],
			'many .99 items, incl. tax'                   => [ 'store' => [ 'prices_include_tax' => true ], 'items' => array_fill( 0, 10, [ '9.99' ] ) ],
			'many .99 items, excl. tax, round at subtotal' => [ 'store' => [ 'round_at_subtotal' => true ], 'items' => array_fill( 0, 10, [ '9.99' ] ) ],
			'many .99 items, incl. tax, round at subtotal' => [ 'store' => [ 'prices_include_tax' => true, 'round_at_subtotal' => true ], 'items' => array_fill( 0, 10, [ '9.99' ] ) ],
			'mixed rates, incl. tax, round at subtotal'   => [ 'store' => [ 'prices_include_tax' => true, 'round_at_subtotal' => true ], 'items' => [ [ '19.99', 3 ], [ '7.49', 3, 'reduced-12' ], [ '3.33', 7, 'reduced-6' ] ] ],
			'high quantity cheap item'                    => [ 'items' => [ [ '0.33', 999, 'reduced-12' ] ] ],
			'float traps'                                 => [ 'items' => [ [ '0.29' ], [ '1.15' ], [ '4.35' ], [ '19.99' ], [ '8.17' ] ] ],
			'price with three decimals'                   => [ 'items' => [ [ '1.005' ], [ '19.99' ] ] ],
			'float traps, incl. tax'                      => [ 'store' => [ 'prices_include_tax' => true ], 'items' => [ [ '0.29' ], [ '1.15' ], [ '4.35' ], [ '19.99' ], [ '8.17' ] ] ],
			'non-integer tax rate'                        => [ 'rates' => [ [ 'Odd rate', '7.7000' ] ], 'items' => [ [ '33.33', 3, 'odd-rate' ] ] ],
			'us rate with three decimals'                 => [ 'profile' => 'us', 'us_rate' => '8.8750', 'items' => [ [ '19.99', 3 ], [ '0.99' ] ] ],
			'compound tax rates'                          => [ 'rates' => [ [ 'Stacked', '5.0000', [ '7.0000' ] ] ], 'items' => [ [ '49.99', 2, 'stacked' ] ] ],
			'store rounds to whole kronor'                => [ 'store' => [ 'price_decimals' => 0 ], 'items' => [ [ '99.50', 3 ], [ '12.40', 1, 'reduced-12' ] ] ],
			'store rounds to whole kronor, incl. tax'     => [ 'store' => [ 'price_decimals' => 0, 'prices_include_tax' => true ], 'items' => [ [ '99.50', 3 ], [ '12.40', 1, 'reduced-12' ] ] ],

			// Discounts.
			'percent coupon leaves half an öre'           => [ 'items' => [ [ '19.99' ] ], 'coupons' => [ [ 'percent', 12.5 ] ] ],
			'percent coupon leaves half an öre, incl. tax' => [ 'store' => [ 'prices_include_tax' => true ], 'items' => [ [ '19.99' ] ], 'coupons' => [ [ 'percent', 12.5 ] ] ],
			'percent coupon on odd prices'                => [ 'items' => [ [ '33.33', 3 ] ], 'coupons' => [ [ 'percent', 10 ] ] ],
			'fixed cart coupon split across mixed rates'  => [ 'items' => [ [ '33.33' ], [ '33.33', 1, 'reduced-12' ], [ '33.33', 1, 'reduced-6' ] ], 'coupons' => [ [ 'fixed_cart', 10 ] ] ],
			'fixed cart coupon split, incl. tax'          => [ 'store' => [ 'prices_include_tax' => true ], 'items' => [ [ '33.33' ], [ '33.33', 1, 'reduced-12' ], [ '33.33', 1, 'reduced-6' ] ], 'coupons' => [ [ 'fixed_cart', 10 ] ] ],
			'fixed product coupon on odd quantity'        => [ 'items' => [ [ '9.99', 7 ] ], 'coupons' => [ [ 'fixed_product', 1.11 ] ] ],
			'stacked coupons'                             => [ 'items' => [ [ '79.90', 2 ], [ '24.95', 3, 'reduced-12' ] ], 'coupons' => [ [ 'percent', 15 ], [ 'fixed_cart', 20 ] ] ],
			'coupon limited to one item'                  => [ 'items' => [ [ '49.99', 3 ] ], 'coupons' => [ [ 'percent', 33, [ 'limit_usage_to_x_items' => 1 ] ] ] ],
			'hundred percent coupon'                      => [ 'items' => [ [ '100.00', 2 ] ], 'coupons' => [ [ 'percent', 100 ] ] ],
			'hundred percent coupon with shipping'        => [ 'items' => [ [ '100.00', 2 ] ], 'coupons' => [ [ 'percent', 100 ] ], 'shipping' => [ 'cost' => '49.00' ] ],
			'coupon larger than the cart'                 => [ 'items' => [ [ '100.00', 3 ] ], 'coupons' => [ [ 'fixed_cart', 500 ] ] ],
			'percent coupon with taxed shipping'          => [ 'items' => [ [ '33.33', 3 ] ], 'coupons' => [ [ 'percent', 10 ] ], 'shipping' => [ 'cost' => '39.20' ] ],
			'partial gift card'                           => [ 'items' => [ [ '100.00', 2 ] ], 'gift_card' => 33.33, 'cart_only' => 'Gift cards are applied to the cart.' ],

			// Shipping and fees.
			'shipping that is 39.00 incl. vat'            => [ 'items' => [ [ '100.00' ] ], 'shipping' => [ 'cost' => '31.20' ] ],
			'shipping 9.99 at the reduced rate'           => [ 'store' => [ 'shipping_tax_class' => 'reduced-12' ], 'items' => [ [ '100.00' ] ], 'shipping' => [ 'cost' => '9.99' ] ],
			'shipping not taxable'                        => [ 'items' => [ [ '100.00' ] ], 'shipping' => [ 'cost' => '49.00', 'tax_status' => 'none' ], 'cart_only' => 'An order shipping line has no tax status of its own.' ],
			'shipping inherits the reduced rate'          => [ 'items' => [ [ '100.00', 1, 'reduced-12' ] ], 'shipping' => [ 'cost' => '49.00' ] ],
			'shipping over mixed item rates'              => [ 'items' => [ [ '100.00', 1, 'reduced-6' ], [ '100.00', 1, 'reduced-12' ] ], 'shipping' => [ 'cost' => '49.00' ] ],
			'two shipping packages'                       => [
				'items'    => [ [ '100.00' ], [ '50.00', 1, 'reduced-12' ] ],
				'shipping' => [ 'cost' => '29.00', 'packages' => 2 ],
				'pending'  => 'Needs the multi-package shipping lines from krokedil/woocommerce develop-multi-package-shipping, which is not released yet.',
				'one_step' => 'The one-step checkout lets the shopper choose one shipping option, not one per package.',
			],
			'two packages, one shipped untaxed'           => [
				'items'     => [ [ '100.00' ], [ '50.00', 1, 'reduced-12' ] ],
				'shipping'  => [ 'methods' => [ [ '29.00', 'taxable' ], [ '19.00', 'none' ] ] ],
				'cart_only' => 'An order shipping line has no tax status of its own.',
				'one_step'  => 'The one-step checkout lets the shopper choose one shipping option, not one per package.',
				'pending'   => 'Needs the multi-package shipping lines from krokedil/woocommerce develop-multi-package-shipping, which is not released yet.',
			],
			'non-taxable fee'                             => [ 'items' => [ [ '100.00' ] ], 'fees' => [ [ 25.00, false ] ] ],
			'fee with odd amount'                         => [ 'items' => [ [ '100.00' ] ], 'fees' => [ [ 3.33, true ] ] ],
			'fee at the reduced rate'                     => [ 'items' => [ [ '100.00' ] ], 'fees' => [ [ 9.99, true, 'reduced-12' ] ] ],
			'negative fee across mixed rates'             => [ 'items' => [ [ '100.00' ], [ '100.00', 1, 'reduced-12' ] ], 'fees' => [ [ -10.00, true ] ] ],
			'everything at once'                          => [
				'store'    => [ 'prices_include_tax' => true ],
				'items'    => [ [ '19.99', 3 ], [ '7.49', 2, 'reduced-12' ], [ '3.33', 1, 'reduced-6' ], [ '0.29', 11, 'zero-rate' ] ],
				'coupons'  => [ [ 'percent', 7 ] ],
				'fees'     => [ [ 4.99, true ] ],
				'shipping' => [ 'cost' => '39.20' ],
			],
		];

		return array_map( static fn( $basket ) => [ $basket ], $baskets );
	}

	/**
	 * Fills the cart from a basket spec and returns the chosen shipping rate, if any.
	 */
	private function arrangeCart( array $basket ): ?string {
		$this->arrangeStore( $basket );

		$items = [];
		foreach ( $this->haveBasketProducts( $basket ) as list( $product, $quantity ) ) {
			$items[] = [ $product, $quantity ];
		}
		$this->haveCartWith( $items );

		foreach ( $basket['fees'] ?? [] as $index => $fee ) {
			$this->haveCartFee( "Fee {$index}", $fee[0], $fee[1], $fee[2] ?? '' );
		}

		foreach ( $basket['coupons'] ?? [] as $index => $coupon ) {
			$this->haveAppliedCoupon( "rounding-{$index}", $coupon[0], (float) $coupon[1], $coupon[2] ?? [] );
		}

		if ( isset( $basket['gift_card'] ) ) {
			$amount = $basket['gift_card'];

			// The PW Gift Cards integration reads the card from the session; the total is lowered the way the plugin itself would.
			WC()->session->set( 'pw-gift-card-data', [ 'gift_cards' => [ 'KP-GIFT-1' => $amount ] ] );
			add_filter( 'woocommerce_calculated_total', static fn( $total ) => $total - $amount );
		}

		$rate_id = null;

		if ( isset( $basket['shipping']['methods'] ) ) {
			$rate_ids = $this->haveFlatRateMethods( $this->basketCountry( $basket ), $basket['shipping']['methods'] );
			$rate_id  = $rate_ids[0];
			$this->haveOnePackagePerCartItem( $rate_ids );
		} elseif ( isset( $basket['shipping'] ) ) {
			$rate_id = $this->haveChosenFlatRateShipping( $this->basketCountry( $basket ), $basket['shipping']['cost'], $basket['shipping']['tax_status'] ?? 'taxable' );

			if ( ( $basket['shipping']['packages'] ?? 1 ) > 1 ) {
				$this->haveOnePackagePerCartItem( $rate_id );
			}
		}

		$this->recalculateCart();
		$this->resetHttpInterception();

		return $rate_id;
	}

	/** Builds a saved order from a basket spec, with $order_args passed through to haveOrder(). */
	private function arrangeOrder( array $basket, array $order_args = [] ): \WC_Order {
		$this->arrangeStore( $basket );

		$coupons = [];
		foreach ( $basket['coupons'] ?? [] as $index => $coupon ) {
			$coupons[] = $this->haveCoupon( "rounding-{$index}", $coupon[0], (float) $coupon[1], $coupon[2] ?? [] );
		}

		$shipping = null;
		if ( isset( $basket['shipping'] ) ) {
			$shipping = array_fill( 0, $basket['shipping']['packages'] ?? 1, [ 'total' => $basket['shipping']['cost'] ] );
		}

		$address = 'US' === $this->basketCountry( $basket ) ? $this->usAddress() : $this->swedishAddress();

		$order = $this->haveOrder(
			$order_args + [
				'items'            => $this->haveBasketProducts( $basket ),
				'shipping'         => $shipping,
				'fees'             => array_map(
					static fn( $fee, $index ) => [
						'name'       => "Fee {$index}",
						'total'      => (string) $fee[0],
						'tax_status' => $fee[1] ? 'taxable' : 'none',
						'tax_class'  => $fee[2] ?? '',
					],
					$basket['fees'] ?? [],
					array_keys( $basket['fees'] ?? [] )
				),
				'coupons'          => $coupons,
				'billing'          => $address,
				'shipping_address' => $address,
				'currency'         => get_woocommerce_currency(),
			]
		);

		$this->resetHttpInterception();

		return $this->reload( $order );
	}

	/** An order from a basket spec that went through Klarna. */
	private function arrangeKlarnaOrder( array $basket, array $order_args ): \WC_Order {
		return $this->arrangeOrder( $basket, $order_args + [ 'klarna' => [ 'country' => $this->basketCountry( $basket ) ] ] );
	}

	/** Refunds part of an order the way wp-admin does: 'one-unit', 'everything', 'shipping', 'fee' or 'amount'. */
	private function haveRefund( \WC_Order $order, string $refund ): \WC_Order_Refund {
		switch ( $refund ) {
			case 'one-unit':
				$item_id = array_key_first( $order->get_items() );
				return $this->haveRefundForItems( $order, [ $item_id ], '', [ $item_id => 1 ] );
			case 'shipping':
				return $this->haveRefundForItems( $order, array_keys( $order->get_items( 'shipping' ) ) );
			case 'fee':
				return $this->haveRefundForItems( $order, array_keys( $order->get_items( 'fee' ) ) );
			case 'amount':
				return wc_create_refund( [ 'order_id' => $order->get_id(), 'amount' => '10.01' ] );
			default:
				return $this->haveRefundForItems( $order );
		}
	}

	/** Applies the basket's store profile, options and extra tax rates. */
	private function arrangeStore( array $basket ): void {
		if ( ! empty( $basket['pending'] ) ) {
			$this->markTestSkipped( $basket['pending'] );
		}

		$store   = $basket['store'] ?? [];
		$profile = $basket['profile'] ?? 'se';

		if ( 'us' === $profile ) {
			$this->deleteAllTaxRates();
			$this->configureStore( array_merge( [ 'country' => 'US:CA', 'currency' => 'USD' ], $store ) );
			$this->haveTaxRate(
				[
					'tax_rate_country' => 'US',
					'tax_rate_state'   => 'CA',
					'tax_rate'         => $basket['us_rate'] ?? '8.5000',
					'tax_rate_name'    => 'Sales Tax',
				]
			);
			$this->haveKlarnaCredentials( 'us' );
			$this->reloadPaymentGateways();
			$this->haveCustomerAddress( $this->usAddress(), $this->usAddress() );
			return;
		}

		$this->configureStore(
			array_merge( [ 'country' => 'SE', 'currency' => 'SEK', 'calc_taxes' => 'se-no-tax' !== $profile ], $store )
		);

		foreach ( $basket['rates'] ?? [] as $rate ) {
			$slug = $this->haveTaxClass( $rate[0], $rate[1] );

			foreach ( $rate[2] ?? [] as $priority => $compound_rate ) {
				$this->haveTaxRate(
					[
						'tax_rate_country'  => 'SE',
						'tax_rate'          => $compound_rate,
						'tax_rate_name'     => "{$rate[0]} compound",
						'tax_rate_class'    => $slug,
						'tax_rate_priority' => $priority + 2,
						'tax_rate_compound' => 1,
					]
				);
			}
		}
	}

	/**
	 * The basket's products, named and SKU'd by position so snapshots stay stable.
	 *
	 * @return array<int, array{0: \WC_Product, 1: int}>
	 */
	private function haveBasketProducts( array $basket ): array {
		$products = [];

		foreach ( $basket['items'] as $index => $item ) {
			$price     = $item[0];
			$quantity  = $item[1] ?? 1;
			$tax_class = $item[2] ?? '';
			$number    = $index + 1;

			if ( 'variation' === ( $item[3] ?? 'simple' ) ) {
				list( , $variations ) = $this->haveVariableProduct(
					[ 'Red' => [ 'price' => $price, 'tax_class' => $tax_class, 'sku' => "rounding-{$number}-red" ] ],
					[ 'name' => "Rounding variable {$number}", 'sku' => "rounding-{$number}" ]
				);
				$product = $variations['Red'];
			} else {
				$product = $this->haveSimpleProduct(
					[
						'name'      => "Rounding item {$number}",
						'sku'       => "rounding-{$number}",
						'price'     => $price,
						'tax_class' => $tax_class,
					]
				);
			}

			$products[] = [ $product, $quantity ];
		}

		return $products;
	}

	private function basketCountry( array $basket ): string {
		return 'us' === ( $basket['profile'] ?? 'se' ) ? 'US' : 'SE';
	}

	/** The body of the one create-session request. */
	private function sessionBody(): array {
		return $this->klarnaRequestTo( '/payments/v1/sessions' )['json'];
	}

	/** @return array<string, int> Product ids in the cart, masked so the snapshot does not depend on them. */
	private function productIdPlaceholders(): array {
		$placeholders = [];

		foreach ( array_values( WC()->cart->get_cart() ) as $index => $item ) {
			$placeholders[ '<product-' . ( $index + 1 ) . '-id>' ] = $item['data']->get_id();
		}

		return $placeholders;
	}
}
