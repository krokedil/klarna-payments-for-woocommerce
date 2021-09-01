=== Klarna Payments for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 5.8.0
Requires PHP: 5.6
WC requires at least: 3.4.0
WC tested up to: 5.6.0
Stable tag: trunk
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
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/collection/126-klarna-payments/).

== Frequently Asked Questions ==
= Which countries does this payment gateway support? =
Klarna Payments works for merchants in Sweden, Denmark, Finland, Norway, Germany, Austria, the Netherlands, UK and United States.

= Where can I find Klarna Payments for WooCommerce documentation? =
For help setting up and configuring Klarna Payments for WooCommerce please refer to our [documentation](https://docs.krokedil.com/collection/126-klarna-payments/).

= Are there any specific requirements? =
* WooCommerce 3.3.0 or newer is required.
* PHP 5.6 or higher is required.
* A SSL Certificate is required.

== Changelog ==
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
