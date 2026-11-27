jQuery(function ($) {
  const kec_cart = {
    selectedVariation: null,

    /**
     * Initialize the Klarna Express Checkout button.
     */
    init() {
      // Load the Klarna Express Checkout button.
      kec_cart.load();

      // Add event listener to the cart update button.
      $(document.body).on("updated_cart_totals", kec_cart.load);

      $(document.body).on("woocommerce_variation_has_changed", kec_cart.onVariationSelectChange);
      $(document.body).on("found_variation", kec_cart.onFoundVariation);
    },

    /**
     * Load the klarna express button.
     *
     * @returns {void}
     */
    load() {
      if (!kec_cart.checkVariation()) {
        return;
      }

      // Check that the button container exists, and that the button has not already been rendered into it.
      const $button = $("#kec-pay-button");
      if (!$button.length || null !== $button[0].shadowRoot) {
        return;
      }

      // Wait until the window.Klarna.Payments.Buttons is available before initializing the button.
      const interval = setInterval(() => {
        if (window.Klarna && window.Klarna.Payments && window.Klarna.Payments.Buttons) {
          clearInterval(interval);
          kec_cart.initKlarnaButton();
        }
      }, 100);

      // Stop trying to load the button after 2 seconds.
      setTimeout(() => clearInterval(interval), 2000);
    },

    /**
     * Initialize the Klarna button.
     *
     * @returns {void}
     */
    initKlarnaButton() {
      const { client_id, theme, shape, locale } = kec_cart_params;

      window.Klarna.Payments.Buttons.init({
        client_id,
      }).load({
        container: "#kec-pay-button",
        theme: theme,
        shape: shape,
        locale: locale,
        on_click: (authorize) => {
          kec_cart.onClickHandler(authorize);
        },
      });
    },

    /**
     * Handle the change selected variation event.
     * If no variation is selected, the KEC button will be hidden.
     *
     * @returns {void}
     */
    onVariationSelectChange() {
      let variationIsSelected = kec_cart.checkVariation() ? true : false;
      $('#kec-pay-button').toggle(variationIsSelected);
    },

    /**
     * Handle the found variation event.
     *
     * @param {object} event
     * @param {object} variation
     *
     * @returns {void}
     */
    onFoundVariation(event, variation) {
      console.log("onFoundVariation", variation);
      kec_cart.selectedVariation = variation;

      kec_cart.load();
    },

    /**
     * Checks if we are on a product page, and if so, if the product is a variable product.
     * If it is, it will see if the customer has selected a variation.
     *
     * @returns {boolean} True if we can continue, or if we need to wait for a variation to be set.
     */
    checkVariation() {
      const { is_product_page, product } = kec_cart_params;

      if (!is_product_page) {
        return true;
      }

      if (product.type !== "variable") {
        return true;
      }

      return kec_cart.selectedVariation !== null;
    },

    /**
     * Handle the click event on the KEC button.
     *
     * @param {function} authorize
     * @returns {void}
     */
    onClickHandler(authorize) {
      kec_cart.clearError();

      // If the customer is on the product page, we need to set the cart to only contain the product, and the quantity of it.
      const { is_product_page } = kec_cart_params;
      if (is_product_page) {
        const setCartResult = kec_cart.productPageHandler();

        if (!setCartResult) {
          kec_cart.showError();
          return;
        }
      }

      const payload = kec_cart.getPayload();

      if (!payload) {
        kec_cart.showError();
        return;
      }

      // Authorize the Klarna payment.
      authorize(
        { auto_finalize: false, collect_shipping_address: true },
        payload,
        (result) => kec_cart.onAuthorizeHandler(result)
      );
    },

    /**
     * Handle the authorize result from Klarna.
     *
     * @param {object} authorizeResult
     * @returns {void}
     */
    async onAuthorizeHandler(authorizeResult) {
      if (!authorizeResult.approved) {
        // A closed popup leaves show_form true; a decline sets it to false, and a failure adds an error.
        if (authorizeResult.show_form === false || authorizeResult.error) {
          // Only log why it failed, the result also holds the client token and the customer's address.
          kec_cart.logError("Klarna did not approve the authorization", {
            show_form: authorizeResult.show_form,
            error: authorizeResult.error,
          });
          kec_cart.showError();
        }
        return;
      }

      // Send the authorize result to the server.
      const authCallbackResult = await kec_cart.authCallback(authorizeResult);

      if (!authCallbackResult) {
        kec_cart.showError();
        return;
      }

      // Redirect the customer to the redirect url.
      window.location.href = authCallbackResult;
    },

    /**
     * Show an error notice to the customer.
     *
     * @returns {void}
     */
    showError() {
      kec_cart.clearError();

      const $notice = $('<div class="woocommerce-error kec-error" role="alert"></div>');
      $notice.text(kec_cart_params.i18n.error);

      const $wrapper = $(".woocommerce-notices-wrapper").first();
      if ($wrapper.length) {
        $wrapper.prepend($notice);
      } else {
        $("#kec-pay-button").before($notice);
      }

      $("html, body").animate({ scrollTop: $notice.offset().top - 100 }, 500);
    },

    /**
     * Remove any error notice shown by KEC.
     *
     * @returns {void}
     */
    clearError() {
      $(".kec-error").remove();
    },

    /**
     * Log why a step of the KEC flow failed to the console.
     *
     * @param {string} reason The step that failed.
     * @param {*} details The response or result the step got.
     * @returns {void}
     */
    logError(reason, details) {
      console.error("Klarna Express Checkout:", reason, details);
    },

    /**
     * Handle the product page logic.
     *
     * @returns {void}
     */
    productPageHandler() {
      const { is_product_page, product } = kec_cart_params;

      if (!is_product_page && kec_cart.checkVariation()) {
        return;
      }

      let variationId = null;
      if (product.type === "variable") {
        variationId = kec_cart.selectedVariation.variation_id;
      }

      return kec_cart.setCustomerCart(product.id, variationId);
    },

    /**
     * Get the order data from the server.
     *
     * @returns {object|boolean}
     */
    getPayload() {
      const { url, nonce, method } = kec_cart_params.ajax.get_payload;
      let payload = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
        },
        async: false,
        success: (result) => {
          if (result.success) {
            payload = result.data || false;
          } else {
            kec_cart.logError("Could not get the payload", result.data);
          }
        },
        error: (jqXHR) => kec_cart.logError("Could not get the payload", jqXHR.statusText),
      });

      return payload;
    },

    /**
     * The auth callback ajax request.
     *
     * @param {object} authorizeResult
     * @returns {object|boolean}
     */
    authCallback(authorizeResult) {
      const { url, nonce, method } = kec_cart_params.ajax.auth_callback;
      let result = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          nonce: nonce,
          result: authorizeResult,
        },
        async: false,
        success: (response) => {
          if (response.success) {
            result = response.data || false;
          } else {
            kec_cart.logError("The auth callback failed", response.data);
          }
        },
        error: (jqXHR) => kec_cart.logError("The auth callback failed", jqXHR.statusText),
      });

      return result;
    },

    /**
     * Set the customer cart.
     *
     * @param {number} productId
     * @param {number|null} variationId
     *
     * @returns {boolean}
     */
    setCustomerCart(productId, variationId = null) {
      const { url, nonce, method } = kec_cart_params.ajax.set_cart;

      let result = false;

      $.ajax({
        type: method,
        url: url,
        data: {
          product_id: productId,
          variation_id: variationId,
          nonce: nonce,
        },
        async: false,
        success: (response) => {
          result = response.success || false;

          if (!result) {
            kec_cart.logError("Could not set the cart", response.data);
          }
        },
        error: (jqXHR) => kec_cart.logError("Could not set the cart", jqXHR.statusText),
      });

      return result;
    },
  };

  window.klarnaAsyncCallback = kec_cart.init();
});
