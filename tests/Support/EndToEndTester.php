<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The EndToEnd actor. The fixtures and the checkout flow live in the traits.
 */
class EndToEndTester extends \Codeception\Actor
{
    use _generated\EndToEndTesterActions;
	use Traits\CanManageE2EProducts;
	use Traits\CanManageE2ETaxRates;
	use Traits\CanDriveE2ECheckout;
	use Traits\CanDriveE2EOrderManagement;
}
