<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Klarna_Payments_Form_Fields
 */
class Klarna_Payments_Form_Fields {
	/**
	 * Returns the fields.
	 */
	public static function fields() {
		return apply_filters( 'wc_gateway_klarna_payments_settings', array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				'label'       => __( 'Enable Klarna Payments', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Pay Over Time', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
				'default'     => __( 'Get the flexibility to pay over time with Klarna!', 'woocommerce' ),
				'desc_tip'    => true,
			),

			'allow_multiple_countries' => array(
				'title'       => __( 'Allow Klarna Payments across multiple countries', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'label' => __( 'If this option is checked Klarna credentials for customer\'s billing country will be used, if available. If those credentials are not available, then Klarna credentials for shop base location country will be used. If the option is unchecked only Klarna credentials for shop base location country will be used.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),

			// US credentials.
			'us_credentials' => array(
				'title' => 'Credentials (US)',
				'type'  => 'title',
			),
			'test_merchant_id_us' => array(
				'title'       => __( 'Test merchant ID (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_us' => array(
				'title'       => __( 'Test shared secret (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_us' => array(
				'title'       => __( 'Live merchant ID (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_us' => array(
				'title'       => __( 'Live shared secret (US)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for US.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'prescreen' => array(
				'title'       => __( 'Prescreen', 'woocommerce-gateway-klarna-payments' ),
				'label'       => __( 'Enable Prescreen (US merchants only)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc_tip'    => true,
			),

			// Europe.
			// GB credentials.
			'gb_credentials' => array(
				'title' => 'Credentials (GB)',
				'type'  => 'title',
			),
			'test_merchant_id_gb' => array(
				'title'       => __( 'Test merchant ID (GB)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for GB.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_gb' => array(
				'title'       => __( 'Test shared secret (GB)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for GB.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_gb' => array(
				'title'       => __( 'Live merchant ID (GB)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for GB.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_gb' => array(
				'title'       => __( 'Live shared secret (GB)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for GB.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// SE credentials.
			'se_credentials' => array(
				'title' => 'Credentials (SE)',
				'type'  => 'title',
			),
			'test_merchant_id_se' => array(
				'title'       => __( 'Test merchant ID (SE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for EU.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_se' => array(
				'title'       => __( 'Test shared secret (SE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for SE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_se' => array(
				'title'       => __( 'Live merchant ID (SE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for SE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_se' => array(
				'title'       => __( 'Live shared secret (SE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for SE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// NO credentials.
			'no_credentials' => array(
				'title' => 'Credentials (NO)',
				'type'  => 'title',
			),
			'test_merchant_id_no' => array(
				'title'       => __( 'Test merchant ID (NO)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_no' => array(
				'title'       => __( 'Test shared secret (NO)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_no' => array(
				'title'       => __( 'Live merchant ID (NO)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_no' => array(
				'title'       => __( 'Live shared secret (NO)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NO.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// FI credentials.
			'fi_credentials' => array(
				'title' => 'Credentials (FI)',
				'type'  => 'title',
			),
			'test_merchant_id_fi' => array(
				'title'       => __( 'Test merchant ID (FI)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_fi' => array(
				'title'       => __( 'Test shared secret (FI)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_fi' => array(
				'title'       => __( 'Live merchant ID (FI)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_fi' => array(
				'title'       => __( 'Live shared secret (FI)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for FI.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// DK credentials.
			'dk_credentials' => array(
				'title' => 'Credentials (DK)',
				'type'  => 'title',
			),
			'test_merchant_id_dk' => array(
				'title'       => __( 'Test merchant ID (DK)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_dk' => array(
				'title'       => __( 'Test shared secret (DK)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_dk' => array(
				'title'       => __( 'Live merchant ID (DK)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_dk' => array(
				'title'       => __( 'Live shared secret (DK)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DK.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// NL credentials.
			'nl_credentials' => array(
				'title' => 'Credentials (NL)',
				'type'  => 'title',
			),
			'test_merchant_id_nl' => array(
				'title'       => __( 'Test merchant ID (NL)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_nl' => array(
				'title'       => __( 'Test shared secret (NL)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_nl' => array(
				'title'       => __( 'Live merchant ID (NL)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_nl' => array(
				'title'       => __( 'Live shared secret (NL)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for NL.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// AT credentials.
			'at_credentials' => array(
				'title' => 'Credentials (AT)',
				'type'  => 'title',
			),
			'test_merchant_id_at' => array(
				'title'       => __( 'Test merchant ID (AT)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_at' => array(
				'title'       => __( 'Test shared secret (AT)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_at' => array(
				'title'       => __( 'Live merchant ID (AT)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_at' => array(
				'title'       => __( 'Live shared secret (AT)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for AT.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			// DE credentials.
			'de_credentials' => array(
				'title' => 'Credentials (DE)',
				'type'  => 'title',
			),
			'test_merchant_id_de' => array(
				'title'       => __( 'Test merchant ID (DE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_shared_secret_de' => array(
				'title'       => __( 'Test shared secret (DE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_id_de' => array(
				'title'       => __( 'Live merchant ID (DE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shared_secret_de' => array(
				'title'       => __( 'Live shared secret (DE)', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Klarna Payments merchant account for DE.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'testmode' => array(
				'title'       => __( 'Test mode', 'woocommerce-gateway-klarna-payments' ),
				'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging' => array(
				'title'       => __( 'Logging', 'woocommerce-gateway-klarna-payments' ),
				'label'       => __( 'Log debug messages', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'float_what_is_klarna' => array(
				'title'       => __( 'What is Klarna? link', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'If checked, What is Klarna? will be floated right.', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'send_product_urls' => array(
				'title'       => __( 'Product URLs', 'woocommerce-gateway-klarna-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Send product and product image URLs to Klarna', 'woocommerce-gateway-klarna-payments' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),

			'iframe_options' => array(
				'title' => 'Iframe settings',
				'type'  => 'title',
			),
			'background' => array(
				'title'       => 'Background',
				'type'        => 'color',
				'default'     => '#ffffff',
				'desc_tip'    => true,
			),
			'color_button' => array(
				'title'       => 'Button color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_button_text' => array(
				'title'       => 'Button text color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox' => array(
				'title'       => 'Checkbox color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_checkbox_checkmark' => array(
				'title'       => 'Checkbox checkmark color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_header' => array(
				'title'       => 'Header color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_link' => array(
				'title'       => 'Link color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_border' => array(
				'title'       => 'Border color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_border_selected' => array(
				'title'       => 'Selected border color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_text' => array(
				'title'       => 'Text color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_details' => array(
				'title'       => 'Details color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'color_text_secondary' => array(
				'title'       => 'Secondary text color',
				'type'        => 'color',
				'default'     => '',
				'desc_tip'    => true,
			),
			'radius_border' => array(
				'title'       => 'Border radius (px)',
				'type'        => 'number',
				'default'     => '',
				'desc_tip'    => true,
			),
		) );
	}
}

