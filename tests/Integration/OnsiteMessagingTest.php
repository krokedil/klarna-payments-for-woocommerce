<?php

declare(strict_types=1);

namespace Tests\Integration;

use Krokedil\Klarna\OnsiteMessaging\KlarnaOnsiteMessaging;
use Tests\Support\IntegrationTestCase;

/**
 * When the On-Site Messaging script reaches the page.
 *
 * @covers \Krokedil\Klarna\OnsiteMessaging\KlarnaOnsiteMessaging::enqueue_scripts
 * @covers \Krokedil\Klarna\OnsiteMessaging\Shortcode
 */
class OnsiteMessagingTest extends IntegrationTestCase {

	private const MODULE = '@klarna/onsite_messaging';

	protected ?string $storeProfile = 'se';

	private KlarnaOnsiteMessaging $osm;

	protected function setUp(): void {
		parent::setUp();

		wp_dequeue_script_module( self::MODULE );
		$this->haveKlarnaCredentials( 'se', [ 'test_client_id_se' => 'klarna_test_client_se' ] );

		$this->osm = new KlarnaOnsiteMessaging( [ 'onsite_messaging_enabled' => 'yes' ] );
		$this->osm->init();
	}

	protected function tearDown(): void {
		wp_dequeue_script_module( self::MODULE );
		remove_shortcode( 'onsite_messaging' );

		parent::tearDown();
	}

	/**
	 * Page builders, theme templates and header/footer areas render the shortcode
	 * without it ever being part of the current post's content.
	 */
	public function test_a_shortcode_rendered_outside_the_post_content_loads_the_script(): void {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => 'Built with a page builder, content lives in post meta.',
			]
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->osm->enqueue_scripts();
		$html = do_shortcode( '[onsite_messaging data-key="top-strip-promotion-badge"]' );

		$this->assertStringContainsString( '<klarna-placement', $html );
		$this->assertModuleEnqueued();
	}

	public function test_a_page_without_any_placement_does_not_load_the_script(): void {
		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->go_to( get_permalink( $page_id ) );

		$this->osm->enqueue_scripts();

		$this->assertNotContains( self::MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Before the loop the global post is only the main query's first product, which
	 * gave a shop page banner a random product's price.
	 */
	public function test_a_promotion_on_the_shop_page_has_no_purchase_amount(): void {
		$this->haveSimpleProduct( [ 'price' => '125.00', 'tax_status' => 'none' ] );
		$this->goToProductListing();

		$this->assertSame( '', $this->purchaseAmountOf( do_shortcode( '[onsite_messaging]' ) ) );
	}

	public function test_a_placement_in_the_product_loop_uses_that_products_price(): void {
		$this->haveSimpleProduct( [ 'price' => '125.00', 'tax_status' => 'none' ] );
		$this->goToProductListing();

		the_post();
		$html = do_shortcode( '[onsite_messaging data-key="credit-promotion-badge"]' );
		wp_reset_postdata();

		$this->assertSame( '12500', $this->purchaseAmountOf( $html ) );
	}

	public function test_a_placement_on_a_product_page_uses_the_product_price(): void {
		$product = $this->haveSimpleProduct( [ 'price' => '125.00', 'tax_status' => 'none' ] );
		$this->go_to( get_permalink( $product->get_id() ) );

		$this->assertSame( '12500', $this->purchaseAmountOf( do_shortcode( '[onsite_messaging data-key="credit-promotion-badge"]' ) ) );
	}

	/**
	 * A product listing, such as the shop page or a category, without depending on a shop page in the fixture.
	 */
	private function goToProductListing(): void {
		$this->go_to( add_query_arg( 'post_type', 'product', home_url( '/' ) ) );
		$this->assertFalse( is_product(), 'A listing, not a product page.' );
		$this->assertInstanceOf( \WP_Post::class, $GLOBALS['post'] ?? null, 'The global post is the first product, as on the shop page.' );
	}

	private function purchaseAmountOf( string $html ): string {
		$this->assertSame( 1, preg_match( '/data-purchase-amount="([^"]*)"/', $html, $matches ), 'A placement is printed.' );

		return $matches[1];
	}

	private function assertModuleEnqueued(): void {
		$this->assertContains( self::MODULE, wp_script_modules()->get_queue(), 'The On-Site Messaging module is enqueued.' );
	}
}
