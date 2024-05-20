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
	 * List of available countries for Klarna Payments.
	 *
	 * @param string $region The region to get countries for. Default is 'all'.
	 *
	 * @return array
	 */
	public static function available_countries( $region = 'all' ) {
		$eu = array(
			'at' => __( 'Austria', 'klarna-payments-for-woocommerce' ),
			'be' => __( 'Belgium', 'klarna-payments-for-woocommerce' ),
			'cz' => __( 'Czech Republic', 'klarna-payments-for-woocommerce' ),
			'dk' => __( 'Denmark', 'klarna-payments-for-woocommerce' ),
			'fi' => __( 'Finland', 'klarna-payments-for-woocommerce' ),
			'fr' => __( 'France', 'klarna-payments-for-woocommerce' ),
			'de' => __( 'Germany', 'klarna-payments-for-woocommerce' ),
			'gr' => __( 'Greece', 'klarna-payments-for-woocommerce' ),
			'hu' => __( 'Hungary', 'klarna-payments-for-woocommerce' ),
			'ie' => __( 'Ireland', 'klarna-payments-for-woocommerce' ),
			'it' => __( 'Italy', 'klarna-payments-for-woocommerce' ),
			'nl' => __( 'Netherlands', 'klarna-payments-for-woocommerce' ),
			'no' => __( 'Norway', 'klarna-payments-for-woocommerce' ),
			'pl' => __( 'Poland', 'klarna-payments-for-woocommerce' ),
			'pt' => __( 'Portugal', 'klarna-payments-for-woocommerce' ),
			'ro' => __( 'Romania', 'klarna-payments-for-woocommerce' ),
			'es' => __( 'Spain', 'klarna-payments-for-woocommerce' ),
			'se' => __( 'Sweden', 'klarna-payments-for-woocommerce' ),
			'ch' => __( 'Switzerland', 'klarna-payments-for-woocommerce' ),
			'gb' => __( 'United Kingdom', 'klarna-payments-for-woocommerce' ),
		);

		$na = array(
			'ca' => __( 'Canada', 'klarna-payments-for-woocommerce' ),
			'mx' => __( 'Mexico', 'klarna-payments-for-woocommerce' ),
			'us' => __( 'United States', 'klarna-payments-for-woocommerce' ),
		);

		$oc = array(
			'au' => __( 'Australia', 'klarna-payments-for-woocommerce' ),
			'nz' => __( 'New Zealand', 'klarna-payments-for-woocommerce' ),
		);

		switch ( $region ) {
			case 'eu':
				$countries = $eu;
				break;
			case 'na':
				$countries = $na;
				break;
			case 'oc':
				$countries = $oc;
				break;
			default:
				$countries = array_merge( $eu, $na, $oc );
				break;
		}

		asort( $countries );

		return $countries;
	}

	/**
	 * Handle migration from legacy settings to new settings.
	 *
	 * @return void
	 */
	public static function migrate_legacy_settings() {
		// Check if we already migrated the settings.
		$has_migrated = get_option( 'kp_settings_migrated', 'no' );

		if ( 'yes' === $has_migrated ) {
			return;
		}

		// Get the settings from the plugin.
		$settings = get_option( 'woocommerce_klarna_payments_settings', array() );

		// Migrate EU credentials.
		$settings = self::migrate_eu_credentials( $settings );

		// Migrate NA credentials.
		$settings = self::migrate_na_credentials( $settings );

		// Migrate OC credentials.
		$settings = self::migrate_oc_credentials( $settings );

		// Save the settings.
		update_option( 'woocommerce_klarna_payments_settings', $settings );
		update_option( 'kp_settings_migrated', true );
	}

	/**
	 * Migrate production and test credentials for EU countries to new settings.
	 *
	 * @param array $settings The settings to migrate.
	 *
	 * @return array
	 */
	public static function migrate_eu_credentials( $settings ) {
		$eu_countries  = array_keys( self::available_countries( 'eu' ) );
		$migrated_prod = isset( $settings['eu_production_username'] ) && isset( $settings['eu_production_password'] );
		$migrated_test = isset( $settings['eu_test_username'] ) && isset( $settings['eu_test_password'] );

		$available_countries = $settings['available_countries'] ?? array();
		$available_countries = ! empty( $available_countries ) ? $available_countries : array();

		// If we have migrated both, and available countries contains any of the EU countries, we can return early.
		if ( $migrated_prod && $migrated_test && array_intersect( $eu_countries, $available_countries ) ) {
			return $settings;
		}

		// Loop each country and see if we have credentials for them.
		foreach ( $eu_countries as $country ) {
			$country_available  = false;
			$merchant_id        = $settings[ 'merchant_id_' . $country ] ?? '';
			$shared_secret      = $settings[ 'shared_secret_' . $country ] ?? '';
			$test_merchant_id   = $settings[ 'test_merchant_id_' . $country ] ?? '';
			$test_shared_secret = $settings[ 'test_shared_secret_' . $country ] ?? '';

			// Migrate any live credentials we have.
			if ( ! empty( $merchant_id ) && ! empty( $shared_secret ) && ! $migrated_prod ) {
				$settings['eu_production_username'] = $merchant_id;
				$settings['eu_production_password'] = $shared_secret;
				$migrated_prod                      = true;
				$country_available                  = true;
			}

			// Migrate any test credentials we have.
			if ( ! empty( $test_merchant_id ) && ! empty( $test_shared_secret ) && ! $migrated_test ) {
				$settings['eu_test_username'] = $test_merchant_id;
				$settings['eu_test_password'] = $test_shared_secret;
				$migrated_test                = true;
				$country_available            = true;
			}

			if ( $country_available ) {
				$available_countries[] = $country;
			}
		}

		$settings['available_countries'] = $available_countries;
		return $settings;
	}

	/**
	 * Migrate production and test credentials for NA countries to new settings.
	 *
	 * @param array $settings The settings to migrate.
	 *
	 * @return array
	 */
	public static function migrate_na_credentials( $settings ) {
		// US, CA and MX should be stored as separate settings still.
		$na_countries = array_keys( self::available_countries( 'na' ) );

		$available_countries = $settings['available_countries'] ?? array();
		$available_countries = ! empty( $available_countries ) ? $available_countries : array();

		foreach ( $na_countries as $country ) {
			$merchant_id        = $settings[ 'merchant_id_' . $country ] ?? '';
			$shared_secret      = $settings[ 'shared_secret_' . $country ] ?? '';
			$test_merchant_id   = $settings[ 'test_merchant_id_' . $country ] ?? '';
			$test_shared_secret = $settings[ 'test_shared_secret_' . $country ] ?? '';
			$country_available  = false;

			// Migrate any live credentials we have.
			if ( ! empty( $merchant_id ) && ! empty( $shared_secret ) ) {
				$settings[ $country . '_production_username' ] = $merchant_id;
				$settings[ $country . '_production_password' ] = $shared_secret;
				$country_available                             = true;
			}

			// Migrate any test credentials we have.
			if ( ! empty( $test_merchant_id ) && ! empty( $test_shared_secret ) ) {
				$settings[ $country . '_test_username' ] = $test_merchant_id;
				$settings[ $country . '_test_password' ] = $test_shared_secret;
				$country_available                       = true;
			}

			if ( $country_available ) {
				$available_countries[] = $country;
			}
		}

		$settings['available_countries'] = $available_countries;
		return $settings;
	}

	/**
	 * Migrate production and test credentials for OC countries to new settings.
	 *
	 * @param array $settings The settings to migrate.
	 *
	 * @return array
	 */
	public static function migrate_oc_credentials( $settings ) {
		$oc_countries = array_keys( self::available_countries( 'oc' ) );

		$available_countries = $settings['available_countries'] ?? array();
		$available_countries = ! empty( $available_countries ) ? $available_countries : array();

		foreach ( $oc_countries as $country ) {
			$merchant_id        = $settings[ 'merchant_id_' . $country ] ?? '';
			$shared_secret      = $settings[ 'shared_secret_' . $country ] ?? '';
			$test_merchant_id   = $settings[ 'test_merchant_id_' . $country ] ?? '';
			$test_shared_secret = $settings[ 'test_shared_secret_' . $country ] ?? '';
			$country_available  = false;

			// Migrate any live credentials we have.
			if ( ! empty( $merchant_id ) && ! empty( $shared_secret ) ) {
				$settings[ $country . '_production_username' ] = $merchant_id;
				$settings[ $country . '_production_password' ] = $shared_secret;
				$country_available                             = true;
			}

			// Migrate any test credentials we have.
			if ( ! empty( $test_merchant_id ) && ! empty( $test_shared_secret ) ) {
				$settings[ $country . '_test_username' ] = $test_merchant_id;
				$settings[ $country . '_test_password' ] = $test_shared_secret;
				$country_available                       = true;
			}

			if ( $country_available ) {
				$available_countries[] = $country;
			}
		}

		$settings['available_countries'] = $available_countries;
		return $settings;
	}

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
			'title'             => __( 'Production Klarna API username', 'klarna-payments-for-woocommerce' ),
			'type'              => 'text',
			'description'       => __( 'Use the API username you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => false,
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);
	}

	/**
	 * Get the standard production password section
	 *
	 * @return array Production Password form section
	 */
	private static function kp_form_production_password() {
		return array(
			'title'             => __( 'Production Klarna API password', 'klarna-payments-for-woocommerce' ),
			'type'              => 'password',
			'description'       => __( 'Use the API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => false,
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);
	}

	/**
	 * Get the standard test username section
	 *
	 * @return array Test Username form section
	 */
	private static function kp_form_test_username() {
		return array(
			'title'             => __( 'Test Klarna API username', 'klarna-payments-for-woocommerce' ),
			'type'              => 'text',
			'description'       => __( 'Use the API username you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => false,
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
		);
	}

	/**
	 * Get the standard test password section
	 *
	 * @return array Test Password form section
	 */
	private static function kp_form_test_password() {
		return array(
			'title'             => __( 'Test Klarna API password', 'klarna-payments-for-woocommerce' ),
			'type'              => 'password',
			'description'       => __( 'Use the API password you downloaded in the Klarna Merchant Portal. Don’t use your email address.', 'klarna-payments-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => false,
			'custom_attributes' => array(
				'autocomplete' => 'off',
			),
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
	 * Build the settings array.
	 *
	 * @return array
	 */
	public static function build_settings() {
		$form_fields = array(
			'general'             => array(
				'id'          => 'general',
				'title'       => 'Klarna Payments',
				'description' => __( 'Enable or disable Klarna payments, depending on your setup, enter client keys and turn on test mode.', 'klarna-payments-for-woocommerce' ),
				'links'       => array(
					array(
						'url'   => 'https://krokedil.se',
						'title' => __( 'Learn more', 'klarna-payments-for-woocommerce' ),
					),
					array(
						'url'   => 'https://krokedil.se',
						'title' => __( 'Documentation', 'klarna-payments-for-woocommerce' ),
					),
				),
				'type'        => 'kp_section_start',
			),
			'enabled'             => array(
				'label'       => __( 'Enable Klarna Payments', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
				'class'       => 'kp_settings__hide_label',
			),
			'testmode'            => array(
				'label'    => __( 'Enable Klarna Payments in Klarna\'s test environment.', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc_tip' => true,
				'class'    => 'kp_settings__hide_label',
			),
			'title'               => array(
				'title'    => __( 'Title', 'klarna-payments-for-woocommerce' ),
				'type'     => 'text',
				'default'  => 'Klarna',
				'desc_tip' => true,
			),
			'customer_type'       => array(
				'title'    => __( 'Select the type of customer that you sell to', 'klarna-payments-for-woocommerce' ),
				'type'     => 'select',
				'options'  => array(
					'b2c' => __( 'B2C', 'klarna-payments-for-woocommerce' ),
					'b2b' => __( 'B2B', 'klarna-payments-for-woocommerce' ),
				),
				'default'  => 'b2c',
				'desc_tip' => true,
			),
			'markets'             => array(
				'title'       => __( 'Markets & regional API Credentials', 'klarna-payments-for-woocommerce' ),
				'description' => __( 'Enter the countries you plan to make Klarna available and then enter the respective test and production credentials for each sales region', 'klarna-payments-for-woocommerce' ),
				'type'        => 'kp_text_info',
			),
			'available_countries' => array(
				'title'       => __( 'Countries where you plan to make Klarna available', 'klarna-payments-for-woocommerce' ),
				'type'        => 'multiselect',
				'options'     => self::available_countries(),
				'class'       => 'wc-enhanced-select',
				'default'     => '',
				'placeholder' => __( 'Start typing', 'klarna-payments-for-woocommerce' ),
			),
			'general_end'         => array(
				'type'        => 'kp_section_end',
				'preview_img' => WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/kp-general-preview.png',
			),
			'credentials'         => array(
				'id'    => 'credentials',
				'title' => 'API Credentials',
				'type'  => 'kp_section_start',
			),
		);

		// Add the credentials fields.
		$eu = self::get_credential_fields( 'eu', __( 'API Credentials for Europe:', 'klarna-payments-for-woocommerce' ) );
		$us = self::get_credential_fields( 'us', __( 'API Credentials for the US:', 'klarna-payments-for-woocommerce' ) );
		$ca = self::get_credential_fields( 'ca', __( 'API Credentials for Canada:', 'klarna-payments-for-woocommerce' ) );
		$mx = self::get_credential_fields( 'mx', __( 'API Credentials for Mexico:', 'klarna-payments-for-woocommerce' ) );
		$au = self::get_credential_fields( 'au', __( 'API Credentials for Australia:', 'klarna-payments-for-woocommerce' ) );
		$nz = self::get_credential_fields( 'nz', __( 'API Credentials for New Zealand:', 'klarna-payments-for-woocommerce' ) );

		$form_fields = array_merge( $form_fields, $eu, $us, $ca, $mx, $au, $nz );

		$form_fields['credentials_end'] = array(
			'type' => 'kp_section_end',
		);

		return apply_filters( 'wc_klarna_payments_form_fields', $form_fields );
	}

	/**
	 * Get credential settings fields
	 *
	 * @param string $key   The key for the settings field.
	 * @param string $title The title for the settings field.
	 *
	 * @return array
	 */
	private static function get_credential_fields( $key, $title ) {
		return array(
			"{$key}_credentials"         => array(
				'title' => $title,
				'type'  => 'kp_credentials',
				'key'   => $key,
			),
			"{$key}_test_username"       => array(
				'type'              => 'text',
				'default'           => '',
				'title'             => __( 'Username (Test)', 'klarna-payments-for-woocommerce' ),
				'placeholder'       => ' ',
				'class'             => 'kp_settings__credentials_field kp_settings__credentials_field_hidden',
				'custom_attributes' => array(
					'data-field-key' => $key,
				),
			),
			"{$key}_test_password"       => array(
				'type'              => 'password',
				'default'           => '',
				'title'             => __( 'Password (Test)', 'klarna-payments-for-woocommerce' ),
				'placeholder'       => ' ',
				'class'             => 'kp_settings__credentials_field kp_settings__credentials_field_hidden',
				'custom_attributes' => array(
					'data-field-key' => $key,
				),
			),
			"{$key}_production_username" => array(
				'type'              => 'text',
				'default'           => '',
				'title'             => __( 'Username (Production)', 'klarna-payments-for-woocommerce' ),
				'placeholder'       => ' ',
				'class'             => 'kp_settings__credentials_field kp_settings__credentials_field_hidden',
				'key'               => $key,
				'custom_attributes' => array(
					'data-field-key' => $key,
				),
			),
			"{$key}_production_password" => array(
				'type'              => 'password',
				'default'           => '',
				'title'             => __( 'Password (Production)', 'klarna-payments-for-woocommerce' ),
				'placeholder'       => ' ',
				'class'             => 'kp_settings__credentials_field kp_settings__credentials_field_hidden',
				'custom_attributes' => array(
					'data-field-key' => $key,
				),
			),
		);
	}

	/**
	 * Get the full list of Klarna Payments settings form fields.
	 * Filter 'wc_gateway_klarna_payments_settings' is applied before returning.
	 *
	 * @return array Filtered settings for Klarna Payments.
	 */
	public static function get_form_fields() {
		$form_fields        = apply_filters( 'wc_gateway_klarna_payments_settings', self::build_settings() );
		$parsed_form_fields = array();

		$has_section_end = true;

		foreach ( $form_fields as $key => $value ) {
			$type = isset( $value['type'] ) ? $value['type'] : '';
			// Replace any title types with the custom type kp_section_start.
			if ( 'title' === $type ) {
				// If we don't have a section end when we find a new title, add one before it.
				if ( ! $has_section_end ) {
					$parsed_form_fields[ 'section_end_' . $key ] = array(
						'type' => 'kp_section_end',
					);
				}

				$value['type']   = 'kp_section_start';
				$value['id']     = $key;
				$has_section_end = false;
			}

			// Replace any sectionend types with the custom type kp_section_end.
			if ( 'sectionend' === $type ) {
				$has_section_end = true;
				$value['type']   = 'kp_section_end';
			}

			$parsed_form_fields[ $key ] = $value;
		}

		// If we don't have a section end at the end of the form, add one.
		if ( ! $has_section_end ) {
			$parsed_form_fields['section_end_final'] = array(
				'type' => 'kp_section_end',
			);
		}

		return $parsed_form_fields;
	}
}
