# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `kp_plugin_features_initialized`

*Triggers after the features class has been initialized and can be used.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$features` | `array` | The features and their availability.

Examples: 
- [Run code after a specific feature is available.](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-klarna-feature-availability-is-loaded)

Source: [./src/PluginFeatures.php](../src/PluginFeatures.php), [line 104](../src/PluginFeatures.php#L104-L110)


---
### `osm_shortcode_added`

*Triggers when the Klarna on-site messaging shortcode is rendered on the frontend.*


Source: [./src/OnsiteMessaging/Shortcode.php](../src/OnsiteMessaging/Shortcode.php), [line 25](../src/OnsiteMessaging/Shortcode.php#L25-L28)


---
### `kom_meta_action_options`

*Triggers inside the order actions dropdown, allowing additional action options to be rendered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The ID of the order being considered.
`$klarna_order` | `object` | The Klarna order object associated with this order.
`$actions` | `array` | The available order management actions and their enabled state.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#add-custom-actions-to-the-kom-metabox)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 268](../src/OrderManagement/MetaBox.php#L268-L276)


---
### `kom_meta_action_tips`

*Triggers inside the order action help tip, allowing tooltip text for custom actions to be output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The ID of the order being considered.
`$klarna_order` | `object` | The Klarna order object associated with this order.
`$actions` | `array` | The available order management actions and their enabled state.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#add-tooltip-text-for-kom-metabox-actions)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 284](../src/OrderManagement/MetaBox.php#L284-L292)


---
### `kom_meta_no_actions`

*Triggers when no order management actions are available, allowing custom markup to be rendered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The ID of the order being considered.
`$klarna_order` | `object` | The Klarna order object associated with this order.
`$actions` | `array` | The available order management actions and their enabled state.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#add-content-when-no-kom-actions-are-available)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 299](../src/OrderManagement/MetaBox.php#L299-L307)


---
### `siwk_merge_with_existing_user`

*Triggers after Klarna user data has been merged into an existing WooCommerce user.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$user_id` | `int` | The ID of the merged user.
`$userdata` | `array` | The user data from Klarna used to update the user.

Source: [./src/SignInWithKlarna/User.php](../src/SignInWithKlarna/User.php), [line 156](../src/SignInWithKlarna/User.php#L156-L162)


---
### `wc_klarna_payments_pending`

*Triggers when a One Step Checkout payment could not be completed and the order is left pending review.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$response` | `array` | The place order response from Klarna.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review)

Source: [./src/ExpressCheckout/KECOneStepIntegration.php](../src/ExpressCheckout/KECOneStepIntegration.php), [line 59](../src/ExpressCheckout/KECOneStepIntegration.php#L59-L66)


---
### `wc_klarna_pending`

*Triggers when a One Step Checkout payment is left pending review. Fires alongside klarna_payments_pending.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$response` | `array` | The place order response from Klarna.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review)

Source: [./src/ExpressCheckout/KECOneStepIntegration.php](../src/ExpressCheckout/KECOneStepIntegration.php), [line 68](../src/ExpressCheckout/KECOneStepIntegration.php#L68-L75)


---
### `kec_cancel_order`

*Triggers when a Klarna Express Checkout order is cancelled due to an expired payment request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order` | `\WC_Order` | The WooCommerce order object.
`$interoperability_token` | `string` | The Klarna interoperability token.
`$interoperability_data` | `array` | The interoperability data. Empty when cancelling.
`$state` | `string` | The payment state reported by Klarna.
`$payload` | `array` | The payload data from Klarna.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-a-kec-payment-expires-or-is-cancelled)

Source: [./src/ExpressCheckout/KECOneStepIntegration.php](../src/ExpressCheckout/KECOneStepIntegration.php), [line 101](../src/ExpressCheckout/KECOneStepIntegration.php#L101-L111)


---
### `kec_process_order`

*Triggers when a completed Klarna Express Checkout payment notification is processed and no acquiring partner integration handles it.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order` | `\WC_Order` | The WooCommerce order object.
`$interoperability_token` | `string` | The Klarna interoperability token.
`$interoperability_data` | `array` | The interoperability data. Empty for this event.
`$state` | `string` | The payment state reported by Klarna.
`$payload` | `array` | The payload data from Klarna.

Source: [./src/ExpressCheckout/Api/Notifications/PaymentStateCompleted.php](../src/ExpressCheckout/Api/Notifications/PaymentStateCompleted.php), [line 60](../src/ExpressCheckout/Api/Notifications/PaymentStateCompleted.php#L60-L69)


---
### `kec_cancel_order`

*Triggers when a Klarna Express Checkout order is cancelled due to an expired payment request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order` | `\WC_Order` | The WooCommerce order object.
`$interoperability_token` | `string` | The Klarna interoperability token.
`$interoperability_data` | `array` | The interoperability data. Empty when cancelling.
`$state` | `string` | The payment state reported by Klarna.
`$payload` | `array` | The payload data from Klarna.

Source: [./src/ExpressCheckout/Api/Notifications/PaymentStateExpired.php](../src/ExpressCheckout/Api/Notifications/PaymentStateExpired.php), [line 45](../src/ExpressCheckout/Api/Notifications/PaymentStateExpired.php#L45-L54)


---
### `klarna_notification_{$event_type}_{$event_version}`

*Triggers when a Klarna notification is received but no handler is registered for its event type and version.*

The dynamic portion of the hook name, `$event_type` and `$event_version`, refers to the Klarna event type and version, for example `payment_completed_v2`.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The full notification body received from Klarna.

Source: [./src/ExpressCheckout/Api/Controllers/Notifications.php](../src/ExpressCheckout/Api/Controllers/Notifications.php), [line 87](../src/ExpressCheckout/Api/Controllers/Notifications.php#L87-L94)


---
### `klarna_notification_{$event_type}_{$event_version}`

*Triggers after a Klarna notification has been handled by its registered handler.*

The dynamic portion of the hook name, `$event_type` and `$event_version`, refers to the Klarna event type and version, for example `payment_completed_v2`.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The full notification body received from Klarna.

Source: [./src/ExpressCheckout/Api/Controllers/Notifications.php](../src/ExpressCheckout/Api/Controllers/Notifications.php), [line 100](../src/ExpressCheckout/Api/Controllers/Notifications.php#L100-L107)


---
### `kec_auth_callback_processed`

*Triggers after the two-step Klarna Express Checkout auth callback has been processed and the customer address and client token stored in the session.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `array` | The auth callback result from Klarna, including the approval status, client token and collected shipping address.

Source: [./src/ExpressCheckout/AJAX.php](../src/ExpressCheckout/AJAX.php), [line 173](../src/ExpressCheckout/AJAX.php#L173-L178)


---
### `wc_klarna_payments_accepted`

*Triggers after an accepted Klarna Payments order has been completed and its meta data stored.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$decoded` | `array` | The decoded Klarna order data.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-a-klarna-payment-is-accepted)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 162](../includes/kp-functions.php#L162-L169)


---
### `wc_klarna_accepted`

*Alias of wc_klarna_payments_accepted. Fires at the same time with the same arguments for cross-plugin compatibility.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$decoded` | `array` | The decoded Klarna order data.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-a-klarna-payment-is-accepted)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 171](../includes/kp-functions.php#L171-L178)


---
### `wc_klarna_payments_pending`

*Triggers after a pending Klarna Payments order has been set to on-hold for review.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$decoded` | `array` | The decoded Klarna order data.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 200](../includes/kp-functions.php#L200-L207)


---
### `wc_klarna_pending`

*Alias of wc_klarna_payments_pending. Fires at the same time with the same arguments for cross-plugin compatibility.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$decoded` | `array` | The decoded Klarna order data.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-pending-review)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 209](../includes/kp-functions.php#L209-L216)


---
### `wc_klarna_payments_rejected`

*Triggers after a rejected Klarna Payments order has had its status updated.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$decoded` | `array` | The decoded Klarna order data.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-rejected)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 243](../includes/kp-functions.php#L243-L250)


---
### `wc_klarna_rejected`

*Alias of wc_klarna_payments_rejected. Fires at the same time with the same arguments for cross-plugin compatibility.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$decoded` | `array` | The decoded Klarna order data.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-a-klarna-payment-is-rejected)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 252](../includes/kp-functions.php#L252-L259)


---
### `kp_after_place_order`

*Triggers after the place order request has been sent to Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$response` | `array\|\WP_Error` | The response from the Klarna place order request.
`$order_id` | `string` | The WooCommerce order ID.
`$auth_token` | `string` | The Klarna auth token for the session.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#after-the-place-order-request-completes)

Source: [./classes/class-kp-api.php](../classes/class-kp-api.php), [line 102](../classes/class-kp-api.php#L102-L110)


---
### `kp_modal_closed`

*Triggers after an order note has been added when the Klarna authorization modal is closed or rejected.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order` | `\WC_Order` | The WooCommerce order object.
`$show_form` | `bool` | Whether the checkout form should be shown again to the customer.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#handle-the-klarna-modal-closed-event)

Source: [./classes/class-kp-ajax.php](../classes/class-kp-ajax.php), [line 164](../classes/class-kp-ajax.php#L164-L171)


---
### `wc_klarna_notification_listener`

*Triggers on the Klarna notification endpoint, allowing the Klarna Order Management plugin to process pending orders.*


Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#when-klarna-sends-a-push-notification)

Source: [./classes/class-wc-gateway-klarna-payments.php](../classes/class-wc-gateway-klarna-payments.php), [line 569](../classes/class-wc-gateway-klarna-payments.php#L569-L574)


---
## Filters

### `kec_compatible_blocks`

*Filter the compatible blocks for Klarna Express Checkout.*


Source: [./src/ExpressCheckout.php](../src/ExpressCheckout.php), [line 208](../src/ExpressCheckout.php#L208-L211)


---
### `kosm_data_client_id`

*Filters the Klarna client ID used for the on-site messaging placements.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data_client_id` | `string` | The Klarna data client ID.

Source: [./src/OnsiteMessaging/KlarnaOnsiteMessaging.php](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php), [line 142](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php#L142-L147)


---
### `kosm_show_everywhere`

*Filters whether to enqueue the on-site messaging scripts on all pages, rather than only on product, cart and shortcode pages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$show_everywhere` | `bool` | Whether to enqueue the scripts on all pages. Default false.

Source: [./src/OnsiteMessaging/KlarnaOnsiteMessaging.php](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php), [line 164](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php#L164-L169)


---
### `kosm_region_library`

*Filters the Klarna library region used to load the on-site messaging script.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$region` | `string` | The library region. One of 'eu-library', 'na-library' or 'oc-library'.

Source: [./src/OnsiteMessaging/KlarnaOnsiteMessaging.php](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php), [line 186](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php#L186-L191)


---
### `kosm_data_client_id`

*Filters the Klarna client ID used for the on-site messaging placements.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$client_id` | `string` | The Klarna data client ID.

Source: [./src/OnsiteMessaging/KlarnaOnsiteMessaging.php](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php), [line 192](../src/OnsiteMessaging/KlarnaOnsiteMessaging.php#L192-L197)


---
### `klarna_onsite_messaging_cart_target`

*Filters the WooCommerce hook the on-site messaging placement is attached to on the cart page.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target` | `string` | The action hook name where the placement is rendered.

Source: [./src/OnsiteMessaging/Pages/Cart.php](../src/OnsiteMessaging/Pages/Cart.php), [line 47](../src/OnsiteMessaging/Pages/Cart.php#L47-L52)


---
### `klarna_onsite_messaging_cart_priority`

*Filters the priority used when attaching the on-site messaging placement on the cart page.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$priority` | `int` | The hook priority. Default 5.

Source: [./src/OnsiteMessaging/Pages/Cart.php](../src/OnsiteMessaging/Pages/Cart.php), [line 53](../src/OnsiteMessaging/Pages/Cart.php#L53-L58)


---
### `klarna_onsite_messaging_product_target`

*Filters the WooCommerce hook the on-site messaging placement is attached to on the product page.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target` | `string` | The action hook name. Default 'woocommerce_single_product_summary'.

Source: [./src/OnsiteMessaging/Pages/Product.php](../src/OnsiteMessaging/Pages/Product.php), [line 68](../src/OnsiteMessaging/Pages/Product.php#L68-L73)


---
### `klarna_onsite_messaging_product_priority`

*Filters the priority used when attaching the on-site messaging placement on the product page.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$priority` | `int` | The hook priority.

Source: [./src/OnsiteMessaging/Pages/Product.php](../src/OnsiteMessaging/Pages/Product.php), [line 74](../src/OnsiteMessaging/Pages/Product.php#L74-L79)


---
### `kosm_hide_placement`

*Filters whether to hide the Klarna on-site messaging placement.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$hide` | `bool` | Whether to hide the placement. Default false.
`$purchase_amount` | `int` | The purchase amount, in minor units, used for the placement.

Source: [./src/OnsiteMessaging/Utility.php](../src/OnsiteMessaging/Utility.php), [line 88](../src/OnsiteMessaging/Utility.php#L88-L94)


---
### `kosm_locale`

*Filters the locale (IETF BCP 47 language and region tag) used for the on-site messaging placement.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string\|false` | The resolved locale, or false when no locale could be determined for the currency.

Source: [./src/OnsiteMessaging/Utility.php](../src/OnsiteMessaging/Utility.php), [line 297](../src/OnsiteMessaging/Utility.php#L297-L302)


---
### `kosm_default_euro_locale`

*Filters the default locale used for the EUR currency when no country code can be identified.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default_locale` | `string` | The default EUR locale. Default 'en-DE'.

Source: [./src/OnsiteMessaging/Utility.php](../src/OnsiteMessaging/Utility.php), [line 316](../src/OnsiteMessaging/Utility.php#L316-L321)


---
### `kosm_force_euro_locale`

*Filters whether to always use the default EUR locale regardless of the customer's country code.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$force` | `bool` | Whether to force the default EUR locale. Default false.

Source: [./src/OnsiteMessaging/Utility.php](../src/OnsiteMessaging/Utility.php), [line 323](../src/OnsiteMessaging/Utility.php#L323-L328)


---
### `kom_meta_environment`

*Filters the Klarna environment shown in the order metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$environment` | `string` | The Klarna environment, either 'test' or 'live'.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-environment-label-in-the-kom-metabox)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 163](../src/OrderManagement/MetaBox.php#L163-L169)


---
### `kom_meta_order_status`

*Filters the Klarna order status shown in the order metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_status` | `string` | The status of the Klarna order.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-order-status-label-in-the-kom-metabox)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 175](../src/OrderManagement/MetaBox.php#L175-L181)


---
### `kom_meta_payment_method`

*Filters the initial payment method description shown in the order metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$method_description` | `string` | The Klarna initial payment method description.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-payment-method-label-in-the-kom-metabox)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 187](../src/OrderManagement/MetaBox.php#L187-L193)


---
### `klarna_om_skip_matching_reference_orders`

*Filters whether to skip looking up and displaying orders that share the same Klarna transaction ID.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$skip` | `bool` | Whether to skip the matching reference orders lookup. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#disable-lookup-of-orders-sharing-a-klarna-transaction-id)

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 661](../src/OrderManagement/MetaBox.php#L661-L667)


---
### `klarna_base_region`

*Filters the Klarna API region used to build the order management API base URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$region` | `string` | The Klarna API region, derived from the order's country.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-base-region-for-api-requests)

Source: [./src/OrderManagement/Request/Request.php](../src/OrderManagement/Request/Request.php), [line 142](../src/OrderManagement/Request/Request.php#L142-L148)


---
### `kom_request_timeout`

*Filters the timeout, in seconds, for Klarna order management API requests.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-order-management-request-timeout)

Source: [./src/OrderManagement/Request/Request.php](../src/OrderManagement/Request/Request.php), [line 332](../src/OrderManagement/Request/Request.php#L332-L338)


---
### `kom_order_capture_args`

*Filters the request body sent to Klarna when capturing an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The capture request body, including the captured amount and order lines.
`$order_id` | `int` | The WooCommerce order ID.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-order-capture-request)

Source: [./src/OrderManagement/Request/Post/RequestPostCapture.php](../src/OrderManagement/Request/Post/RequestPostCapture.php), [line 69](../src/OrderManagement/Request/Post/RequestPostCapture.php#L69-L76)


---
### `kom_line_item_product_type`

*Filters the product type used for a refund order line when the product no longer exists in WooCommerce.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$type` | `string` | The product type to use. Default 'physical'.
`$item` | `\WC_Order_Item_Product` | The WooCommerce order item being refunded.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-product-type-for-refunds-of-missing-products)

Source: [./src/OrderManagement/Request/Post/RequestPostRefund.php](../src/OrderManagement/Request/Post/RequestPostRefund.php), [line 154](../src/OrderManagement/Request/Post/RequestPostRefund.php#L154-L161)


---
### `kom_refund_order_args`

*Filters the refund order lines sent to Klarna when refunding an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The refund order lines.
`$order_id` | `int` | The WooCommerce order ID.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-order-refund-request)

Source: [./src/OrderManagement/Request/Post/RequestPostRefund.php](../src/OrderManagement/Request/Post/RequestPostRefund.php), [line 281](../src/OrderManagement/Request/Post/RequestPostRefund.php#L281-L288)


---
### `kom_order_update_args`

*Filters the request body sent to Klarna when updating an order's authorization.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The update request body, including order lines and amounts.
`$order_id` | `int` | The WooCommerce order ID.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-order-update-request)

Source: [./src/OrderManagement/Request/Patch/RequestPatchUpdate.php](../src/OrderManagement/Request/Patch/RequestPatchUpdate.php), [line 50](../src/OrderManagement/Request/Patch/RequestPatchUpdate.php#L50-L57)


---
### `siwk_redirect_url`

*Filters the URL the customer is redirected to after the Klarna sign-in callback.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$redirect_url` | `string` | The redirect URL. Defaults to the shop page permalink.
`$page` | `string\|null` | The page context. Null in the redirect callback.

Source: [./src/SignInWithKlarna/Redirect.php](../src/SignInWithKlarna/Redirect.php), [line 61](../src/SignInWithKlarna/Redirect.php#L61-L67)


---
### `siwk_callback_url`

*Filters the callback URL registered with Klarna for the Sign in with Klarna redirect flow.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$callback_url` | `string` | The callback URL.

Source: [./src/SignInWithKlarna/Redirect.php](../src/SignInWithKlarna/Redirect.php), [line 98](../src/SignInWithKlarna/Redirect.php#L98-L103)


---
### `siwk_set_gateway_to_klarna`

*Filters whether to set Klarna as the chosen payment method after signing in with Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$set_gateway` | `bool` | Whether to set the highest-ordered Klarna gateway (Klarna Checkout or Klarna Payments) as the chosen payment method. Default false.

Source: [./src/SignInWithKlarna/User.php](../src/SignInWithKlarna/User.php), [line 89](../src/SignInWithKlarna/User.php#L89-L94)


---
### `siwk_userdata`

*Filters the user data extracted from the Klarna ID token before it is used to create or update a WooCommerce user.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$userdata` | `array` | The user data, including login, email and billing/shipping meta.

Source: [./src/SignInWithKlarna/User.php](../src/SignInWithKlarna/User.php), [line 250](../src/SignInWithKlarna/User.php#L250-L255)


---
### `siwk_{$setting}`

*Filters the optional OAuth scopes requested for Sign in with Klarna.*

The dynamic portion of the hook name, `$setting`, is the setting name without the `siwk_` prefix, here `scope`. Required scopes are always prepended and cannot be removed via this filter.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$scope` | `string` | The space-separated list of enabled optional scopes.

Source: [./src/SignInWithKlarna/Settings.php](../src/SignInWithKlarna/Settings.php), [line 131](../src/SignInWithKlarna/Settings.php#L131-L138)


---
### `siwk_{$setting}`

*Filters the value of a Sign in with Klarna setting.*

The dynamic portion of the hook name, `$setting`, is the setting name without the `siwk_` prefix, for example `market`, `client_id` or `button_theme`.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$value` | `string\|int` | The setting value.

Source: [./src/SignInWithKlarna/Settings.php](../src/SignInWithKlarna/Settings.php), [line 142](../src/SignInWithKlarna/Settings.php#L142-L149)


---
### `siwk_locale`

*Filters the locale used for Sign in with Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string` | The locale, formatted with a hyphen, for example 'en-US'.

Source: [./src/SignInWithKlarna/Settings.php](../src/SignInWithKlarna/Settings.php), [line 351](../src/SignInWithKlarna/Settings.php#L351-L356)


---
### `klarna_base_region`

*Filters the Klarna API region used to build the Sign in with Klarna JWKS URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$region` | `string` | The Klarna API region, derived from the store's country. Either 'eu' or 'na'.

Source: [./src/SignInWithKlarna/JWT.php](../src/SignInWithKlarna/JWT.php), [line 68](../src/SignInWithKlarna/JWT.php#L68-L73)


---
### `siwk_redirect_url`

*Filters the URL the customer is redirected to after signing in with Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$redirect_url` | `string` | The redirect URL.
`$page` | `string` | The page context, either 'cart' or 'shop'.

Source: [./src/SignInWithKlarna/Ajax.php](../src/SignInWithKlarna/Ajax.php), [line 100](../src/SignInWithKlarna/Ajax.php#L100-L106)


---
### `klarna_register_api_controller`

*Filters the list of REST API controllers registered by the plugin.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$controllers` | `\Krokedil\Klarna\Api\Controllers\Controller[]` | The list of API controllers to register. Default empty array.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#register-a-custom-klarna-rest-api-controller)

Source: [./src/Api/Registry.php](../src/Api/Registry.php), [line 28](../src/Api/Registry.php#L28-L34)


---
### `kec_acquiring_partner_integrations`

*Filters the list of acquiring partner integrations available to handle Klarna Express Checkout notifications.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$integrations` | `\Krokedil\Klarna\ExpressCheckout\Interfaces\AcquiringPartnerIntegration[]` | The registered acquiring partner integrations. Default empty array.

Source: [./src/ExpressCheckout/Api/Notifications/Handler.php](../src/ExpressCheckout/Api/Notifications/Handler.php), [line 47](../src/ExpressCheckout/Api/Notifications/Handler.php#L47-L52)


---
### `kec_one_step_redirect_wait_max_attempts`

*Filters the maximum number of attempts to wait for the order redirect URL to be set.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$max_attempts` | `int` | The maximum number of polling attempts. Default 20.

Source: [./src/ExpressCheckout/OneStepCheckout.php](../src/ExpressCheckout/OneStepCheckout.php), [line 92](../src/ExpressCheckout/OneStepCheckout.php#L92-L97)


---
### `kec_one_step_redirect_wait_sleep_time_mu`

*Filters the wait time, in microseconds, between attempts to read the order redirect URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$sleep_time` | `int` | The wait time between attempts, in microseconds. Default 500000.

Source: [./src/ExpressCheckout/OneStepCheckout.php](../src/ExpressCheckout/OneStepCheckout.php), [line 99](../src/ExpressCheckout/OneStepCheckout.php#L99-L104)


---
### `kec_one_step_default_redirect_url`

*Filters the fallback redirect URL used when the order redirect URL is not set in time.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default_redirect_url` | `string` | The fallback redirect URL. Defaults to the order received URL.
`$order` | `\WC_Order` | The WooCommerce order.
`$kec_unique_id` | `string` | The KEC unique ID.

Source: [./src/ExpressCheckout/OneStepCheckout.php](../src/ExpressCheckout/OneStepCheckout.php), [line 107](../src/ExpressCheckout/OneStepCheckout.php#L107-L114)


---
### `siwk_market`

*Filters the Klarna market used for the Sign in with Klarna button.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$market` | `string` | The market, as a two-letter country code.

Source: [./src/SignInWithKlarna.php](../src/SignInWithKlarna.php), [line 167](../src/SignInWithKlarna.php#L167-L172)


---
### `siwk_environment`

*Filters the environment used for the Sign in with Klarna button.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$environment` | `string` | The Klarna environment, either 'playground' or 'production'.

Source: [./src/SignInWithKlarna.php](../src/SignInWithKlarna.php), [line 174](../src/SignInWithKlarna.php#L174-L179)


---
### `siwk_client_id`

*Filters the Klarna client ID used for the Sign in with Klarna button.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$client_id` | `string` | The Klarna client ID.

Source: [./src/SignInWithKlarna.php](../src/SignInWithKlarna.php), [line 181](../src/SignInWithKlarna.php#L181-L186)


---
### `kom_allowed_update_statuses`

*Filters the WooCommerce order statuses for which Klarna order updates are allowed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$statuses` | `string[]` | The allowed order statuses. Default array( 'on-hold' ).

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-order-statuses-that-allow-klarna-order-updates)

Source: [./src/OrderManagement.php](../src/OrderManagement.php), [line 362](../src/OrderManagement.php#L362-L368)


---
### `klarna_applied_return_fees`

*Filters the return fees applied to a Klarna refund, used to build the refund order note.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$applied_return_fees` | `array` | The applied return fees, with 'amount' and 'tax_amount' keys.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#inspect-the-return-fees-applied-to-a-klarna-refund)

Source: [./src/OrderManagement.php](../src/OrderManagement.php), [line 632](../src/OrderManagement.php#L632-L638)


---
### `kp_blocks_order_button_label_free`

*Filters the checkout pay button label shown for Klarna when the cart total is zero.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$label` | `string` | The pay button label.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-label-on-the-woocommerce-pay-button)

Source: [./klarna-payments-for-woocommerce.php](../klarna-payments-for-woocommerce.php), [line 584](../klarna-payments-for-woocommerce.php#L584-L590)


---
### `kp_blocks_order_button_label`

*Filters the checkout pay button label shown for Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$label` | `string` | The pay button label.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-label-on-the-woocommerce-pay-button)

Source: [./klarna-payments-for-woocommerce.php](../klarna-payments-for-woocommerce.php), [line 594](../klarna-payments-for-woocommerce.php#L594-L600)


---
### `kp_get_customer_type`

*Filters the Klarna customer object added to the request arguments.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$customer` | `array` | The Klarna customer object.
`$customer_type` | `string` | The customer type from the settings.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-customer-object-for-klarna-payments)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 52](../includes/kp-functions.php#L52-L59)


---
### `wc_klarna_payments_country`

*Filters the country code used to determine the Klarna market for the customer.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$country` | `string` | The two-letter country code.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-purchase-country-sent-to-klarna)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 74](../includes/kp-functions.php#L74-L80)


---
### `wc_klarna_payments_country`

*Filters the country code used to determine the Klarna market for the customer.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$country` | `string` | The two-letter country code.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-purchase-country-sent-to-klarna)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 88](../includes/kp-functions.php#L88-L94)


---
### `wc_klarna_payments_country`

*Filters the country code used to determine the Klarna market for the customer.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$country` | `string` | The two-letter country code.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-purchase-country-sent-to-klarna)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 102](../includes/kp-functions.php#L102-L108)


---
### `kp_order_rejected_status`

*Filters the WooCommerce order status applied to a rejected Klarna Payments order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$status` | `string` | The order status to set. Default 'failed'.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-order-status-for-rejected-payments)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 234](../includes/kp-functions.php#L234-L240)


---
### `kp_locale`

*Filters the locale string sent to Klarna, formatted to match the Klarna API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string` | The formatted locale, for example 'en-GB'.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#force-locale-to-a-specific-country-and-language)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 284](../includes/kp-functions.php#L284-L290)


---
### `wc_klarna_payments_combined_payment_method_title`

*Filters the title of the combined Klarna payment method displayed in the checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$title` | `string` | The combined Klarna payment method title.

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 664](../includes/kp-functions.php#L664-L669)


---
### `klarna_get_customer_type`

*Filters the customer type used for Klarna Payments.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$customer_type` | `string` | The customer type from the settings.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-customer-type-b2cb2b)

Source: [./includes/kp-functions.php](../includes/kp-functions.php), [line 706](../includes/kp-functions.php#L706-L712)


---
### `kp_websdk_data_client_id`

*Filters the data-client-id attribute value injected into the Klarna Web SDK v1 (api.js) script tag.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$client_id` | `string` | The Klarna client ID, for example 'klarna_live_client_xxx'.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-klarna-web-sdk-client-id)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 79](../classes/class-kp-assets.php#L79-L85)


---
### `kp_websdk_v2_data_attributes`

*Filters the HTML attributes added to the Klarna Web SDK v2 (klarna.mjs) script module tag.*

Each key-value pair in the returned array becomes an HTML attribute. A null value adds the key as a boolean attribute, for example 'defer'.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attributes` | `array` | Associative array of attribute name => value pairs. Default array( 'defer' => null ).

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-web-sdk-v2-script-attributes)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 90](../classes/class-kp-assets.php#L90-L103)


---
### `kp_websdk_v1_data_attributes`

*Filters the HTML attributes added to the Klarna Web SDK v1 (klarna_websdk_v1) script tag.*

Each key-value pair in the returned array becomes an HTML attribute. A null value adds the key as a boolean attribute, for example 'defer'.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attributes` | `array` | Associative array of attribute name => value pairs. Default array( 'defer' => null ).

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-web-sdk-v1-script-attributes)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 119](../classes/class-kp-assets.php#L119-L132)


---
### `wc_kp_remove_postcode_spaces`


Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 233](../classes/class-kp-assets.php#L233-L233)


---
### `wc_kp_checkout_params`

*Filters the JavaScript parameters object localized to the Klarna Payments checkout script.*

This is the primary filter for modifying any data passed from PHP to the frontend Klarna Payments JavaScript.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$params` | `array` | Associative array of parameters, including AJAX URLs, nonces, cart total, testmode flag, customer type, client token, and i18n strings.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-parameters-passed-to-the-klarna-payments-frontend)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 252](../classes/class-kp-assets.php#L252-L260)


---
### `kp_enable_express_button`

*Filters whether the Klarna Express Checkout (Express Button) feature is enabled.*

The express button is not enqueued, rendered, or loaded unless a callback returns true.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enabled` | `bool` | Whether the express button is enabled. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#enable-the-klarna-express-checkout-button)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 311](../classes/class-kp-assets.php#L311-L319)


---
### `kp_enable_express_button`

*Filters whether the Klarna Express Checkout (Express Button) feature is enabled.*

The express button is not enqueued, rendered, or loaded unless a callback returns true.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enabled` | `bool` | Whether the express button is enabled. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#enable-the-klarna-express-checkout-button)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 347](../classes/class-kp-assets.php#L347-L355)


---
### `kp_express_button_locale`

*Filters the locale passed to the data-locale attribute of the Klarna Express Button web component.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string` | The locale string, for example 'en-US'.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#force-express-button-to-a-specific-country-and-language)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 376](../classes/class-kp-assets.php#L376-L382)


---
### `kp_enable_express_button`

*Filters whether the Klarna Express Checkout (Express Button) feature is enabled.*

The express button is not enqueued, rendered, or loaded unless a callback returns true.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enabled` | `bool` | Whether the express button is enabled. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#enable-the-klarna-express-checkout-button)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 498](../classes/class-kp-assets.php#L498-L506)


---
### `kp_enable_express_button`

*Filters whether the Klarna Express Checkout (Express Button) feature is enabled.*

The express button is not enqueued, rendered, or loaded unless a callback returns true.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enabled` | `bool` | Whether the express button is enabled. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#enable-the-klarna-express-checkout-button)

Source: [./classes/class-kp-assets.php](../classes/class-kp-assets.php), [line 538](../classes/class-kp-assets.php#L538-L546)


---
### `wc_gateway_klarna_payments_settings`

*Filters the full array of settings fields rendered on the Klarna Payments settings page.*

Use this to add, modify, or remove settings fields under WooCommerce > Settings > Payments.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$form_fields` | `array` | The WooCommerce settings form fields array.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-klarna-payments-settings-fields)

Source: [./classes/admin/class-kp-form-fields.php](../classes/admin/class-kp-form-fields.php), [line 609](../classes/admin/class-kp-form-fields.php#L609-L617)


---
### `wc_kp_request_timeout`

*Filters the timeout, in seconds, for Klarna API requests.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-request-timeout-time)

Source: [./classes/requests/class-kp-requests-post.php](../classes/requests/class-kp-requests-post.php), [line 43](../classes/requests/class-kp-requests-post.php#L43-L49)


---
### `kp_wc_api_request_args`

*Filters the request body sent to the Klarna API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#auto-capture-orders)
- [#2](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-order-cart-data-sent-to-klarna)

Source: [./classes/requests/class-kp-requests-post.php](../classes/requests/class-kp-requests-post.php), [line 51](../classes/requests/class-kp-requests-post.php#L51-L58)


---
### `kp_wc_api_request_body_args`

*Filters the formatted Klarna order object used as the request body.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_lines` | `array` | The formatted Klarna order object.
`$order_id` | `int` | The WooCommerce order ID.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#auto-capture-orders)

Source: [./classes/requests/class-kp-requests-post.php](../classes/requests/class-kp-requests-post.php), [line 93](../classes/requests/class-kp-requests-post.php#L93-L104)


---
### `wc_kp_request_timeout`

*Filters the timeout, in seconds, for Klarna API requests.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-request-timeout-time)

Source: [./classes/requests/class-kp-requests-patch.php](../classes/requests/class-kp-requests-patch.php), [line 40](../classes/requests/class-kp-requests-patch.php#L40-L46)


---
### `kp_wc_api_request_args`

*Filters the request body sent to the Klarna API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#auto-capture-orders)
- [#2](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-order-cart-data-sent-to-klarna)

Source: [./classes/requests/class-kp-requests-patch.php](../classes/requests/class-kp-requests-patch.php), [line 48](../classes/requests/class-kp-requests-patch.php#L48-L55)


---
### `wc_kp_request_timeout`

*Filters the timeout, in seconds, for Klarna API requests.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-request-timeout-time)

Source: [./classes/requests/class-kp-requests-get.php](../classes/requests/class-kp-requests-get.php), [line 38](../classes/requests/class-kp-requests-get.php#L38-L44)


---
### `wc_kp_remove_postcode_spaces`

*Filters whether to strip spaces from the postcode sent to Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$strip` | `bool` | Whether to remove postcode spaces. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#remove-postcode-spaces)

Source: [./classes/requests/helpers/class-kp-order-data.php](../classes/requests/helpers/class-kp-order-data.php), [line 238](../classes/requests/helpers/class-kp-order-data.php#L238-L244)


---
### `wc_kp_remove_postcode_spaces`

*Filters whether to strip spaces from the postcode sent to Klarna.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$strip` | `bool` | Whether to remove postcode spaces. Default false.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#remove-postcode-spaces)

Source: [./classes/requests/helpers/class-kp-order-data.php](../classes/requests/helpers/class-kp-order-data.php), [line 339](../classes/requests/helpers/class-kp-order-data.php#L339-L345)


---
### `klarna_base_region`

*Filters the Klarna API region used to build the API base URL.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$region` | `string` | The Klarna API region, derived from the country endpoint. Blank for EU.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-base-region-for-api-requests)

Source: [./classes/requests/class-kp-requests.php](../classes/requests/class-kp-requests.php), [line 116](../classes/requests/class-kp-requests.php#L116-L122)


---
### `wc_klarna_payments_supports`

*Filters the features supported by the Klarna Payments gateway.*

Use this to remove subscriptions support, since it is not possible to disable subscriptions in the Klarna account for Klarna Payments.

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$supports` | `array` | The supported features.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#disable-klarna-payments-for-subscriptions)

Source: [./classes/class-wc-gateway-klarna-payments.php](../classes/class-wc-gateway-klarna-payments.php), [line 84](../classes/class-wc-gateway-klarna-payments.php#L84-L108)


---
### `wc_klarna_payments_process_refund`

*Filters the result of a Klarna Payments refund, allowing the Klarna Order Management plugin to process it.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | Whether the refund was processed. Default false.
`$order_id` | `int` | The WooCommerce order ID.
`$amount` | `null\|int` | The refund amount, or null for the full amount.
`$reason` | `string` | The reason for the refund.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#handle-klarna-refunds-with-custom-code)

Source: [./classes/class-wc-gateway-klarna-payments.php](../classes/class-wc-gateway-klarna-payments.php), [line 588](../classes/class-wc-gateway-klarna-payments.php#L588-L597)


---
### `wc_klarna_payments_available_payment_categories`

*Filters the Klarna payment categories available to display in the checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_payment_categories` | `array` | The available Klarna payment method categories.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#modify-the-available-klarna-payment-categories)

Source: [./templates/klarna-payments-categories.php](../templates/klarna-payments-categories.php), [line 23](../templates/klarna-payments-categories.php#L23-L29)


---
### `kp_blocks_logo`

*Filters the Klarna logo URL shown for the payment method in the block checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$logo_url` | `string` | The URL of the Klarna logo.

Examples: 
- [#1](https://docs.krokedil.com/klarna-for-woocommerce/customization/hooks-action-filter/#change-the-klarna-logo-in-the-blocks-checkout)

Source: [./blocks/src/payment/KlarnaPayments.php](../blocks/src/payment/KlarnaPayments.php), [line 87](../blocks/src/payment/KlarnaPayments.php#L87-L93)


---


