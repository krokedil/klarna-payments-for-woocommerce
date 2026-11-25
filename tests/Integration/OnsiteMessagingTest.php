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

	private function assertModuleEnqueued(): void {
		$this->assertContains( self::MODULE, wp_script_modules()->get_queue(), 'The On-Site Messaging module is enqueued.' );
	}
}
