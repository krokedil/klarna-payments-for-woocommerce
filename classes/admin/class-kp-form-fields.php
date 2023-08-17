<?php
/**
 * Builds the form fields for the payment gateway.
 *
 * @class    KP_Form_Fields
 * @package  KP/Classes
 * @category Class
 * @author   Krokedil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // To prevent direct access to this file.
}

/**
 * Class for WooCommerce settings page
 */
class KP_Form_Fields {
	/**
	 * Countries that have no special requirements, and can have their form section built automatically.
	 *
	 * @var array $kp_form_auto_countries
	 */
	public static $kp_form_auto_countries = array(
		'au' => array(
			'name'     => 'Australia',
			'currency' => 'AUD',
			'endpoint' => '-oc',
		),
		'at' => array(
			'name'     => 'Austria',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'be' => array(
			'name'     => 'Belgium',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'ca' => array(
			'name'     => 'Canada',
			'currency' => 'CAD',
			'endpoint' => '-na',
		),
		'cz' => array(
			'name'     => 'Czech Republic',
			'currency' => 'CZK',
			'endpoint' => '',
		),
		'dk' => array(
			'name'     => 'Denmark',
			'currency' => 'DKK',
			'endpoint' => '',
		),

		'fi' => array(
			'name'     => 'Finland',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'fr' => array(
			'name'     => 'France',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'de' => array(
			'name'     => 'Germany',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'gr' => array(
			'name'     => 'Greece',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'hu' => array(
			'name'     => 'Hungary',
			'currency' => 'HUF',
			'endpoint' => '',
		),
		'ie' => array(
			'name'     => 'Ireland',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'it' => array(
			'name'     => 'Italy',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'mx' => array(
			'name'     => 'Mexico',
			'currency' => 'MXN',
			'endpoint' => '-na',
		),
		'nl' => array(
			'name'     => 'Netherlands',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'nz' => array(
			'name'     => 'New Zealand',
			'currency' => 'NZD',
			'endpoint' => '-oc',
		),
		'no' => array(
			'name'     => 'Norway',
			'currency' => 'NOK',
			'endpoint' => '',
		),

		'pl' => array(
			'name'     => 'Poland',
			'currency' => 'PLN',
			'endpoint' => '',
		),
		'pt' => array(
			'name'     => 'Portugal',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'ro' => array(
			'name'     => 'Romania',
			'currency' => 'RON',
			'endpoint' => '',
		),
		'es' => array(
			'name'     => 'Spain',
			'currency' => 'EUR',
			'endpoint' => '',
		),
		'se' => array(
			'name'     => 'Sweden',
			'currency' => 'SEK',
			'endpoint' => '',
		),
		'ch' => array(
			'name'     => 'Switzerland',
			'currency' => 'CHF',
			'endpoint' => '',
		),
		'gb' => array(
			'name'     => 'United Kingdom',
			'currency' => 'GBP',
			'endpoint' => '',
		),
		'us' => array(
			'name'     => 'United States',
			'currency' => 'USD',
			'endpoint' => '-na',
		),
	);

	/**
	 * Standardized form building for easier maintenance.
	 * This builds the title part of a settings section.
	 *
	 * @param string $country_name The full name of the country as it should appear on the settings page.
	 * @param string $flag_path Path to the flag SVG to use, relative to the plugin root.
	 * @return array Completed title setting.
	 */
	private static function kp_form_country_title( $country_name, $flag_path ) {
		return array(
			'title' => '<img src="' . plugins_url( $flag_path, WC_KLARNA_PAYMENTS_MAIN_FILE ) . '" height="12" /> ' . $country_name,
			'type'  => 'title',
		);
	}

	/**
	 * Get the standard production username form section
	 *
	 * @return array Production Username form section
	 */
	private static function kp_form_production_username() {
		return array(
			'title'       => __( 'Production Klarna API username', 'klarna-payments-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Use the API username you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => false,
		);
	}

	/**
	 * Get the standard production password section
	 *
	 * @return array Production Password form section
	 */
	private static function kp_form_production_password() {
		return array(
			'title'       => __( 'Production Klarna API password', 'klarna-payments-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Use the API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => false,
		);
	}

	/**
	 * Get the standard test username section
	 *
	 * @return array Test Username form section
	 */
	private static function kp_form_test_username() {
		return array(
			'title'       => __( 'Test Klarna API username', 'klarna-payments-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Use the API username you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => false,
		);
	}

	/**
	 * Get the standard test password section
	 *
	 * @return array Test Password form section
	 */
	private static function kp_form_test_password() {
		return array(
			'title'       => __( 'Test Klarna API password', 'klarna-payments-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Use the API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => false,
		);
	}

	/**
	 * Build a completed form section from country name and ISO 3166-1 alpha-2.
	 *
	 * @param string $country_name Full name of the country, in English, as it should appear on the page.
	 * @param string $country_code ISO 3166-1 alpha-2 country code of the country, like "SE" or "NO".
	 *
	 * @return array The completed section for the given country.
	 */
	private static function kp_form_country_section( $country_name, $country_code ) {

		$country_code = strtolower( $country_code );

		$section = array();

		$section[ 'credentials_' . $country_code ]        = self::kp_form_country_title( $country_name, 'assets/img/flags/' . $country_code . '.svg' );
		$section[ 'merchant_id_' . $country_code ]        = self::kp_form_production_username();
		$section[ 'shared_secret_' . $country_code ]      = self::kp_form_production_password();
		$section[ 'test_merchant_id_' . $country_code ]   = self::kp_form_test_username();
		$section[ 'test_shared_secret_' . $country_code ] = self::kp_form_test_password();

		return $section;

	}

	/**
	 * Builds and returns the settings form structure.
	 *
	 * @return array The completed settings form structure
	 */
	private static function kp_form_build_settings() {

		$settings = array(
			'enabled'              => array(
				'title'       => __( 'Enable/Disable', 'klarna-payments-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Payments', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                => array(
				'title'       => __( 'Title', 'klarna-payments-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title. Changes what the payment method is called on the order recieved page aswell as the email that is sent to the customer.', 'klarna-payments-for-woocommerce' ),
				'default'     => 'Klarna',
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'klarna-payments-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'klarna-payments-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode'             => array(
				'title'       => __( 'Test mode', 'klarna-payments-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'klarna-payments-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'              => array(
				'title'       => __( 'Logging', 'klarna-payments-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-payments-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'extra_logging'        => array(
				'title'       => __( 'Log extra data', 'klarna-payments-for-woocommerce' ),
				'label'       => __( 'Log extra debug data', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Log extra data to the request logs from the plugin. This will log a lot more data, and should not be used unless you need it. But can be usefull to debug issues that are hard to replicate.', 'klarna-payments-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'hide_what_is_klarna'  => array(
				'title'    => __( 'Hide "What is Klarna?" link', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'If checked, "What is Klarna?" will not be shown.', 'klarna-payments-for-woocommerce' ),
				'default'  => 'no',
				'desc_tip' => true,
			),
			'float_what_is_klarna' => array(
				'title'    => __( 'Float "What is Klarna?" link', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'If checked, "What is Klarna?" will be floated right.', 'klarna-payments-for-woocommerce' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'send_product_urls'    => array(
				'title'    => __( 'Product URLs', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'Send product and product image URLs to Klarna', 'klarna-payments-for-woocommerce' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'add_to_email'         => array(
				'title'    => __( 'Add Klarna Urls to order email', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'This will add Klarna urls to the order emails that are sent. You can read more about this here: ', 'klarna-payments-for-woocommerce' ) . '<a href="https://docs.klarna.com/guidelines/klarna-payments-best-practices/post-purchase-experience/order-confirmation/" target="_blank">Klarna URLs</a>',
				'default'  => 'no',
				'desc_tip' => true,
			),
			'customer_type'        => array(
				'title'       => __( 'Customer type', 'klarna-payments-for-woocommerce' ),
				'type'        => 'select',
				'label'       => __( 'Customer type', 'klarna-payments-for-woocommerce' ),
				'description' => __( 'Select the customer for the store.', 'klarna-payments-for-woocommerce' ),
				'options'     => array(
					'b2c' => __( 'B2C', 'klarna-payments-for-woocommerce' ),
					'b2b' => __( 'B2B', 'klarna-payments-for-woocommerce' ),
				),
				'default'     => 'b2c',
				'desc_tip'    => true,
			),
		);

		$countries = array();
		foreach ( self::$kp_form_auto_countries as $cc => $values ) {
			$countries = array_merge( $countries, self::kp_form_country_section( $values['name'], $cc ) );
		}

		$settings = array_merge( $settings, $countries );

		$settings = array_merge(
			$settings,
			array(
				'iframe_options'        => array(
					'title' => 'Iframe settings',
					'type'  => 'title',
				),
				'color_border'          => array(
					'title'    => 'Border color',
					'type'     => 'color',
					'default'  => '',
					'desc_tip' => true,
				),
				'color_border_selected' => array(
					'title'    => 'Selected border color',
					'type'     => 'color',
					'default'  => '',
					'desc_tip' => true,
				),
				'color_text'            => array(
					'title'    => 'Text color',
					'type'     => 'color',
					'default'  => '',
					'desc_tip' => true,
				),
				'color_details'         => array(
					'title'    => 'Details color',
					'type'     => 'color',
					'default'  => '',
					'desc_tip' => true,
				),
				'radius_border'         => array(
					'title'    => 'Border radius (px)',
					'type'     => 'number',
					'default'  => '',
					'desc_tip' => true,
				),
			)
		);

		/* Klarna Express Checkout (aka Express Button). */
		if ( apply_filters( 'kp_enable_express_button', false ) ) {
			$settings = array_merge(
				$settings,
				array(
					'express_options'     => array(
						'title' => 'Express Checkout (Express Button)',
						'type'  => 'title',
					),
					'express_enabled'     => array(
						'title'   => __( 'Show Express Button on cart page', 'klarna-payments-for-woocommerce' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable', 'klarna-payments-for-woocommerce' ),
						'default' => 'no',

					),
					'express_data_theme'  => array(
						'title'       => __( 'Theme', 'klarna-payments-for-woocommerce' ),
						'description' => __( 'The color of the button.', 'klarna-payments-for-woocommerce' ),
						'type'        => 'select',
						'default'     => 'default',
						'options'     => array(
							'default' => __( 'Default', 'klarna-payments-for-woocommerce' ),
							'dark'    => __( 'Dark', 'klarna-payments-for-woocommerce' ),
							'light'   => __( 'Light', 'klarna-payments-for-woocommerce' ),
						),
					),
					'express_data_shape'  => array(
						'title'   => __( 'Shape', 'klarna-payments-for-woocommerce' ),
						'type'    => 'select',
						'default' => 'default',
						'options' => array(
							'default' => __( 'Default', 'klarna-payments-for-woocommerce' ),
							'rect'    => __( 'Rectangular', 'klarna-payments-for-woocommerce' ),
							'pill'    => __( 'Pill', 'klarna-payments-for-woocommerce' ),
						),
					),
					'express_data_label'  => array(
						'title'   => __( 'Label', 'klarna-payments-for-woocommerce' ),
						'type'    => 'select',
						'default' => 'default',
						'options' => array(
							'default' => __( 'Default', 'klarna-payments-for-woocommerce' ),
							'klarna'  => __( 'Klarna', 'klarna-payments-for-woocommerce' ),
						),
					),
					'express_data_width'  => array(
						'title'       => __( 'Button width', 'klarna-payments-for-woocommerce' ),
						'type'        => 'text',
						'default'     => '',
						'description' => __( 'A value between 145 and 500 (measured in pixels). Leave blank for default width.', 'klarna-payments-for-woocommerce' ),
					),
					'express_data_height' => array(
						'title'       => __( 'Button height', 'klarna-payments-for-woocommerce' ),
						'type'        => 'text',
						'defualt'     => '',
						'description' => __( 'A value between 35 and 60 (measured in pixels). Leave blank for default height.', 'klarna-payments-for-woocommerce' ),
					),
				)
			);
		}

		return $settings;
	}

	/**
	 * Get the full list of Klarna Payments settings form fields.
	 * Filter 'wc_gateway_klarna_payments_settings' is applied before returning.
	 *
	 * @return array Filtered settings for Klarna Payments.
	 */
	public static function get_form_fields() {
		return apply_filters( 'wc_gateway_klarna_payments_settings', self::kp_form_build_settings() );
	}
}
