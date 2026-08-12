<?php

declare(strict_types=1);

namespace Tests\Support;

/** Inherited Methods */
class EndToEndTester extends \Codeception\Actor
{
    use _generated\EndToEndTesterActions;
	use Traits\CanManageE2EProducts;
	use Traits\CanManageE2ETaxRates;

	/** Fill the WooCommerce checkout billing address form with test data. */
	public function fillBillingAddressForm(
		string $firstName = 'John',
		string $lastName = 'Doe',
		string $email = 'test@example.com',
		string $phone = '+4655555555',
		string $address = 'Test Street 1',
		string $city = 'Test City',
		string $postcode = '12345',
		string $country = 'SE'
	): void {
		$this->waitForCheckoutReady();
		$this->fillField('#billing_first_name', $firstName);
		$this->fillField('#billing_last_name', $lastName);
		$this->fillField('#billing_email', $email);
		$this->fillField('#billing_phone', $phone);
		$this->fillField('#billing_address_1', $address);
		$this->fillField('#billing_city', $city);
		$this->fillField('#billing_postcode', $postcode);
		$this->selectOption('#billing_country', $country);
		$this->waitForCheckoutReady();
	}

	/**
	 * Wait until the WooCommerce checkout form has settled after any pending
	 * `update_checkout` AJAX call.
	 */
	public function waitForCheckoutReady(int $timeout = 20): void
	{
		$this->waitForJS(
			"return typeof jQuery !== 'undefined'"
			. " && jQuery.active === 0"
			. " && !document.querySelector('form.checkout .blockUI')"
			. " && !!document.querySelector('#place_order:not([disabled])');",
			$timeout
		);
	}

	/** Process the Klarna iframe to place the order there. */
	public function processKlarnaIframe(string $paymentMethod = 'Pay in 30 days'): void
	{
		$this->waitForElement('#klarna-apf-iframe', 20);
		$this->switchToIFrame('#klarna-apf-iframe');

		$this->waitForText('Continue with BankID', 20);
		$this->click('Continue with BankID');

		// Wait for any post-BankID screen to render past the loader.
		$this->waitForJS(
			"return !!document.body && /Choose how to pay|Pay with/.test(document.body.innerText);",
			30
		);

		// If we're on a confirm screen that has a Change link, open the picker.
		$changeLink = '[data-fs-element="Payment Option Preview"]';
		if (! $this->isTextOnPage('Choose how to pay') && $this->isElementOnPage($changeLink)) {
			$this->click($changeLink);
			$this->waitForText('Choose how to pay', 20);
		}

		// On the picker, pick the requested method and continue.
		if ($this->isTextOnPage('Choose how to pay')) {
			// Klarna's React store is sometimes slow to register a synthetic click.
			$registered = false;
			for ($attempt = 1; $attempt <= 3; $attempt++) {
				$this->selectKlarnaPaymentOption($paymentMethod);
				if ($this->waitForKlarnaSelectionRegistered(8)) {
					$registered = true;
					break;
				}
			}
			if (! $registered) {
				$this->fail('Klarna never registered the picker selection (data-fs-has-selected-method stayed "false" after 3 click attempts).');
			}

			$this->jsClick('#buy_button');
			$this->waitForText('Pay with', 20);
		}

		// Wait for the buy button to be enabled; Klarna keeps it clickable but inert.
		$this->waitForJS(
			"return !!document.querySelector('#buy_button:not([disabled]):not([aria-busy=\"true\"])');",
			20
		);
		$this->jsClick('#buy_button');
	}

	/**
	 * Non-throwing check for whether a text is currently rendered on the page,
	 * for branching on optional UI where `see()` would fail the test.
	 */
	public function isTextOnPage(string $text): bool
	{
		$needle = addslashes($text);
		return (bool) $this->executeJS(
			"return !!document.body && document.body.innerText.indexOf('{$needle}') !== -1;"
		);
	}

	/**
	 * Non-throwing check for whether a CSS selector currently matches an
	 * element on the page, for branching on optional UI.
	 */
	public function isElementOnPage(string $cssSelector): bool
	{
		$selector = addslashes($cssSelector);
		return (bool) $this->executeJS(
			"return !!document.querySelector('{$selector}');"
		);
	}

	/** Select a Klarna picker option by its visible label. */
	public function selectKlarnaPaymentOption(string $label): void
	{
		// Wait for any in-flight loader to settle before reading the DOM.
		$this->waitForJS(
			"return !!document.querySelector('[data-fs-element=\"Payment Option Radio\"]');",
			20
		);

		$needle = addslashes($label);
		$clicked = (bool) $this->executeJS(
			<<<JS
			const target = '{$needle}';
			const radios = Array.from(document.querySelectorAll('[data-fs-element="Payment Option Radio"]'));
			// Find the radio whose enclosing card contains the requested label.
			let pick = radios.find(r => {
				let el = r.parentElement;
				while (el && el !== document.body) {
					if (el.innerText && el.innerText.includes(target)) return true;
					el = el.parentElement;
				}
				return false;
			});
			// If nothing text-matched but the picker offers a single option, take it.
			if (!pick && radios.length === 1) pick = radios[0];
			if (!pick) return false;

			// Click the enclosing Payment Offer Card label, which carries Klarna's handler.
			const card = pick.closest('label[data-fs-element="Payment Offer Card"]')
				|| pick.closest('label[for]')
				|| pick;

			// A bare .click() does not propagate the selection into Klarna's store.
			const rect = card.getBoundingClientRect();
			const cx = rect.left + rect.width / 2;
			const cy = rect.top + rect.height / 2;
			const eventInit = {
				bubbles: true,
				cancelable: true,
				composed: true,
				view: window,
				clientX: cx,
				clientY: cy,
				button: 0,
				buttons: 0,
			};
			for (const type of ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click']) {
				const Ctor = type.startsWith('pointer') ? PointerEvent : MouseEvent;
				try { card.dispatchEvent(new Ctor(type, { ...eventInit, pointerType: 'mouse' })); }
				catch (e) { card.dispatchEvent(new MouseEvent(type, eventInit)); }
			}

			// Also flip the radio's checked state and fire a change event.
			try {
				pick.checked = true;
				pick.dispatchEvent(new Event('change', { bubbles: true }));
			} catch (e) { /* ignore, the card sequence above is the primary path */ }

			return true;
			JS
		);

		if (! $clicked) {
			$this->fail("Klarna picker has no option matching '{$label}'");
		}
	}

	/**
	 * Poll until Klarna's buy button reports the picker selection as
	 * registered, or until the timeout is reached.
	 */
	public function waitForKlarnaSelectionRegistered(int $timeout): bool
	{
		$deadline = microtime(true) + $timeout;
		while (microtime(true) < $deadline) {
			$registered = (bool) $this->executeJS(
				"return !!document.querySelector('#buy_button[data-fs-has-selected-method=\"true\"]');"
			);
			if ($registered) {
				return true;
			}
			usleep(500_000); // 500ms
		}
		return false;
	}

	/**
	 * Click an element by CSS selector via JavaScript, for the Klarna buttons
	 * where an overlaid label span intercepts Selenium's pointer click.
	 */
	public function jsClick(string $cssSelector): void
	{
		$selector = addslashes($cssSelector);
		$clicked = (bool) $this->executeJS(
			"const el = document.querySelector('{$selector}');"
			. " if (!el) return false;"
			. " el.click();"
			. " return true;"
		);
		if (! $clicked) {
			$this->fail("jsClick: no element matches '{$cssSelector}'");
		}
	}

	/** Verify a WooCommerce order once we are on the thank you page. */
	public function verifyOrderOnThankYouPage(string $paymentMethod, string $orderTotal, array $expectedMeta = []): void
	{
		$order_id = $this->grabFromCurrentUrl('/\/checkout\/order-received\/(\d+)\//');
		if ($order_id === null) {
			$this->fail("Could not extract order ID from URL");
			return;
		}

		$this->seeInDatabase('wp_posts', [
			'ID' => $order_id,
			'post_type' => 'shop_order',
		]);

		$expectedMeta = array_merge([
			'_payment_method' => $paymentMethod,
			'_order_total' => $orderTotal,
		], $expectedMeta);

		foreach( $expectedMeta as $meta_key => $meta_value ) {
			$this->seeInDatabase('wp_postmeta', [
				'post_id' => $order_id,
				'meta_key' => $meta_key,
				'meta_value' => $meta_value,
			]);
		}
	}

}
