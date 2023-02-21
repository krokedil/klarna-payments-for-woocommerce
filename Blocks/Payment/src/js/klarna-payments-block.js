import '../css/klarna-payments-block.scss';
const { decodeEntities } = wp.htmlEntities;
const { getSetting } = wc.wcSettings;
const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { applyFilters } = wp.hooks;

// Data
const settings = getSetting('klarna_payments_data', {});
const title = applyFilters('kp_blocks_title', decodeEntities(settings.title || 'Klarna'), settings );
const description = applyFilters('kp_blocks_description', decodeEntities(settings.description || ''), settings );
const iconUrl = settings.iconurl;

const canMakePayment = () => {
    return applyFilters('kp_blocks_enabled', true, settings);
};

const Content = props => {
    return <div>{description}</div>;
};

const Label = props => {
    const { PaymentMethodLabel } = props.components;
    const icon = <img src={iconUrl} alt={title} name={title} />
    return <PaymentMethodLabel className='kp-block-label' text={title} icon={icon} />;
};

/**
 * Klarna payments method config.
 */
const KlarnaPaymentsOptions = {
    name: 'klarna_payments',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    placeOrderButtonLabel: 'Pay with Klarna',
    canMakePayment: canMakePayment,
    ariaLabel: title
};

registerPaymentMethod(KlarnaPaymentsOptions);
