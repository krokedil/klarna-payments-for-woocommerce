const { registerPlugin: RegisterPluginOSM } = wp.plugins;

const ElementOSM = ({ klarnaKey, locale, theme, purchaseAmount, cart }) => {
    const [showPlacement, setShowPlacement] = React.useState(true);

    React.useEffect(() => {
        if (window.klarna_onsite_messaging) {
            window.klarna_onsite_messaging.update_total_price(cart.cartTotals.total_price);
        }
    }, [cart.cartTotals.total_price]);

    return showPlacement
        ? React.createElement("klarna-placement", {
              className: "klarna-onsite-messaging",
              "data-preloaded": "true",
              "data-key": klarnaKey,
              "data-locale": locale,
              "data-theme": theme,
              "data-purchase-amount": purchaseAmount,
          })
        : null;
};

const renderOSM = () => {
    const { ExperimentalOrderMeta } = window.wc?.blocksCheckout || {};
    if (!ExperimentalOrderMeta) {
        console.warn("[Klarna OSM] ExperimentalOrderMeta not found in window.wc.blocksCheckout");
    }

    const osmData = window.wc?.wcSettings?.getSetting("osm-cart-block-integration_data", {}) || {};
    return React.createElement(
        ExperimentalOrderMeta,
        null,
        React.createElement(ElementOSM, {
            klarnaKey: osmData.key || "",
            locale: osmData.locale || "",
            theme: osmData.theme || "",
            purchaseAmount: osmData.purchase_amount || "",
        }),
    );
};

RegisterPluginOSM("osm-cart-block-integration", {
    render: renderOSM,
    scope: "woocommerce-checkout",
});
