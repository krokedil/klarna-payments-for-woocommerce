import * as React from "react";

/**
 * Wordpress/WooCommerce dependencies
 */
// @ts-ignore - Can't avoid this issue, but it's loaded in by Webpack
import { decodeEntities } from "@wordpress/html-entities";
// @ts-ignore - Can't avoid this issue, but it's loaded in by Webpack
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
// @ts-ignore - Can't avoid this issue, but it's loaded in by Webpack
import { getSetting } from "@woocommerce/settings";
// @ts-ignore - Can't avoid this issue, but it's loaded in by Webpack
import { applyFilters } from "@wordpress/hooks";
import "./style.scss";

interface Settings {
  title: string;
  description: string;
  iconurl: string;
  orderbuttonlabel: string;
  features: string[];
}

const settings: Settings = getSetting('klarna_payments_data', {} as Settings);

const title: string = applyFilters('kp_blocks_title', decodeEntities(settings.title || 'Klarna'), settings) as string;
const description: string = applyFilters('kp_blocks_description', decodeEntities(settings.description || ''), settings) as string;
const iconUrl: string = settings.iconurl;
const features: string[] = settings.features;

const canMakePayment = (): boolean => {
  return applyFilters('kp_blocks_enabled', true, settings) as boolean;
};

const Content: React.FC = () => {
  return <div>{description}</div>;
};

interface LabelProps {
  components?: {
    PaymentMethodLabel: React.ComponentType<any>;
  };
}

const Label: React.FC<LabelProps> = ({ components }) => {
  const { PaymentMethodLabel } = components;
  const icon = <img src={iconUrl} alt={title} />;

  return <PaymentMethodLabel className="kp-block-label" text={title} icon={icon} />;
};

/**
 * Klarna payments method config.
 */
const KlarnaPaymentsOptions = {
  name: 'klarna_payments',
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  placeOrderButtonLabel: settings.orderbuttonlabel,
  canMakePayment: canMakePayment,
  ariaLabel: title,
  supports: {
    features
  }
};

registerPaymentMethod(KlarnaPaymentsOptions);
