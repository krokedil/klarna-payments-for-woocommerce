const { registerExpressPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;
const { useEffect } = wp.element;

const settings = getSetting("kec_one_step_data", {});

/**
 * Render the button container, and mount the button into it if the one-step script is already loaded.
 */
const Content = () => {
  useEffect(() => {
    window.kecOneStep?.mountButton();
  }, []);

  return React.createElement("div", { id: "kec-pay-button" });
};

const Edit = () => React.createElement("div", null, settings.title || "Klarna");

registerExpressPaymentMethod({
  name: "kec_one_step",
  title: settings.title || "Klarna",
  description: settings.title || "Klarna",
  content: React.createElement(Content),
  edit: React.createElement(Edit),
  // The cart block already has the button in the order summary, so only show it in the checkout block.
  canMakePayment: () => !!settings.is_checkout,
  paymentMethodId: "klarna_payments",
  supports: {
    features: ["products"],
  },
});
