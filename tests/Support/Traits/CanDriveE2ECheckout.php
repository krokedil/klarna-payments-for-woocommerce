<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Codeception\Exception\ElementNotFound;
use Facebook\WebDriver\Exception\WebDriverException;
use PHPUnit\Framework\Assert;

/**
 * The browser half of a purchase, one step at a time: shape the store and the cart,
 * then drive the shortcode checkout through Klarna's iframe.
 *
 * Sibling of CanDriveCheckout, which cans Klarna's answers for the Integration suite.
 */
trait CanDriveE2ECheckout {

	use \Tests\Support\_generated\EndToEndTesterActions;

	/** The billing address every checkout starts from; null leaves a field untouched. */
	public const BILLING_ADDRESS = [
		'country'    => 'SE',
		'first_name' => 'John',
		'last_name'  => 'Doe',
		'email'      => 'test@example.com',
		'phone'      => '+4655555555',
		'address_1'  => 'Test Street 1',
		'city'       => 'Test City',
		'postcode'   => '12345',
	];

	/**
	 * The Klarna payment option a purchase takes by default, matched against Klarna's
	 * own option id and against the text on its card.
	 */
	public const DEFAULT_KLARNA_METHOD = 'pay_later';

	/** Klarna's hosted payment iframe on the WooCommerce checkout. */
	private const KLARNA_IFRAME = '#klarna-apf-iframe';

	/** How long to keep advancing Klarna's screens before giving up, in seconds. */
	private const KLARNA_TIMEOUT = 120;

	/** How long to wait between reading Klarna's screen and reading it again. */
	private const KLARNA_POLL = 1_000_000; // 1s

	/** How many times to act on the same screen before calling it stuck. */
	private const KLARNA_SCREEN_ATTEMPTS = 8;

	/** How many attempts to insist on the scenario's payment method before taking what Klarna offers. */
	private const KLARNA_METHOD_ATTEMPTS = 3;

	/** How many polls to sit on a screen we have no move for before failing. */
	private const KLARNA_UNKNOWN_POLLS = 10;

	/** Which of those polls tries the obvious way on. */
	private const KLARNA_UNKNOWN_NUDGE = 4;

	/** A Klarna screen before anything has been read. */
	private const EMPTY_KLARNA_SCREEN = [
		'text'     => '',
		'buttons'  => [],
		'options'  => [],
		'picker'   => false,
		'account'  => false,
		'identify' => false,
		'buy'      => null,
		'loading'  => false,
	];

	/** Billing fields that are `<select>` rather than `<input>`. */
	private const BILLING_SELECT_FIELDS = [ 'country', 'state' ];

	/** Sets the store options a scenario needs, keyed by option name. */
	public function haveStoreOptionsInDatabase( array $options ): void {
		foreach ( $options as $name => $value ) {
			$this->haveOptionInDatabase( $name, $value );
		}
	}

	/** Creates the tax classes and rates a scenario needs. */
	public function haveTaxClassesInDatabase( array $rates ): void {
		foreach ( $rates as $rate ) {
			$this->haveTaxClassInDatabase( $rate );
		}
	}

	/**
	 * Creates the products a scenario needs and adds them to the cart. An item is a
	 * SKU from TestProducts or `[ SKU, quantity ]`.
	 *
	 * @return array<string, int> The created product ids, keyed by SKU.
	 */
	public function haveCartWith( array $items ): array {
		$product_ids = [];

		foreach ( $items as $item ) {
			[ $sku, $quantity ] = is_array( $item ) ? [ $item[0], $item[1] ?? 1 ] : [ $item, 1 ];

			$product_ids[ $sku ] = $this->haveProductInDatabase( $sku );

			// WooCommerce's own add-to-cart, which leaves the cart in the session the
			// checkout reads. Doing it over wc-ajax builds a second cart instead.
			$this->amOnPage( "/?add-to-cart={$product_ids[ $sku ]}&quantity={$quantity}" );
		}

		return $product_ids;
	}

	/**
	 * Opens the checkout and waits for a Klarna payment category to render, which is
	 * the gateway availability assertion.
	 */
	public function amOnCheckoutPageWithKlarna( string $paymentMethodId = 'klarna_payments_pay_later' ): void {
		$this->amOnPage( '/checkout/' );
		$this->waitForElement( "#payment_method_{$paymentMethodId}", 15 );
	}

	/** Fills the WooCommerce checkout billing address form. */
	public function fillBillingAddressForm( array $overrides = [] ): void {
		$address = array_replace( self::BILLING_ADDRESS, $overrides );

		$this->waitForCheckoutReady();

		// Country first: it decides which other fields WooCommerce renders.
		if ( isset( $address['country'] ) ) {
			$this->selectOption( '#billing_country', $address['country'] );
			$this->waitForCheckoutReady();
		}
		unset( $address['country'] );

		foreach ( $address as $field => $value ) {
			if ( $value === null ) {
				continue;
			}

			$selector = "#billing_{$field}";

			if ( in_array( $field, self::BILLING_SELECT_FIELDS, true ) ) {
				$this->selectOption( $selector, $value );
				continue;
			}

			$this->fillField( $selector, $value );
		}

		$this->waitForCheckoutReady();
	}

	/** Waits for the checkout form to settle after any pending `update_checkout` call. */
	public function waitForCheckoutReady( int $timeout = 20 ): void {
		$this->waitForJS(
			"return typeof jQuery !== 'undefined'"
			. " && jQuery.active === 0"
			. " && !document.querySelector('form.checkout .blockUI')"
			. " && !!document.querySelector('#place_order:not([disabled])');",
			$timeout
		);
	}

	/** Places the order and drives Klarna's iframe to the thank you page. */
	public function placeKlarnaOrder( string $paymentMethod = self::DEFAULT_KLARNA_METHOD ): void {
		$this->waitForCheckoutReady();
		$this->click( '#place_order' );

		$this->completeKlarnaCheckout( $paymentMethod );
	}

	/**
	 * Drives Klarna's hosted iframe until the browser lands on the thank you page.
	 *
	 * Klarna picks the screens and their order, so nothing is scripted: read the screen,
	 * advance the state it matches, look again, until the thank you page or the timeout.
	 */
	public function completeKlarnaCheckout( string $paymentMethod = self::DEFAULT_KLARNA_METHOD, int $timeout = self::KLARNA_TIMEOUT ): void {
		$deadline = microtime( true ) + $timeout;
		$path     = [];
		$attempts = [];
		$unknown  = 0;
		$screen   = [];

		while ( microtime( true ) < $deadline ) {
			$page = $this->readTopWindow();

			if ( str_contains( $page['uri'], 'order-received' ) ) {
				$this->comment( 'klarna: ' . implode( ' -> ', $path ) . ' -> order received' );
				return;
			}

			// No iframe yet, or Klarna is between documents.
			if ( ! $page['hasIframe'] ) {
				usleep( self::KLARNA_POLL );
				continue;
			}

			$screen = $this->readKlarnaScreen();
			$name   = $screen['name'];

			if ( end( $path ) !== $name ) {
				$path[] = $name;
				$this->comment( "klarna: {$name}" );
			}

			// Klarna is working, or has drawn something we have no move for.
			if ( $name === 'busy' || $name === 'unknown' ) {
				$unknown = $name === 'unknown' ? $unknown + 1 : 0;

				if ( $unknown > self::KLARNA_UNKNOWN_POLLS ) {
					$this->failOnKlarnaScreen( 'reached a screen it has no move for', $screen, $path );
				}

				// One nudge at the obvious way on before giving up.
				if ( $unknown === self::KLARNA_UNKNOWN_NUDGE ) {
					$this->comment( 'klarna: unknown screen, trying the obvious way on' );
					$this->clickKlarnaOnward();
				}

				usleep( self::KLARNA_POLL );
				continue;
			}
			$unknown = 0;

			$attempts[ $name ] = ( $attempts[ $name ] ?? 0 ) + 1;
			if ( $attempts[ $name ] > self::KLARNA_SCREEN_ATTEMPTS ) {
				$this->failOnKlarnaScreen( "cannot get past the '{$name}' screen", $screen, $path );
			}

			$this->advanceKlarnaScreen( $screen, $paymentMethod, $attempts[ $name ] );

			usleep( self::KLARNA_POLL );
		}

		$this->failOnKlarnaScreen( 'never reached the order received page', $screen, $path );
	}

	/**
	 * Acts on a screen `readKlarnaScreen()` recognised. `$attempt` counts prior acts on
	 * it: the method asked for is insisted on for the first few, then taken as offered.
	 */
	private function advanceKlarnaScreen( array $screen, string $paymentMethod, int $attempt ): void {
		$insist = $attempt <= self::KLARNA_METHOD_ATTEMPTS;

		switch ( $screen['name'] ) {
			case 'identify':
				$this->clickKlarnaIdentityButton();
				return;

			case 'picker':
				// Selecting and continuing are separate polls: Klarna drops clicks
				// under load, so the picker is read back before continuing.
				if ( $insist && ! $this->hasKlarnaOptionSelected( $paymentMethod ) ) {
					$this->selectKlarnaPaymentOption( $paymentMethod );
					return;
				}

				if ( ! $insist && ! $this->hasKlarnaOptionSelected( $paymentMethod ) ) {
					$this->comment( "klarna: will not select {$paymentMethod}, continuing with what it offers" );
				}

				if ( $this->clickKlarnaSelector(
					'[data-fs-element="Continue Button"]',
					'[data-testid="pick-plan"]'
				) ) {
					return;
				}

				// The dialog takes neither a selection nor a continue: shut it and
				// buy on the screen behind it.
				$this->clickKlarnaButton( 'Close' );
				return;

			case 'account':
				// The account Klarna preselects is the only one a test can use; any
				// other needs a real bank login.
				$this->clickKlarnaButton( 'Continue' );
				return;

			case 'confirm':
				// The payment preview is the way back to the picker when Klarna
				// confirms a method nobody asked for.
				if ( $insist
					&& ! $this->klarnaConfirms( $screen, $paymentMethod )
					&& $this->clickKlarnaSelector(
						'[data-fs-element="Payment Preview"]',
						'[data-fs-element="Payment Option Preview"]'
					)
				) {
					return;
				}

				if ( ! $this->klarnaConfirms( $screen, $paymentMethod ) ) {
					$this->comment(
						sprintf(
							'klarna: paying with %s rather than %s, which it would not switch to',
							$screen['buy']['method'] === '' ? 'the method on screen' : $screen['buy']['method'],
							$paymentMethod
						)
					);
				}

				$this->clickKlarnaSelector( '[data-fs-element="Buy Button"]', '#buy_button' );
				return;
		}
	}

	/**
	 * Whether the confirm screen is about to pay with the method we asked for, read from
	 * the buy button's method id first and the text on screen second.
	 */
	private function klarnaConfirms( array $screen, string $paymentMethod ): bool {
		$method = $this->methodNeedle( $paymentMethod );

		if ( $screen['buy'] !== null && str_contains( $this->methodNeedle( (string) $screen['buy']['method'] ), $method ) ) {
			return true;
		}

		return str_contains( $this->methodNeedle( $screen['text'] ), $method );
	}

	/**
	 * Strips a method name to letters and digits, so `pay_later` matches Klarna's
	 * `global_invoice_kp.4_pay_later` and its "Pay later" wording alike.
	 */
	private function methodNeedle( string $value ): string {
		return strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', $value ) );
	}

	/**
	 * Clicks a payment option in Klarna's picker, matched on Klarna's option id or on the
	 * text of its card. A method Klarna never offers surfaces in the stuck-screen failure.
	 */
	public function selectKlarnaPaymentOption( string $paymentMethod ): bool {
		$needle = addslashes( $paymentMethod );

		if ( ! $this->clickKlarnaElement( "(kpOption('{$needle}') || {}).card" ) ) {
			return false;
		}

		// Then flip the radio outright, in case the card is not what carries the handler.
		$this->klarnaJs(
			<<<JS
			const option = kpOption('{$needle}');
			if (option && option.radio && 'checked' in option.radio) {
				try {
					option.radio.checked = true;
					option.radio.dispatchEvent(new Event('change', { bubbles: true }));
				} catch (e) { /* ignore, the click above is the primary path */ }
			}
			return true;
			JS
		);

		return true;
	}

	/** Whether the picker has the method we asked for selected. */
	private function hasKlarnaOptionSelected( string $paymentMethod ): bool {
		$needle = addslashes( $paymentMethod );

		return (bool) $this->klarnaJs(
			<<<JS
			const option = kpOption('{$needle}');
			return option ? kpOptionChecked(option) : false;
			JS
		);
	}

	/**
	 * What the top window is doing, read outside any iframe.
	 *
	 * @return array{uri: string, hasIframe: bool}
	 */
	private function readTopWindow(): array {
		try {
			$this->switchToIFrame();

			$window = $this->executeJS(
				'return { uri: location.pathname + location.search, hasIframe: !!document.querySelector("'
				. self::KLARNA_IFRAME . '") };'
			);
		} catch ( WebDriverException $e ) {
			// The window is between documents; read it again next poll.
			return [
				'uri'       => '',
				'hasIframe' => false,
			];
		}

		$window = is_array( $window ) ? $window : [];

		return [
			'uri'       => (string) ( $window['uri'] ?? '' ),
			'hasIframe' => (bool) ( $window['hasIframe'] ?? false ),
		];
	}

	/**
	 * Everything we branch on inside the Klarna iframe, in one round trip, plus the name
	 * of the screen it adds up to. Leaves the browser switched into the iframe.
	 *
	 * @return array{name: string, text: string, buttons: list<string>, options: list<string>, picker: bool, account: bool, identify: bool, buy: ?array, loading: bool}
	 */
	private function readKlarnaScreen(): array {
		try {
			$this->switchToIFrame( self::KLARNA_IFRAME );

			$screen = $this->klarnaJs(
				<<<'JS'
				const buttons = Array.from(document.querySelectorAll('button, [role="button"]'))
					.filter(kpVisible)
					.map(button => ({
						label: kpLabel(button),
						id: button.id || '',
						method: button.getAttribute('data-fs-payment-method-id') || '',
						disabled: button.disabled === true || button.getAttribute('aria-disabled') === 'true',
						busy: button.getAttribute('aria-busy') === 'true',
					}));

				const text = (document.body ? document.body.innerText : '').replace(/\s+/g, ' ').trim();

				return {
					text: text.slice(0, 600),
					buttons: buttons.map(button => button.label).filter(label => label !== ''),
					// The offer cards, named for the failure message and for picking one.
					options: kpOptions().map(kpOptionName),
					// Not "a radio exists": the confirm screen has those too.
					picker: kpOptions().length > 1 || /Choose how to pay/i.test(kpDialogText()),
					// Klarna's own dialog for picking the bank account to pay from.
					account: !!document.querySelector('#pbb-account-list, [name="pbb_account"]'),
					// Whichever app Klarna picked to identify the shopper with.
					identify: buttons.some(button => /BankID|Swish|Vipps|MitID|Verify|Identify/i.test(button.label)),
					buy: buttons.find(button => button.id === 'buy_button') || null,
					loading: !!document.querySelector('#shield-loader, #loader-wrapper'),
				};
				JS
			);
		} catch ( ElementNotFound | WebDriverException $e ) {
			// The iframe reloaded or went away; treat it as still working, since the
			// next poll reads the top window where success is decided.
			$screen = [ 'loading' => true ];
		}

		$screen = array_replace( self::EMPTY_KLARNA_SCREEN, is_array( $screen ) ? $screen : [] );

		$screen['name'] = $this->nameKlarnaScreen( $screen );

		return $screen;
	}

	/**
	 * Names the screen the iframe is showing. Order matters: Klarna draws its dialogs
	 * over the confirm screen, leaving the buy button in the DOM underneath.
	 */
	private function nameKlarnaScreen( array $screen ): string {
		if ( $screen['identify'] ) {
			return 'identify';
		}

		if ( $screen['account'] ) {
			return 'account';
		}

		if ( $screen['picker'] ) {
			return 'picker';
		}

		if ( $screen['buy'] !== null ) {
			return ( $screen['buy']['busy'] || $screen['buy']['disabled'] ) ? 'busy' : 'confirm';
		}

		// Nothing to act on at all is Klarna still drawing, not an unknown screen.
		if ( $screen['loading'] || $screen['buttons'] === [] ) {
			return 'busy';
		}

		return 'unknown';
	}

	/** Clicks the first visible element matching any of the given selectors. */
	private function clickKlarnaSelector( string ...$selectors ): bool {
		$list = addslashes( implode( ', ', $selectors ) );

		return $this->clickKlarnaElement( "Array.from(document.querySelectorAll('{$list}')).find(kpVisible)" );
	}

	/** Clicks whichever identity app Klarna is offering on this screen. */
	private function clickKlarnaIdentityButton(): bool {
		return $this->clickKlarnaElement(
			'kpButtons().find(button => /BankID|Swish|Vipps|MitID|Verify|Identify/i.test(kpLabel(button)))'
		);
	}

	/** Clicks the first visible button whose label contains the given text. */
	private function clickKlarnaButton( string $text ): bool {
		$needle = addslashes( $text );

		return $this->clickKlarnaElement(
			"kpButtons().find(button => kpLabel(button).toLowerCase().includes('{$needle}'.toLowerCase()))"
		);
	}

	/**
	 * Clicks whatever looks like the way on, for a screen we have no rule for. Anything
	 * that reads like a way back is left alone.
	 */
	private function clickKlarnaOnward(): bool {
		return $this->clickKlarnaElement(
			<<<'JS'
			(() => {
				const away = /cancel|close|back|change|another|other|disconnect|edit|remove/i;
				const onward = /continue|confirm|pay|next|done|approve|accept|ok|buy/i;
				const buttons = kpButtons().filter(button => !away.test(kpLabel(button)));

				return buttons.find(button => onward.test(kpLabel(button)))
					|| (buttons.length === 1 ? buttons[0] : null);
			})()
			JS
		);
	}

	/**
	 * Clicks the element a JavaScript expression finds inside the iframe, through kpClick:
	 * label spans over Klarna's buttons swallow the driver's own click.
	 */
	private function clickKlarnaElement( string $findJs ): bool {
		return (bool) $this->klarnaJs(
			"const el = {$findJs};"
			. ' if (!el) return false;'
			. " el.scrollIntoView({ block: 'center' });"
			. ' return kpClick(el);'
		);
	}

	/** Runs a script inside the Klarna iframe with the kp* helpers below in scope. */
	private function klarnaJs( string $script ) {
		return $this->executeJS(
			<<<'JS'
			// Klarna leaves covered screens and closed dialogs in the DOM, both
			// marked aria-hidden.
			const kpVisible = el => {
				if (!el || !el.getClientRects().length) return false;

				const style = getComputedStyle(el);
				if (style.visibility === 'hidden' || style.display === 'none' || Number(style.opacity) === 0) {
					return false;
				}

				return !el.closest('[aria-hidden="true"], [inert]');
			};
			// The text of the topmost dialog, if any, else of the whole screen.
			const kpDialogText = () => {
				const dialogs = Array.from(document.querySelectorAll('[role="dialog"], dialog, [aria-modal="true"]'))
					.filter(kpVisible);
				const top = dialogs.length ? dialogs[dialogs.length - 1] : document.body;
				return top ? (top.innerText || '').replace(/\s+/g, ' ').trim() : '';
			};
			const kpButtons = () => Array.from(document.querySelectorAll('button, [role="button"]'))
				.filter(kpVisible)
				.filter(button => button.disabled !== true && button.getAttribute('aria-disabled') !== 'true');
			const kpLabel = el => ((el.innerText || el.getAttribute('aria-label') || el.title || '') + '')
				.replace(/\s+/g, ' ')
				.trim()
				.slice(0, 80);
			// A payment option is a radio, which carries the id, plus the card, which
			// carries the click handler and the text.
			const kpNeedle = value => (value + '').toLowerCase().replace(/[^a-z0-9]/g, '');
			const kpCardOf = radio => radio.closest('label[data-fs-element="Payment Offer Card"]')
				|| radio.closest('label')
				|| radio.parentElement
				|| radio;
			const kpOptions = () => {
				const radios = Array.from(document.querySelectorAll(
					'[data-fs-element="Payment Option Radio"], [role="radio"], input[type="radio"]'
				)).filter((radio, index, all) => all.indexOf(radio) === index);

				if (radios.length) {
					return radios
						.map(radio => ({ radio, card: kpCardOf(radio) }))
						.filter(option => kpVisible(option.card));
				}

				// No radios at all: the cards are all we have.
				return Array.from(document.querySelectorAll('label[data-fs-element="Payment Offer Card"]'))
					.filter(kpVisible)
					.map(card => ({ radio: null, card }));
			};
			const kpOptionKey = option => kpNeedle([
				option.radio ? option.radio.id : '',
				option.radio ? (option.radio.value || '') : '',
				option.card.id || '',
				option.card.getAttribute('for') || '',
			].join(' '));
			const kpOptionName = option => kpLabel(option.card) || kpOptionKey(option);
			const kpOption = target => {
				const needle = kpNeedle(target);
				const options = kpOptions();

				return options.find(option => kpOptionKey(option).includes(needle))
					|| options.find(option => kpNeedle(kpLabel(option.card)).includes(needle))
					// One option and no match: it is the only way on.
					|| (options.length === 1 ? options[0] : null);
			};
			const kpOptionChecked = option => [option.radio]
				.concat(Array.from(option.card.querySelectorAll('[role="radio"], input[type="radio"]')))
				.filter(Boolean)
				.some(radio => radio.getAttribute('aria-checked') === 'true' || radio.checked === true);
			const kpClick = el => {
				const box = el.getBoundingClientRect();
				const init = {
					bubbles: true,
					cancelable: true,
					composed: true,
					view: window,
					clientX: box.left + box.width / 2,
					clientY: box.top + box.height / 2,
					button: 0,
					buttons: 0,
				};
				for (const type of ['pointerdown', 'mousedown', 'pointerup', 'mouseup', 'click']) {
					const Ctor = type.startsWith('pointer') ? PointerEvent : MouseEvent;
					try { el.dispatchEvent(new Ctor(type, { ...init, pointerType: 'mouse' })); }
					catch (e) { el.dispatchEvent(new MouseEvent(type, init)); }
				}

				// Native activation on top: the offer cards react only to the
				// sequence above, the buy button only to this.
				try { el.click(); } catch (e) { /* not every element has one */ }

				return true;
			};
			JS
			. "\n" . $script
		);
	}

	/** Fails the test with everything we know about the screen we stopped on. */
	private function failOnKlarnaScreen( string $why, array $screen, array $path ): void {
		Assert::fail(
			sprintf(
				"Klarna's checkout %s.\nScreens: %s\nButtons: %s\nPayment options: %s\nOn screen: %s",
				$why,
				$path === [] ? 'none' : implode( ' -> ', $path ),
				empty( $screen['buttons'] ) ? 'none' : implode( ' | ', $screen['buttons'] ),
				empty( $screen['options'] ) ? 'none' : implode( ' | ', $screen['options'] ),
				$screen['text'] ?? '(nothing read)'
			)
		);
	}

	/** Verify a WooCommerce order once we are on the thank you page. */
	public function verifyOrderOnThankYouPage( string $paymentMethod, string $orderTotal, array $expectedMeta = [] ): void {
		$order_id = $this->grabFromCurrentUrl( '/\/checkout\/order-received\/(\d+)\//' );
		if ( $order_id === null ) {
			Assert::fail( 'Could not extract order ID from URL' );
			return;
		}

		$this->seeInDatabase(
			'wp_posts',
			[
				'ID'        => $order_id,
				'post_type' => 'shop_order',
			]
		);

		$expectedMeta = array_merge(
			[
				'_payment_method' => $paymentMethod,
				'_order_total'    => $orderTotal,
			],
			$expectedMeta
		);

		foreach ( $expectedMeta as $meta_key => $meta_value ) {
			// Read back rather than asserted, so a failure reports the value
			// WooCommerce actually wrote.
			$actual = $this->grabFromDatabase(
				'wp_postmeta',
				'meta_value',
				[
					'post_id'  => $order_id,
					'meta_key' => $meta_key,
				]
			);

			Assert::assertSame(
				$meta_value,
				$actual,
				"Order {$order_id} has {$meta_key} = " . var_export( $actual, true )
					. ", expected " . var_export( $meta_value, true )
			);
		}
	}
}
