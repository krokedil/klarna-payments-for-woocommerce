<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Assert;
use Tests\Support\EndToEndTester;

/**
 * A browser reaches the tunnel once per purchase, on the confirmation URL Klarna sends the
 * shopper to, and has to land on the local site with the path and query it asked for.
 *
 * Broken, it reads as "never reached the order received page" at the end of a purchase.
 * See tests/_mu-plugins/01-klarna-public-url.php.
 */
class TunnelCest
{
	public function a_browser_that_arrives_on_the_tunnel_lands_on_the_local_site(EndToEndTester $I): void
	{
		$public = rtrim((string) ($_ENV['KP_WORDPRESS_URL'] ?? ''), '/');
		$local  = rtrim((string) ($_ENV['WORDPRESS_URL'] ?? ''), '/');

		Assert::assertNotSame('', $public, 'KP_WORDPRESS_URL is not set in tests/.env.');
		Assert::assertNotSame('', $local, 'WORDPRESS_URL is not set in tests/.env.');
		Assert::assertNotSame($public, $local, 'KP_WORDPRESS_URL and WORDPRESS_URL are the same host.');

		// With a query string, which Klarna's confirmation URL carries the order key in.
		$path = '/shop/?kp-tunnel-probe=1';

		$I->amOnUrl($public);
		$I->amOnPage($path);

		Assert::assertSame(
			$local . $path,
			(string) $I->executeJS('return window.location.href;'),
			'A browser request through the tunnel did not land on the local site.'
		);

		// A rendered page rather than the driver's own error page.
		$I->seeElement('body.woocommerce-shop, body.post-type-archive-product, body');

		// Leave the driver where every other test expects it.
		$I->amOnUrl($local);
	}
}
