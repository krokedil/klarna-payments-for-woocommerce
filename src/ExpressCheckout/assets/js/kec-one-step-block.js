const { __ } = wp.i18n;
const { registerPlugin } = wp.plugins;
const { ExperimentalOrderMeta } = wc.blocksCheckout;
const render = () => {
    return (
        React.createElement(
            ExperimentalOrderMeta,
            null,
            React.createElement("div", { id: "kec-pay-button"})
        )
    );
};

registerPlugin('kec-one-step', {
    render,
    scope: 'woocommerce-checkout',
});
