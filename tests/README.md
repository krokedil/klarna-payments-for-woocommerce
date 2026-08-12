# Klarna Payments test suites

Three suites, all booted by [lucatume/wp-browser](https://wpbrowser.wptestkit.dev/)
against a real WordPress + WooCommerce + Klarna Payments install:

| Suite | Runner | What it covers |
|---|---|---|
| `Integration` | `composer test:integration` | PHP-level tests with WordPress and WooCommerce loaded in-process (WPLoader). No browser, no ngrok, no Klarna API calls. |
| `Harness` | `composer test:harness` | The test harness itself rather than the plugin: what the WooCommerce Subscriptions fakes answer, and that artifact redaction keeps a Klarna secret out of the report. |
| `EndToEnd` | `composer test:e2e` | Browser-driven tests through an ngrok HTTPS tunnel, because Klarna's JS SDK refuses non-HTTPS origins. |

Start with the Integration suite. It needs no credentials and runs in about two
minutes.

`Harness` boots the same WordPress and shares the same SQLite database as
`Integration`, so **never run the two at once**. Concurrent runs surface as bogus
assertion failures rather than lock errors.

[CONVENTIONS.md](CONVENTIONS.md) covers what to write and what to leave out. Read
it before adding a test. This file is the mechanics.

## Prerequisites

All suites:

- **PHP 7.4+** with `pdo_sqlite`, `sqlite3`, `mbstring`, `gd`, `zip`, `curl`,
  `dom`, `xml`.
- **Composer**, **Node**, **npm**.

EndToEnd only:

- **Chrome**, any recent version. `composer test:chromedriver` matches the driver
  to your local Chrome automatically.
- **ngrok** on `PATH`, with an account that supports reserved or branded domains.
- **WP-CLI** on `PATH`, only needed to regenerate the dump fixture.

## One-time setup

The Integration suite needs steps 1 and 2, but only the *WordPress test install*
block of `tests/.env`. The ngrok and Klarna credential values can stay blank.
Step 3 is EndToEnd only.

1. Copy the env template and fill it in:

   ```bash
   cp tests/.env.example tests/.env
   ```

   Required values:
   - `NGROK_DOMAIN` is the CLI argument for `ngrok http --domain=`, for example
     `kp-test-site`. When using an internal ngrok domain this must be
     `<name>.internal` (such as `kp-test-site.internal`), not the public URL. The
     suite uses it to build the tunnel URL and to point WordPress `siteurl` and
     `home` at the tunnel.
   - `WORDPRESS_URL` and `WORDPRESS_DOMAIN` are the public HTTPS URL the tunnel
     exposes, for example `https://kp-test-site.ngrok.io`. With an internal domain
     set this to the full URL, such as
     `https://kp-test-site.<your-ngrok-subdomain>.ngrok.io`. The suite uses these
     to configure `siteurl` and `home`, and to navigate the browser.
   - `NGROK_AUTHTOKEN` from <https://dashboard.ngrok.com/get-started/your-authtoken>.
   - `KLARNA_TEST_MID_SE` and `KLARNA_TEST_SECRET_SE` from a Klarna test merchant
     account.

2. Install PHP and JS dependencies, then build KP's assets:

   ```bash
   composer install   # scaffolds tests/_wordpress/ via post-autoload-dump-dev
   npm install
   npm run build      # produces /assets/, which KP's frontend JS needs
   ```

3. Generate the SQLite dump fixture:

   ```bash
   composer test:regenerate-dump
   ```

   This boots a clean WordPress, activates WooCommerce and KP, configures SE/SEK,
   creates the shortcode-based checkout pages and one product, then exports
   `tests/Support/Data/dump.sql`. Run it again whenever the upstream WC or KP
   baseline schema changes.

## Running the tests

```bash
composer test:integration          # whole Integration suite
composer test:harness              # whole Harness suite, never alongside Integration
composer test:e2e                  # whole EndToEnd suite

vendor/bin/codecept run Integration:SessionTest                        # one file
vendor/bin/codecept run Integration:SessionTest --debug                # with output
vendor/bin/codecept run Integration OrderManagementTest:test_the_operation_reaches_klarna
```

`composer test:e2e` starts the PHP built-in server, ChromeDriver and ngrok,
restores the dump before each test, runs the suite, then tears everything down.

## The Integration suite

One file per area of the plugin:

| File | Covers |
|---|---|
| `SessionTest.php` | `KP_Session`: creating, updating and reusing a Klarna session, and the order body it carries. |
| `CheckoutTest.php` | `process_payment()` and its three PHP-reachable forks (shortcode, blocks via HPP, express checkout token). |
| `GatewayAvailabilityTest.php` | Whether the gateway offers itself: country, currency, credentials, feature availability. |
| `OrderManagementTest.php` | Capture, cancel, update and refund, plus the guards all four share. |
| `PendingOrdersTest.php` | The out-of-band fraud verdict Klarna sends after answering `PENDING`. |
| `CallbacksTest.php` | The authorization callback. |
| `RequestsTest.php` | Credential resolution, regional endpoint routing, and the `KP_Order_Data` payload builder. |
| `FunctionsTest.php` | The helpers in `includes/kp-functions.php` and the functions that apply Klarna's verdict to an order. |
| `SubscriptionsTest.php` | The recurring token and the unattended renewal that charges it. |

Most methods are data-provider driven, so the case count runs well ahead of the
method count.

### Writing one

Extend `Tests\Support\IntegrationTestCase` and declare a store profile:

```php
class MyTest extends IntegrationTestCase {

    protected ?string $storeProfile = 'se';

    public function test_something(): void {
        $this->haveCustomerAddress( $this->swedishAddress(), $this->swedishAddress() );
        $this->haveCartWith( [ [ $this->haveSimpleProduct(), 2 ] ] );

        $body = ( new \KP_Order_Data( 'b2c' ) )->get_klarna_order_object();

        $this->assertSame( 25000, $body['order_amount'] );
    }
}
```

| Profile | Store |
|---|---|
| `'se'` | SE / SEK, one 25% VAT rate, Klarna test credentials. |
| `'se-no-tax'` | Same, taxes off. |
| `'us'` | US:CA / USD, one 8.5% sales tax rate, a US customer address, US credentials. |
| `null` | WooCommerce defaults (US / USD, taxes off) with no credentials. |

The profile is applied before every test. Override `setUp()` only for genuinely
extra arrange, and call `parent::setUp()` first.

The base class resets the store between tests and carries the assertions for
things plugin code leaves behind: Klarna order lines (`findOrderLine()`,
`assertHasOrderLine()`), order notes (`orderNotes()`, `assertOrderHasNote()`,
`assertOrderHasNoNote()`) and failures (`assertWpErrorCode()`), plus `reload()`
and `statusOf()` for reading an order back after plugin code has written to it.

It pulls in the fixture traits:

| Trait | Gives you |
|---|---|
| `CanConfigureStore` | `configureStore()`, `configureSwedishStore()`, `configureUsStore()`, `haveTaxRate()`, `haveTaxClass()`, `setKlarnaSettings()`, `haveKlarnaCredentials()`, `setFeatureAvailability()`, `reloadPaymentGateways()`, `resetKlarnaSession()`. |
| `CanManageProducts` | `haveSimpleProduct()`, `haveVariableProduct()`. |
| `CanBuildCartsAndOrders` | `haveCustomerAddress()`, `haveCartWith()`, `haveCartFee()`, `haveChosenFlatRateShipping()`, `simulateCheckoutPage()`, `haveOrder()`, `haveKlarnaOrder()`, `haveCapturableKlarnaOrder()`, `markAsKlarnaOrder()`, `haveRefundForItems()`, plus the `swedishAddress()` and `usAddress()` presets. |
| `CanInterceptHttp` | `willRespondWith()`, `httpRequests()`, `klarnaRequests()`, `klarnaRequestTo()`, `klarnaRequestsTo()`, `assertKlarnaRequestCount()`, `assertNoKlarnaRequests()`. |
| `CanDriveKlarnaOrderManagement` | `willRetrieveKlarnaOrder()`, `willCapture()`, `willCancel()`, `willAcceptTheUpdate()`, `willRejectWith()`. |
| `CanDriveCheckout` | `willCreateSession()`, `willCreateHpp()`, `willPlaceOrder()`, `willCreateCustomerToken()`. |
| `CanFakeSubscriptions` | `haveSubscription()`, `haveSubscriptionFor()`, `haveRenewalOrderFor()`, `markAsSubscription()`, `haveCartContaining()`. |
| `CanSnapshotRequests` | `assertRequestMatchesSnapshot()`, `assertMatchesSnapshot()`. |

`haveOrder()` takes `status`, `paid`, `klarna` and `created_via`, which is how
order management tests get an order in the state they need without a follow-up
save. `created_via => 'store-api'` is what makes an order a *blocks* order:
`kp_is_wc_blocks_order()` looks at nothing else, and it is the whole fork between
`process_blocks_order()` and `process_checkout_order()`.

```php
$order = $this->haveOrder(
    [
        'items'   => [ [ $this->haveSimpleProduct(), 2 ] ],
        'billing' => $this->swedishAddress(),
        'klarna'  => true,          // or [ 'order_id' => ..., 'country' => ... ]
        'paid'    => true,
        'status'  => 'on-hold',
    ]
);

// Or, for the common case, the preset with overrides:
$order = $this->haveKlarnaOrder( [ 'status' => 'on-hold' ] );
```

### Request body snapshots

Outgoing Klarna payloads are pinned against JSON fixtures in
`Support/Data/snapshots/` rather than asserted key by key:

```php
$this->assertRequestMatchesSnapshot( $this->klarnaRequestTo( '/captures' ), 'om-capture-se' );
```

The fixture records the HTTP method, the full URL (which pins the regional
endpoint) and the decoded body. To rewrite the fixtures after an intended change:

```bash
composer test:integration:snapshots
```

Then read the diff before committing. That diff is the review. There is more on
placeholders and on what does not belong in a snapshot in
[CONVENTIONS.md](CONVENTIONS.md).

### Outbound HTTP is blocked

`IntegrationTestCase` hooks `pre_http_request` for every test, so nothing reaches
Klarna. An unexpected call is short-circuited with a `WP_Error`, which is a path
production code already has to survive, and it is recorded so you can assert on
it. Queue a canned response for calls that need to succeed:

```php
$this->willRespondWith(
    [ 'initial_payment_method' => [ 'description' => 'Pay in 30 days' ] ],
    200,
    'ordermanagement/v1/orders'   // optional: only answer matching URLs
);
```

A fourth argument sets response headers, for the endpoints that answer with an
empty body. A capture returns its capture id in a `capture-id` header, and
`process_response()` reads it from there.

Responses are consumed in the order they were queued, first URL match wins. That
matters for order management, where cancelling, capturing, updating and refunding
all retrieve the Klarna order first, so a test that expects the second request to
happen has to queue two responses. `CanDriveKlarnaOrderManagement` wraps both
halves, so the test says what Klarna will answer rather than how a response is
shaped:

```php
$this->willRetrieveKlarnaOrder( [ 'status' => 'CAPTURED' ] );   // the lookup
$this->willCancel();                                            // the action
```

`CanDriveCheckout` is the same idea for the purchase itself. A blocks checkout
creates a session and then a hosted payment page, so `willCreateSession()`
followed by `willCreateHpp()` says what Klarna answers without the test knowing
which response envelope belongs to which call.

Assert on a request by naming its endpoint rather than its position.
`klarnaRequestTo( '/captures' )` and `assertKlarnaRequestCount( 1, '/captures' )`
survive KP adding another call to the same flow, where `klarnaRequests()[1]` would
not.

### WooCommerce Subscriptions is faked, always

The `wcs_*` functions KP calls are declared in
[_subscriptions-fakes.php](_subscriptions-fakes.php), which the Integration and
Harness bootstraps require for the whole run. PHP cannot undefine a function, so
the per-test switch cannot live in `function_exists()`. They answer from
`Tests\Support\Fakes\SubscriptionsRegistry`, which is empty until a test puts
something in it, so a test that ignores all of this sees a store with no
subscriptions. `CanFakeSubscriptions` is the way in:

```php
$parent       = $this->haveOrder( [ 'klarna' => true ] );
$subscription = $this->haveSubscriptionFor( $parent );   // a shop_subscription
$renewal      = $this->haveRenewalOrderFor( $subscription );
```

Those are real orders. Only the class `wc_get_order()` returns is swapped, for
`Fakes\SubscriptionOrder`, which is a `WC_Order` plus the `shop_subscription`
type, `get_parent()` and `payment_failed()`. It does **not** stand in for
`WC_Subscriptions_Cart` or `WC_Subscriptions_Product`, so a cart holding a new
subscription product is still out of reach. `haveCartContaining( 'renewal' )` and
its siblings cover the states KP branches on beyond that.
[Harness/Fixtures/FakeSubscriptionsTest.php](Harness/Fixtures/FakeSubscriptionsTest.php)
pins what the fake answers.

Note that the subscription fake keeps `WC_Order::payment_complete()` rather than
`WC_Subscription`'s, and the subscription itself carries no line items, so a
completed renewal lands the subscription on `completed` rather than `processing`.

### Other things to know about the environment

- WPLoader wraps each test in a database transaction, but WooCommerce keeps state
  outside the database: the session cart, `WC()->customer`, tax rate caches, the
  shipping method count transient, and whatever third party integrations read from
  `WC()->session`. `IntegrationTestCase::resetStore()` clears all of it. If you
  add a fixture that writes somewhere new, reset it there too.
- Store defaults are US / USD with taxes off. KP treats US as a separate sales tax
  market, so a test that sets no store profile runs with `separate_sales_tax` on.
- `assertNoKlarnaRequests()` filters to `klarna.com` on purpose. WooCommerce core
  makes requests of its own, and rebuilding the payment gateways triggers the
  WooPayments incentive check, so asserting on all outbound HTTP would make a test
  hostage to unrelated calls.
- Anything reading `filter_input( INPUT_GET, ... )` or `INPUT_POST` is unreachable
  here. It reads the real request, which is empty in CLI no matter what `$_GET`
  says. `KP_Callbacks::process_hpp_redirect()` and the refund return fee are the
  cases in point, and both belong to the E2E suite.
- `ExpressCheckout`, `OnsiteMessaging` and `SignInWithKlarna` under `src/` have no
  Integration coverage yet.

## Test reports

All three suites write [Allure](https://allurereport.org/) results, and one report
renders them together:

```bash
composer test:integration          # and/or
composer test:harness              # and/or
composer test:e2e

composer test:report               # writes tests/_output/report/index.html
```

Results accumulate per suite in `tests/_output/allure-results/<suite>/`, so running
one suite never discards another's. Run only `test:e2e` and the report still
carries yesterday's Integration results. `composer test:report:reset` clears
everything. Rendering is done by the `allure` CLI from `devDependencies`, which is
the TypeScript build, so no Java is involved.

**Two output modes.** `test:report` writes a directory that zips well as a CI
artifact. `composer test:report:share` writes one self-contained
`tests/_output/report-share/index.html` for handing to someone outside the dev
team.

**What lands on a test.** Integration tests carry `http-requests.json`, every
Klarna call the test provoked request by request, plus their data-provider
arguments as report parameters, so a table-driven case shows which row it was.
EndToEnd *failures* carry `screenshot.png`, `page-source.html`,
`browser-console.log` (where the Klarna SDK reports iframe and tokenisation
errors) and `network.json`, a flat list of what the browser fetched with method,
URL, status and mime type.

**Server-side logs land on every E2E test, passing or not.** What the WordPress
install itself wrote gets attached too: `debug.log` for PHP notices, warnings and
fatals, plus one attachment per WooCommerce log handle.
`place-order-debug-<uid>.log` is the useful half of a broken checkout, since it
records each step of the flow together with the notices raised along the way. A
green checkout can still log a deprecation, which is why these are not
failures-only.

Each test gets **only its own lines**. `WordPressLogReporter` notes how long each
log file is before a test starts and attaches just what arrived by the time it
ends, so a 20 KB slice rather than a shared megabyte. The logs are wiped once at
the start of each suite run: `wp-content/debug.log` truncated, `wc-logs/*.log`
deleted, with WooCommerce's `.htaccess` and `index.html` left alone since those
are directory protection rather than logs. Tests that logged nothing attach
nothing.

Two caveats. Logs are written by the built-in server, a *different process* to the
test, so a line written as a test tears down can occasionally land in the next
test's slice. And the Integration suite contributes nothing here, because
Codeception's error handler intercepts PHP notices and turns them into test
failures before they ever reach `debug.log`, so only EndToEnd collects logs.

**The redaction guarantee.** Every *text* artifact is scrubbed through
`Tests\Support\Reporting\Redactor` before it is attached, in every encoding KP can
emit: raw, base64, URL-encoded, JSON-escaped, and the
`base64(merchant_id:shared_secret)` pair KP actually puts in its `Authorization`
header. Afterwards `verify-no-secrets.php` re-scans the whole results directory and
the raw `tests/_output` artifacts. If a credential survived, the report is deleted
and the build fails, naming the *env var* that leaked and never its value. It scans
the results rather than the rendered HTML on purpose, because Allure base64-encodes
attachment payloads into the report, so grepping `index.html` would give false
confidence.

Note the boundary. The gate covers everything *published*, since attachments are
written into `allure-results`, which it scans. It does **not** scan the raw logs
under `tests/_wordpress/wp-content/`, which stay on your machine and are not CI
artifacts. Scrub those by hand before sharing them.

**Adding a new secret.** Add the env var name to `SecretRegistry::SECRET_KEYS`, and
to `BASIC_AUTH_PAIRS` if it is one half of a `mid:secret` pair. Regional
`KLARNA_TEST_SECRET_<CC>` keys present in `tests/.env` are picked up automatically,
so a new region needs no code change.

**Adding a new artifact type.** Scrub it through the `Redactor` before attaching
it. The gate is a backstop for mistakes, not the control.

## Layout

| Path | Purpose |
|---|---|
| `tests/CONVENTIONS.md` | What to write and what to leave out. |
| `tests/Integration/` | PHPUnit-style tests (WPLoader), one file per area of the plugin. |
| `tests/Harness/` | Tests of the harness rather than the plugin: the subscriptions fakes and the artifact redaction. |
| `tests/_subscriptions-fakes.php` | The `wcs_*` stand-ins, required by the Integration and Harness bootstraps. |
| `tests/Support/IntegrationTestCase.php` | Base class: store profiles, store reset, and order line, order note and `WP_Error` assertions. |
| `tests/Support/Traits/CanConfigureStore.php` | Store options, tax rates, KP settings. |
| `tests/Support/Traits/CanManageProducts.php` | Product fixtures via the WC CRUD layer. |
| `tests/Support/Traits/CanBuildCartsAndOrders.php` | Customer address, cart, shipping and order fixtures. |
| `tests/Support/Traits/CanInterceptHttp.php` | Blocks and records outbound HTTP; queues canned responses. |
| `tests/Support/Traits/CanDriveKlarnaOrderManagement.php` | Canned Klarna order management responses (lookup, capture, cancel, update, rejection). |
| `tests/Support/Traits/CanDriveCheckout.php` | Canned Klarna checkout responses (session, HPP, place order, customer token). |
| `tests/Support/Traits/CanFakeSubscriptions.php` | Subscription, renewal-order and cart-state fixtures. |
| `tests/Support/Traits/CanSnapshotRequests.php` | Pins a request or an array against a committed JSON fixture. |
| `tests/Support/Data/snapshots/` | The request body fixtures. Regenerate with `composer test:integration:snapshots`. |
| `tests/Support/Fakes/` | `SubscriptionsRegistry` (what the `wcs_*` stubs answer) and `SubscriptionOrder` (the `WC_Subscription` stand-in). |
| `tests/EndToEnd/` | Browser-driven Cests (WPWebDriver). |
| `tests/Support/Traits/CanManageE2E*.php` | E2E fixtures written straight to the DB via WPDb. |
| `tests/Support/Extension/NgrokController.php` | Starts and stops the ngrok tunnel. |
| `tests/Support/Extension/ArtifactReporter.php` | Attaches screenshot, page source, console log and network summary to failed E2E tests. |
| `tests/Support/Extension/WordPressLogReporter.php` | Clears `debug.log` and `wc-logs/` per suite; attaches each test's own slice of them. |
| `tests/Support/Reporting/` | `Redactor` (scrubs secrets out of any string), `SecretRegistry` (builds one from `tests/.env`) and `LogTail` (reads a test's slice of the WP and WC logs). |
| `tests/_support_scripts/verify-no-secrets.php` | The leak gate: fails the build and deletes the report if a credential survived. |
| `tests/_support_scripts/generate-report.sh` | Renders the Allure report from whichever suite results exist, then runs the gate. |
| `tests/_mu-plugins/01-https-proxy.php` | Honours ngrok's `X-Forwarded-Proto`; forces `siteurl` and `home`. |
| `tests/_mu-plugins/02-klarna-test-credentials.php` | Overlays KP settings with env-var credentials. |
| `tests/_support_scripts/install-test-env.php` | Idempotent WP scaffold, runs on `composer install`. |
| `tests/_support_scripts/regenerate-dump.sh` | Rebuilds `dump.sql` from a clean install. |
| `tests/Support/Data/dump.sql` | The fixture WPDb restores before each E2E test. Generated, and carries no committed credentials. |

## Troubleshooting

- **`ngrok did not expose ... within 15s`.** Usually a wrong `NGROK_DOMAIN`, which
  must be `<name>.internal` rather than the public URL, or an empty or invalid
  `NGROK_AUTHTOKEN`. Run `ngrok http --domain=$NGROK_DOMAIN 8000` manually against
  a dummy `python -m http.server 8000` to isolate it.
- **`An ngrok process is already running ...`.** A previous run crashed and left
  ngrok orphaned. `pkill ngrok` and retry.
- **Klarna gateway not visible in an E2E test.** Confirm `KLARNA_TEST_MID_SE` and
  `KLARNA_TEST_SECRET_SE` are set and valid. The gateway short-circuits in
  `country_currency_check()` when credentials are missing.
- **`session not created: This version of ChromeDriver only supports ...`.** Chrome
  auto-updated. Run `composer test:chromedriver`.
- **A snapshot test fails after an unrelated change.** Read the diff first. If the
  change was intended, `composer test:integration:snapshots` and commit the new
  fixture. If it was not, the snapshot just caught a regression.
- **An Integration test passes alone but fails in the suite.** Something is
  leaking state between tests. Check what your fixture writes to `WC()->session`
  and whether `resetStore()` clears it.
