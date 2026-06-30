const $ = jQuery;
const { klarna_interoperability } = await import("@klarna/network_session_token");
let configData = {};

const params = document.getElementById(
  'wp-script-module-data-@klarna/kec-one-step'
);

if (params?.textContent) {
  try { configData = JSON.parse(params.textContent); } catch { }
}

const KECOneStep = {
  params: {
    ajax: {},
    client_id: '',
    testmode: false,
    theme: 'dark',
    shape: 'default',
    locale: false,
    currency: '',
    amount: 0,
    source: 'unknown',
    is_variable_product: false,
  },
  Klarna: null,
  button: null,
  isInitiating: false,
  variationId: null,

  getContainer() {
    return document.querySelector('#kec-pay-button');
  },

  isMounted() {
    const container = KECOneStep.getContainer();
    return !!(container && container.childElementCount > 0);
  },

  mountButton() {
    const container = KECOneStep.getContainer();
    if (!container || !KECOneStep.Klarna) {
      return;
    }

    if (KECOneStep.isMounted()) {
      return;
    }

    const buttonArgs = {
      theme: KECOneStep.params.theme,
      shape: KECOneStep.params.shape,
      locale: KECOneStep.params.locale,
      intents: ["PAY"],
      initiationMode: "DEVICE_BEST",
      initiate: async () => await KECOneStep.onClickPayButton()
    };
    

    if( KECOneStep.params.is_variable_product && !KECOneStep.variationId ) {
        buttonArgs.disabled = true;
        container.classList.add('kec-button-disabled');
    }

    KECOneStep.button = KECOneStep.Klarna.Payment
      .button(buttonArgs)
      .mount(container);
  },

  onCartUpdated() {
    window.requestAnimationFrame(() => {
      KECOneStep.mountButton();
    });
  },

  updateProductVariationButton() {
    const disabled = null === KECOneStep.variationId;
    const KECButton = KECOneStep.getContainer();

    if (!KECOneStep.button) {
      return;
    }

    KECOneStep.button.toggleState('disabled', disabled);

    if (!KECButton) {
      return;
    }

    KECButton.classList.toggle('kec-button-disabled', disabled);
  },

  /**
   * Initialize the Klarna Express Checkout script for One Step Checkout.
   *
   * @returns {Promise<void>}
   */
  init: async function (e) {
    KECOneStep.params = configData;
    KECOneStep.Klarna = klarna_interoperability.Klarna;

    KECOneStep.mountButton();

    KECOneStep.Klarna.Payment.on("shippingaddresschange", KECOneStep.onShippingAddressChange);
    KECOneStep.Klarna.Payment.on("shippingoptionselect", KECOneStep.onShippingOptionSelect);

    $(document.body).on(
      "updated_cart_totals added_to_cart removed_from_cart updated_checkout updated_wc_div wc-blocks_added_to_cart wc-blocks_removed_from_cart",
      KECOneStep.onCartUpdated
    );

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
    KECOneStep.variationId = variation.variation_id;
    KECOneStep.updateProductVariationButton();
  },

  /**
   * Handle the initiate request from Klarna when the customer clicks the payment button.
   *
   * @return {Promise<Object>} The payment request object.
   */
  onClickPayButton: async function () {
    if( KECOneStep.isInitiating ) {
      return;
    }
    KECOneStep.isInitiating = true;

    const { url, nonce, method } = KECOneStep.params.ajax.get_initiate_body;

    const result = await $.ajax({
      type: method,
      url: url,
      data: {
        nonce: nonce,
        source: KECOneStep.params.source,
        variation_id: KECOneStep.variationId,
      },
    });

    if (result.error) {
      console.error("Error initiating Klarna payment:", result.error);
      throw new Error(result.error);
    }

    const body = result.data || {};
    return body;
  },

  /**
   * Handle the shipping address change event from Klarna.
   *
   * @param {object} data
   * @param {object} shippingAddress
   * @returns {Promise<Object>} The updated payment request object.
   */
  onShippingAddressChange: async function (data, shippingAddress) {
    const { url, nonce, method } = KECOneStep.params.ajax.shipping_change;

    const result = await $.ajax({
      type: method,
      url: url,
      data:{
        nonce: nonce,
        shippingAddress: shippingAddress,
        paymentRequestId: data.paymentRequestId,
        paymentToken: data.stateContext.paymentToken
      },
    });

    if (result.error) {
      throw new Error(result.error);
    }

    const body = result.data || {};

    return body
  },

  /**
   * Handle the shipping option select event from Klarna.
   *
   * @param {object} _ Unused parameter.
   * @param {object} shippingOption The selected shipping option.
   * @returns {Promise<Object>} The updated payment request object.
   */
  onShippingOptionSelect: async function (_, shippingOption) {
    const { url, nonce, method } = KECOneStep.params.ajax.shipping_option_change;

    const result = await $.ajax({
      type: method,
      url: url,
      data: {
        nonce: nonce,
        selectedOption: shippingOption.shippingOptionReference
      },
    });

    if (result.error) {
      console.error("Error updating selected shipping method:", result.error);
      throw new Error(result.error);
    }

    const body = result.data || {};

    return body
  },

  /**
   * Handle when the customer clicks the clear variation button or when the variation is reset.
   *
   * @returns {void}
   */
  onVariationClear() {
    KECOneStep.variationId = null;
    KECOneStep.updateProductVariationButton();
  }

}

window.kecOneStep = KECOneStep;

$(document.body).on("found_variation", KECOneStep.onFoundVariation);
$('body').on('klarna_wc_sdk_loaded', KECOneStep.init);
$(document.body).on("reset_data", KECOneStep.onVariationClear);
