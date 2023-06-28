#!/bin/sh

# Get the url from the ngrok API using the ngrok container.
# Wait for ngrok URL to become available
while [ -z "$NGROK_URL" ]; do
  echo "Waiting for ngrok URL to become available..."
  NGROK_URL=$(curl -s http://ngrok:4040/api/tunnels | jq -r '.tunnels[0].public_url')
  sleep 2
done

echo "NGROK_URL: $NGROK_URL"

wp core install --url=$NGROK_URL --title='Krokedil E2E Test' --admin_user='admin' --admin_password='password' --admin_email='e2e@krokedil.se' --skip-email --skip-plugins --skip-themes
wp rewrite structure '/%postname%/' --hard
if [ -z "${WC_VERSION}" ]; then
    wp plugin install woocommerce --activate
else
    wp plugin install woocommerce --version=${WC_VERSION} --activate
fi
wp plugin install wp-mail-logging --activate
wp theme install storefront --activate
wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate
wp plugin activate klarna-payments-for-woocommerce
wp plugin install https://github.com/krokedil/klarna-order-management-for-woocommerce/archive/master.zip --activate
wp option update woocommerce_default_country SE
wp option update woocommerce_currency SEK
wp post create --post_type=page --post_title='Checkout Block' --post_status=publish --post_content='<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-express-payment-block --><div class="wp-block-woocommerce-checkout-express-payment-block"></div><!-- /wp:woocommerce/checkout-express-payment-block --><!-- wp:woocommerce/checkout-contact-information-block --><div class="wp-block-woocommerce-checkout-contact-information-block"></div><!-- /wp:woocommerce/checkout-contact-information-block --><!-- wp:woocommerce/checkout-shipping-address-block --><div class="wp-block-woocommerce-checkout-shipping-address-block"></div><!-- /wp:woocommerce/checkout-shipping-address-block --><!-- wp:woocommerce/checkout-billing-address-block --><div class="wp-block-woocommerce-checkout-billing-address-block"></div><!-- /wp:woocommerce/checkout-billing-address-block --><!-- wp:woocommerce/checkout-shipping-methods-block --><div class="wp-block-woocommerce-checkout-shipping-methods-block"></div><!-- /wp:woocommerce/checkout-shipping-methods-block --><!-- wp:woocommerce/checkout-payment-block --><div class="wp-block-woocommerce-checkout-payment-block"></div><!-- /wp:woocommerce/checkout-payment-block --><!-- wp:woocommerce/checkout-order-note-block --><div class="wp-block-woocommerce-checkout-order-note-block"></div><!-- /wp:woocommerce/checkout-order-note-block --><!-- wp:woocommerce/checkout-terms-block --><div class="wp-block-woocommerce-checkout-terms-block"></div><!-- /wp:woocommerce/checkout-terms-block --><!-- wp:woocommerce/checkout-actions-block --><div class="wp-block-woocommerce-checkout-actions-block"></div><!-- /wp:woocommerce/checkout-actions-block --></div><!-- /wp:woocommerce/checkout-fields-block --><!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block"><!-- wp:woocommerce/checkout-order-summary-block --><div class="wp-block-woocommerce-checkout-order-summary-block"><!-- wp:woocommerce/checkout-order-summary-cart-items-block --><div class="wp-block-woocommerce-checkout-order-summary-cart-items-block"></div><!-- /wp:woocommerce/checkout-order-summary-cart-items-block --><!-- wp:woocommerce/checkout-order-summary-subtotal-block --><div class="wp-block-woocommerce-checkout-order-summary-subtotal-block"></div><!-- /wp:woocommerce/checkout-order-summary-subtotal-block --><!-- wp:woocommerce/checkout-order-summary-fee-block --><div class="wp-block-woocommerce-checkout-order-summary-fee-block"></div><!-- /wp:woocommerce/checkout-order-summary-fee-block --><!-- wp:woocommerce/checkout-order-summary-discount-block --><div class="wp-block-woocommerce-checkout-order-summary-discount-block"></div><!-- /wp:woocommerce/checkout-order-summary-discount-block --><!-- wp:woocommerce/checkout-order-summary-coupon-form-block --><div class="wp-block-woocommerce-checkout-order-summary-coupon-form-block"></div><!-- /wp:woocommerce/checkout-order-summary-coupon-form-block --><!-- wp:woocommerce/checkout-order-summary-shipping-block --><div class="wp-block-woocommerce-checkout-order-summary-shipping-block"></div><!-- /wp:woocommerce/checkout-order-summary-shipping-block --><!-- wp:woocommerce/checkout-order-summary-taxes-block --><div class="wp-block-woocommerce-checkout-order-summary-taxes-block"></div><!-- /wp:woocommerce/checkout-order-summary-taxes-block --></div><!-- /wp:woocommerce/checkout-order-summary-block --></div><!-- /wp:woocommerce/checkout-totals-block --></div>'  --porcelain
