=== Klarna Payments for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 4.9.6
Requires PHP: 5.6
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Klarna offers three payment methods. Pay now, Pay later and Slice it. It offers your consumers to try before they buy, finance purchases on your store, or make use of other payment options made available by Klarna.

== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Documentation ==
http://docs.krokedil.com/documentation/klarna-payments-woocommerce/

== Changelog ==
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
