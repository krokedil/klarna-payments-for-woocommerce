<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\ExpressCheckout\OneStepCheckout;
use Tests\Support\IntegrationTestCase;

/**
 * The WooCommerce order a KEC one-step checkout builds from the cart.
 *
 * @covers \Krokedil\Klarna\ExpressCheckout\OneStepCheckout
 */
class KecOneStepCheckoutTest extends IntegrationTestCase {

	private const CART_HASH_MESSAGE = 'WooCommerce 11+ only empties the cart after payment when the order carries the hash of that cart.';

	protected ?string $storeProfile = 'se';

	protected function setUp(): void {
		parent::setUp();

		$this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
		WC()->session->set( 'kec_one_step_unique_id', 'kec-unique-id' );
	}

	protected function tearDown(): void {
		OneStepCheckout::unset_sessions();

		parent::tearDown();
	}

	public function test_the_order_carries_the_hash_of_the_cart_it_was_built_from(): void {
		$this->haveOneStepOrder();

		$this->assertTrue( $this->oneStepOrder()->has_cart_hash( WC()->cart->get_cart_hash() ), self::CART_HASH_MESSAGE );
	}

	public function test_the_cart_hash_follows_a_change_of_shipping_option(): void {
		$rate_id     = $this->haveOneStepOrder();
		$hash_before = WC()->cart->get_cart_hash();

		OneStepCheckout::get_changed_shipping_option_response( $this->haveSecondFlatRate( $rate_id ) );

		$this->assertNotSame( $hash_before, WC()->cart->get_cart_hash(), 'The pricier shipping option changes the cart total.' );
		$this->assertTrue( $this->oneStepOrder()->has_cart_hash( WC()->cart->get_cart_hash() ), self::CART_HASH_MESSAGE );
	}

	/** Builds the order the way Klarna's shipping address callback does, and returns the chosen rate. */
	private function haveOneStepOrder(): string {
		$this->haveCartWith( [ [ $this->haveSimpleProduct( [ 'price' => '100.00' ] ), 2 ] ] );
		$rate_id = $this->haveChosenFlatRateShipping( 'SE' );

		OneStepCheckout::get_shipping_address_change_body( [ 'country' => 'SE' ], 'payment-request-id' );

		return $rate_id;
	}

	private function oneStepOrder(): \WC_Order {
		return wc_get_order( WC()->session->get( 'kec_one_step_order_id' ) );
	}

	/** Adds a pricier flat rate to the zone of the given rate. */
	private function haveSecondFlatRate( string $rate_id ): string {
		$zone        = \WC_Shipping_Zones::get_zone_by( 'instance_id', (int) substr( $rate_id, strlen( 'flat_rate:' ) ) );
		$instance_id = $zone->add_shipping_method( 'flat_rate' );

		update_option(
			"woocommerce_flat_rate_{$instance_id}_settings",
			[
				'title'      => 'Express',
				'tax_status' => 'taxable',
				'cost'       => '100.00',
			]
		);

		$this->resetShippingCaches();
		// resetShippingCaches() only sees persisted session keys, and the rates cached a moment ago are not persisted yet.
		WC()->session->__unset( 'shipping_for_package_0' );
		WC()->shipping()->load_shipping_methods();
		$this->recalculateCart();

		return "flat_rate:{$instance_id}";
	}
}
