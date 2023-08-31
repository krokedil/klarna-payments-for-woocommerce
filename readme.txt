=== Klarna Payments for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce
Donate link: https://klarna.com
Requires at least: 5.0
Tested up to: 6.2.2
Requires PHP: 7.4
WC requires at least: 5.6.0
WC tested up to: 8.0.0
Stable tag: 3.2.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== DESCRIPTION ==

*As opposed to Klarna Checkout, Payments are standalone payment methods. Complement your existing checkout experience with a Klarna hosted widget (e.g., Pay Later, Financing or Installments).*

Choose the Klarna features you want – and only the features you want – to reduce purchase stress and improve your user experience. We have several financing and direct payment options to meet your needs, and they’re all easily integrated into your existing buying journey.

=== Pay Now (direct payments) ===
Customers who want to pay in full at checkout can do it quickly and securely with a credit/debit card.Friction-free direct purchases while maximising the value for your business thanks to guaranteed payments. If they have a Klarna account they can save their details and enjoy one-click purchases from then on.

===  Pay later (invoice) ===
Try it first, pay it later. Delayed payments for customers who like low friction purchases and to pay after delivery.

=== Slice it (installments) ===
Installment, revolving and other flexible financing plans let customers pay when they can and when they want.

=== How to Get Started ===
* [Sign up for Klarna](https://www.klarna.com/international/business/woocommerce/).
* [Install the plugin](https://wordpress.org/plugins/klarna-payments-for-woocommerce/) on your site. During this process you will be asked to download [Klarna Order Management](https://wordpress.org/plugins/klarna-order-management-for-woocommerce/) so you can handle orders in Klarna directly from WooCommerce.
* Get your store approved by Klarna, and start selling.

=== What's the difference between Klarna Checkout and Klarna Payments? ===
Klarna as your single payment provider keeps everything under one roof. You’ll have one agreement, one point of contact, one settlement file, one payout with __Klarna Checkout__. It only takes a single integration to deliver the full Klarna hosted checkout experience through a widget placed on your site.

__Klarna Payments__ removes the headaches of payments, for both consumers and merchants. Complement your checkout with a Klarna hosted widget located in your existing checkout which offers payment options for customers with a smooth user experience.

== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Klarna Payments settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/klarna-payments-for-woocommerce/).

== Frequently Asked Questions ==
= Which countries does this payment gateway support? =
Klarna Payments works for merchants in Australia, Austria, Belgium, Canada, Czech Republic, Denmark, Finland, France, Germany, Greece, Ireland (Republic of Ireland), Italy, Netherlands, New Zealand, Norway, Poland, Portugal, Romania, Spain, Sweden, Switzerland, United Kingdom and United States.

= Where can I find Klarna Payments for WooCommerce documentation? =
For help setting up and configuring Klarna Payments for WooCommerce please refer to our [documentation](https://docs.krokedil.com/klarna-payments-for-woocommerce/).


== Changelog ==
= 2023.08.31    - version 3.2.1 =
* Fix           - Fix fatal error due to subscriptions class not available.

= 2023.08.31    - version 3.2.0 =
* Feature       - Added support for WC subscriptions.
* Enhancement   - You can now use the 'klarna_base_region' filter to change the regional endpoint (EU, US or OC).
* Tweak         - The KP regions in the settings should now appear in alphabetic order.
* Fix           - Revert changes related to the setting "What is Klarna". Enabling the setting should now hide the link (like it used to) instead of making it appear.

= 2023.07.26    - version 3.1.3 =
* Fix           - Fixed an issue where the customer type defaulted to B2C even when B2B was chosen in the settings.

= 2023.07.25    - version 3.1.2 =
* Fix           - The Klarna logo will now correctly be fetched from the session as originally intended, rather than defaulting to the use of the standard logo.
- Tweak         - We have removed the settings tab from the "Klarna Add-ons" page because its functionalities have been transferred to the plugin.
* Tweak         - We will now validate the API credentials based on the active mode, whether it's test or production. This enhancement should prevent the plugin from inaccurately attempting to verify production credentials when the test mode is in operation.

= 2023.06.28    - version 3.1.1 =
* Fix           - Fixed an issue with how we made our meta queries when trying to find orders based on a the Klarna session ID.
* Enhancement   - Added a validation to ensure that the order returned by our meta query actually is the correct order by verifying that the Klarna session ID stored matches the one we searched for.

= 2023.06.20    - version 3.1.0 =
* Feature       - The plugin now supports WooCommerce's "High-Performance Order Storage" ("HPOS") feature.
* Feature       - Added support for Hungary.
* Enhancement   - We will now use the WooCommerce JavaScript method $.scroll_to_notices() to scroll to notices when errors happen during checkout. This allows for custom overrides of the method. Thank you [@oxyc](https://github.com/oxyc)!
* Fix           - Fixed the compatibility with YITH Giftcards.
* Fix           - Fixed the compatibility with WooCommerce Advanced Shipping Packages


= 2023.05.16    - version 3.0.7 =
* Fix           - Fixed a critical error related to calculating the cart total sum. Previously, a fatal error would occur on certain PHP versions if a non-numeric item was encountered during the calculation (thanks @tobyaherbert!)
* Fix           - Fixed an issue where essential meta data required for proper order management was missing during the pay for order payment process.
* Fix           - Fixed an issue where the table rate shipping method used a different identifier during checkout compared to order management.

= 2023.04.11    - version 3.0.6 =
* Fix           - Fixed an issue where the client token would disappear due to a conflict with a third-party plugin, causing the Klarna payment options to not appear.
* Fix           - Pay for order should now work as expected.
* Fix           - When the order is being placed, the billing country should now be retrieved from the order directly. This should fix an issue with "Fluid Checkout PRO".
* Tweak         - API errors are now only displayed on the front-end if test mode is enabled. They will still be logged provided that logging is enabled.

= 2023.03.02    - version 3.0.5 =
* Fix           - Fixed an issue where using Smart Coupons would cause a BAD_VALUE if the coupon amount was greater than the total sum of the cart content.
* Fix           - Removed an extraneous comma which would result in a fatal error when using PHP version older than 7.3. Note: the minimum PHP version is 7.4.

= 2023.02.27    - version 3.0.4 =
* Fix           - Fixed an error message that could happen when updating your settings.
* Fix           - Fixed an issue where we would sometimes attempt to create sessions with Klarna for countries that you do not have settings for.
* Fix           - Fixed an issue in the logger where WordPress returns a boolean for the current WP_Hook instead of an array.

= 2023.02.24    - version 3.0.3 =
* Fix           - Fixed an issue with shipping not being present when loading the checkout page if the cart needs shipping.

= 2023.02.22    - version 3.0.2 =
* Fix           - Fixed an issue with cart fees not being processed properly, causing a potential error.
* Fix           - Fixed a notice caused by a new setting not being set.

= 2023.02.21    - version 3.0.1 =
* Fix           - Fixed an issue with the live api url for Klarna Payments.

= 2023.02.20    - version 3.0.0 =
* Feature       - Added support for WooCommerce Checkout blocks using Klarna Payments.
* Feature       - Added support for Romania.
* Feature       - Added a lot more filters to everything in the requests to Klarna, to make it easier to customize the data as needed.
* Enhancement   - Improved the logs for each request, with the ability to add a lot more information to each log. This will help with debugging issues faster.
* Enhancement   - Reduced the amount of requests needed to place an order with Klarna.
* Note          - This version contains a lot of major changes to the plugin, so please test thoroughly before updating to this version to ensure that it works as expected on your store.

= 2022.10.27    - version 2.12.1 =
* Fix           - Fixed a critical error when trying to clear the session.

= 2022.10.26    - version 2.12.0 =
* Feature       - Added support for "PW WooCommerce Gift Cards".
* Fix           – Fixed an issue where “null” is returned if the tax rate could not be retrieved.
* Tweak         - If Klarna Payments is enabled, it should now be available through the 'woocommerce_available_payment_gateways' filter. This is changed from being only available on the checkout page or when performing AJAX calls.
* Enhancement   – You can now use the ‘kp_locale’ filter to change the Klarna locale.

= 2022.09.27    - version 2.11.5 =
* Fix           - Fix the token fragment not being updated under certain conditions (thanks !@clifgriffin).
* Tweak         - It should no longer be any issue when KP and KCO are enabled simultaneously on the checkout.

= 2022.07.25    - version 2.11.4 =
* Fix           - Fix bug that prevent scripts from properly loading, making the checkout appear to freeze.

= 2022.07.25    - version 2.11.3 =
* Fix           - Fix undefined index.

= 2022.07.18    - version 2.11.2 =
* Fix           - Fix purchase country not being updated when the billing country is changed on the checkout page.

= 2022.07.13    - version 2.11.1 =
* Fix - Fixed an issue that would cause a critical error on versions of PHP older than 7.3. The WooCommerce team [strongly recommends](https://woocommerce.com/document/update-php-wordpress/) upgrading to PHP 7.4 for better performance and security.

= 2022.07.12    - version 2.11.0 =
* Enhancement   - Enhanced the checkout experience.
* Tweak         - Improved compatibility with third-party theme (thanks @swarnat!)

= 2021.05.30    - version 2.10.0 =
* Feature       - Add support for Greece locale (el_GR).
* Fix           - Fix incorrect shipping tax sometimes happening on non-integer VATs (thank you Avaroth!).
* Fix           - Fix "Internal server error" sometimes happening when the store's country and customer's country do not match region-wise.
* Fix           - Fix undefined index happening due to unsupported countries.
* Fix           - Fix issue where you could not switch between KP and KCO when both plugins were enabled in the checkout at the same time.
* Enhancement   - Klarna Payments is now available on the admin page which should improve compatibility with some third-party plugins.

= 2021.04.27    - version 2.9.1 =
* Fix           - Make sure that the checkout is fully unlocked after the Klarna popup window is closed by a customer.
* Fix           - Check if a country is supported before getting the currency. Fixes a undefined index error message.

= 2021.04.13    - version 2.9.0 =
* Feature       - Added support for Greece and Czech Republic.
* Enhancement   - The debug log messages that are saved on errors to the database are no longer autoloaded by us, and will only be loaded when asked for directly.
* Fix           - Fixed an issue with the Mexico support.
* Fix           - Fixed an issue that could occur where some plugins would strip the hashtag from the hex color in the settings, causing an error.

= 2021.01.19    - version 2.8.1 =
* Fix           - Fixed a potential fatal error when the create session request does not return a OK response.

= 2021.01.19    - version 2.8.0 =
* Feature       - Add support for Mexico.
* Fix           - Fixerd an issue causing the wrong payment method name to be set in some cases when using pay for order.
* Fix           - Fixed a error notice if the WooCommerce customer object is not set.

= 2021.12.07    - version 2.7.1 =
* Enhancement   - Countries that you have entered credentials for now show up on the status page.
* Enhancement   - We will now warn you if you have put Klarna into test mode without having any test credentials filled in.
* Enhancement   - Added an option to permanently remove the go-live banner on the admin page.
* Fix           - Fixed an issue that could let customers press the place order button multiple times that could cause double orders.

= 2021.11.17    - version 2.7.0 =
* Feature       - Add support for Ireland.
* Feature       - Add support for Portugal.
* Enhancement   - Add support for YITH Giftcards.
* Enhancement   - Add the request URL to the log for the plugin log. Thank you Maksim Kuzmin (github KuzMaxOriginal)
* Fix           - Fixed a notice on a permalinks check. Thank you Maksim Kuzmin (github KuzMaxOriginal)

= 2021.10.26    - version 2.6.1 =
* Fix           - Prevent any load events from happening during the checkout completion stage.
* Fix           - Fixed some undefined index errors.
* Tweak         - Change the Klarna documentation URL in the email setting.

= 2021.09.01    - version 2.6.0 =
* Feature       - Added support for the Polish market.
* Feature       - We will now save the last 15 requests to Klarna that had an API error and display them on the WooCommerce status page. This should help with getting error messages when you need to debug issues without going through the logs. These will also be in the status report that you can send to us for support tickets.
* Enhancement   - Added a warning message if you are not using pretty permalinks for your permalinks setting in WooCommerce.
* Enhancement   - Added translations for multiple languages for the text that is printed in Emails to customers when using Klarna as a payment method.
* Fix           - Removed a banner from the settings page that was added incorrectly.
* Fix           - Fixed compatibility issues with PHP 8.0 that would cause some error notices in the logs.

= 2021.06.16    - version 2.5.1 =
* Fix           - Fixed the logging of AJAX errors. Should no longer be logged as [object, Object].

= 2021.06.16    - version 2.5.0 =
* Feature       - Added support to have Klarna Payments working on a combined cart and checkout page.
* Fix           - Fixed an issue with pay for order causing it to not work.
* Fix           - Included wc-checkout and jquery-blockui as dependencies for our JavaScript file. If blockui was missing from the checkout page it could cause a JavaScript error.
* Fix           - We will no longer send a request to Klarna if the cart is empty.

= 2021.05.19    - version 2.4.4 =
* Enhancement   - Remove the need for URL fragments/hashtag URL when completing the checkout process.

= 2021.03.23    - version 2.4.3 =
* Fix           - Fixed an issue causing us to send a create session before totals had been calculated. This did not break anything, but caused a request to Klarna to fail in the background.

= 2021.03.19    - version 2.4.2 =
* Fix           - Fixed an incorrectly named variable causing an error on some pages.

= 2021.03.18    - version 2.4.1 =
* Fix           - Fixed a critical error when the settings for Klarna Payments has not been saved properly.

= 2021.03.16    - version 2.4.0 =
* Enhancement   - Change how and when we do calculations and send data to Klarna. Before we did this on the JS event "updated_checkout". This has been changed to rather do it after WooCommerce does their calculations, using the action "woocommerce_after_calculate_totals".
* Feature       - Added support for the plugin Checkout Addons due to the above change.
* Feature       - Added a filter on the order lines sent to Klarna, "kp_wc_api_order_lines". Thank you Ernesto Ruge (github the-infinity).
* Fix           - Removed unsupported color settings for the Klarna Payments iframe.

= 2021.03.09    - version 2.3.2 =
* Fix           - Fixed an issue with the checkout freezing trying to pay for an order and not using a Klarna payments method.

= 2021.03.04    - version 2.3.1 =
* Fix           - Fixed an issue with the checkout not being unblocked when Klarna rejected a purchase.

= 2021.03.02    - version 2.3.0 =
* Feature       - Added support for Pay for order links. You can now send payment links for orders that are created in the Admin page to customers and they can complete them through Klarna Payments. This can also be used to send previously unsuccessful orders to the customers again to have them retry the same purchase again.

= 2021.01.27    - version 2.2.0 =
* Feature       - Added support for Klarnas authentication callback. After a purchase is authenticated we schedule a check after 2 minutes to possibly complete an order where the customer was not properly returned to the checkout page from the 3DS step.
* Enhancement   - Klarna Addons now have better support for WooCommerce Admins navigation feature. Thank you to Joshua Flowers ( github joshuatf )!
* Enhancement   - Added additional links to support and documentation on the settings page for the payment method.
* Enhancement   - Added translation for the "What is Klarna?" string.

= 2020.11.10    - version 2.1.4 =
* Enhancement   - Added compatibility for Smart Coupons.
* Enhancement   - Improved coupon handling on the checkout.
* Enhancement   - Add a default icon if we are not on the checkout page and have an icon set from Klarna.
* Fix           - Fixed an issue with sending the wrong country code for the UK when testing credentials after saving settings.
* Fix           - Fixed an issue that caused the customer to be redirected to a 404 page on an error in the checkout.

= 2020.09.30    - version 2.1.3 =
* Enhancement   - Added proper user-agent to test-credentials check requests.
* Fix           - Fixed an issue with saving the incorrect value as the Klarna environment used. Caused Live orders to show as test orders in the Klarna Order management meta box.

= 2020.09.07    - version 2.1.2 =
* Enhancement   - Added compatibility with the plugin WooCommerce Gift Cards. https://woocommerce.com/products/gift-cards/
* Fix           - Fixed so you can now hide the Klarna banner from the admin pages.
* Fix           - Klarna banner no longer shows on all admin pages. Only the Dashboard and the Klarna Payments settings page.

= 2020.07.28    - version 2.1.1 =
* Fix           - Fixed support for Finish locale again.

= 2020.07.02    - version 2.1.0 =
* Feature       - Check if credentials are correct on saving them. If they are not an error message will be displayed with more information.
* Feature       - Added new countries to the plugin. We have now added support for BE, ES, IT, FR, NZ.
* Enhancement   - Updated admin page banners with a new design.

= 2020.06.02    - version 2.0.9 =
* Enhancement   - Removed fallback icons for payment methods. Could cause a timeout when we tried to verify a URL endpoint.
* Enhancement   - Updated all API requests to have a default timeout of 10 seconds.
* Enhancement    - Force payment category to be an array in the template. Prevents issues when updating from a 1.x version to 2.x.
* Fix           - Prevent errors on failed requests.
* Fix           - Removed the clearing of a snippet before logging requests. Caused errors for some people.

= 2020.05.15     - version 2.0.8 =
* Fix           - Modified redirect url set in process_payment function to improve checkout flow.

= 2020.04.09  	- version 2.0.7 =
* Fix			- Added security checks to the Klarna Addons page to prevent unauthorized changes to plugins.

= 2020.02.25  	- version 2.0.6 =
* Fix           - Fixed an issue with nonce calculation when creating an account on the checkout page after an order is placed.
* Fix			- Fixed an issue regarding how we handle a WP_Error, could cause a critical error for some users.
* Feature		- Added a setting to add Klarna information to the order confirmation email sent to a customer.
* Enhancement   - Changed the what is Klarna URL to klarna.com
* Enhancement   - Added the WooCommerce version to the user-agent for the api requests.
* Enhancement   - Changed the text in the order note on a failed auth call to say Authorization instead of Payment.

= 2020.01.22  	- version 2.0.5 =
* Fix           - Fixed so we are now sending iFrame options on update calls.
* Feature		- Added support for Australia.

= 2019.11.27  	- version 2.0.4 =
* Fix           - Better logic for handling null responses on update session API calls.
* Fix           - Switched the default for customer type to be Person instead of Business.

= 2019.11.20  	- version 2.0.3 =
* Fix			- Fixed a rare issue with client token being invalid if changing country from a non valid Klarna Payments country to a valid one and Klarna Payments was not the default payment method.
* Fix           - Fixed an issue with sessions not being cleared
* Fix           - Added round to order line shipping tax amount.
* Enhancement   - Removed an old filter that forced phone numbers to go through. No longer needed due to new architecture.

= 2019.11.07  	- version 2.0.2 =
* Fix			- Fixed an issue where Client tokens where not set correctly if KP was not set as the default gateway.

= 2019.11.04  	- version 2.0.1 =
* Fix           - Properly set testmode.

= 2019.11.04  	- version 2.0.0 =
* Enhancement   - Complete rewrite of the plugin structure.
* Enhancement   - Less requests being sent to Klarna for each purchase.
* Enhancement   - Added Canada as a supported country with CAD as the currency.

= 2019.10.22  	- version 1.9.2 =
* Enhancement   - Added separate error message to order if customer leaves the iframe by them selves.

= 2019.09.25  	- version 1.9.1 =
* Fix           - Added check to only add shipping to order lines if shipping is needed for the order.

= 2019.08.13  	- version 1.9.0 =
* Feature       - Added support for WooCommerce Store Credit plugin.
* Tweak         - Added console logging for Authorize ajax call.
* Tweak         - Changed shipping reference logic for order data sent to Klarna. To be better compatible with future versions of Klarna Order Management plugin.
* Fix           - Limit reference field sent to Klarna to 64 characters.

= 2019.08.13  	- version 1.8.4 =
* Fix           - Send address data to Klarna from checkout form on load call for US stores. Plugin rewrite caused payment method iframe not to be displayed for US stores.

= 2019.08.10  	- version 1.8.3 =
* Enhancement	- We now use order data for authorization calls. This prevents issues with difference in formating of adress details between create order and authorization.
* Enhancement	- Changed the text added to the order note to "Payment rejected by Klarna" on a failed authorization calls.
* Fix			- Fixed issue with Sofort, removed a flag that was not needed to be sent with the authorization call.
* Fix			- Fixed an issue where billing_company field could softblock the checkout.
* Fix			- Get currency from the order instead of the WooCommerce default.

= 2019.07.31  	- version 1.8.2 =
* Fix			- Added handling for failed authorization calls.

= 2019.07.30  	- version 1.8.1 =
* Enhancement	- Improved JavaScript selectors for some elements. Should increase compatibility with custom themes.
* Enhancement	- Added a failsafe for orders not properly being placed with Klarna.
* Fix			- Fixed issue when it comes to separate sales tax for American merchants.

= 2019.07.23  	- version 1.8.0 =
* Feature		- Full rewrite of the order flow. Should now be more compatible with other plugin and themes.
* Feature		- Improved debug logging.
* Feature		- Added support for a lot more locales.
* Tweak         - Updated title description for Klarna Payments settings.
* Misc			- Cleaned up JS code. Removed unused functionality.

= 2019.06.11  	- version 1.7.0 =
* Feature       - Added new Klarna Add-ons page.
* Feature       - Added Klarna On-site Messaging & Klarna order management as available add-ons.

= 2019.06.03  	- version 1.6.5 =
* Tweak			- Improved logging for debugging purpose.
* Tweak			- Added support for Swedish locale for Finish stores.
* Tweak         - No longer tries to send company name on a B2C purchase.

= 2019.02.06  	- version 1.6.4 =
* Tweak			- Removed validation of required fields in the Payment method area. Caused an issue with Authorize.net payment gateway.
* Tweak         - Removed the disable on the Place order button since it is no longer needed to catch invalid fields.
* Tweak         - Changed JS library endpoint.
* Tweak         - Added extended description to the payment method title to clarify what it does.

= 2018.11.27  	- version 1.6.3 =
* Feature		- Added setting to hide "What is Klarna?" link.
* Tweak			- Added filter wc_kp_remove_postcode_spaces to enable removing whitespace from postcode posted to Klarna.
* Tweak			- Removed update order on visibility change.
* Tweak			- Default customer type to b2c if setting is not saved in db.
* Tweak			- Plugin WordPress 5.0 compatible.
* Fix			- Added support for additional required fields (other than Woo standard) on checkout. Prevents Klarna iframe from showing before all fields are entered.
* Fix			- Narrowed search for checkout field changes. Prevents some themes from entering infinite loop that loads the Klarna iframe.
* Fix			- Made payment method title editable again.
* Fix			- Add round to fees sent to Klarna.

= 2018.10.19  	- version 1.6.2 =
* Enhancement 	- Changed so all payment methods have the same ID in frontend as in the factory gateway. Adds support for payment gateway based fees and similar plugins.
* Fix 			- Fixed no tax being applied to negative fee.

= 2018.09.25  	- version 1.6.1 =
* Fix		    - Fixed 409 error caused by missing Organization name field.
* Fix		    - Better support for Switzerland.

= 2018.09.20  	- version 1.6.0 =
* Feature		- Added support for B2B purchases.
* Feature		- Added support Switzerland.

= 2018.08.30  	- version 1.5.4 =
* Tweak			- Added Payment method name settings field.
* Tweak			- Added filter wc_klarna_payments_default_checkout_fields. Makes it possible to select which checkout fields should be used when sending customer data via javascript to Klarna.
* Tweak			- Logging improvments.

= 2018.08.17  	- version 1.5.3 =
* Tweak			- Added filter kp_wc_api_request_args to be able to override order data sent to Klarna.
* Tweak			- Added filter wc_klarna_payments_available_payment_categories to be able to override wich payment methods that should be available.
* Tweak			- Logging improvements in klarna_payments_session_ajax_update function if request fails.
* Tweak			- Added button for hiding Klarna banner in WP admin. Stays hidden for 6 days and then reappears again (if plugin still is in test mode).
* Fix			- KP payment method not available on Order pay page (to avoid compatibility issues with Realex payment plugin).

= 2018.07.23  	- version 1.5.2 =
* Tweak			- Add max width to payment method icons.
* Enhancement	- Added Klarna LEAP functionality (URL's for new customer signup & onboarding).
* Fix			- Added fallback image for 404 on payment gateway icon URL.

= 2018.06.21  	- version 1.5.1 =
* Tweak			- Payment gateway icons now fetched from Klarnas CDN.

= 2018.06.08  	- version 1.5.0 =
* Feature		- Switches to Klarnas new /payments endpoint. Displays each Klarna payment method as its own payment option in checkout.
* Feature		- Added support for wp_add_privacy_policy_content (for GDPR compliance). More info: https://core.trac.wordpress.org/attachment/ticket/43473/PRIVACY-POLICY-CONTENT-HOOK.md.
* Tweak			- Switches to $product->get_name() for Klara order line name.
* Tweak			- Adds Klarna dashboard banners and settings page sidebar.
* Tweak			- Added PHP version and Krokedil to user agent.
* Tweak			- Only log messages if enabled in settings.
* Tweak			- Added logging of error response in Klarna create & update session.
* Tweak			- Added function to hide iframes when not needed.
* Tweak			- Added action klarna_payments_template to template. Action used in plugin to maybe create or update session.
* Fix			- Changes the check in set_klarna_country(). No longer uses is_checkout(). Just check for customer country if WC_Customer exist.

= 2018.01.29  	- version 1.4.2 =
* Fix           - Cleans up translation strings.
* Enhancement   - process_payment method refactoring.

= 2018.01.25  	- version 1.4.1 =
* Fix           - Fixes WC 3.3 notices.
* Tweak         - Stores Klarna order transaction ID as soon as possible.
* Tweak         - Adds "can't edit order" admin note.

= 1.0 =
* Initial release.
