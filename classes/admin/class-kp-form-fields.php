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
		'sk' => array(
			'name'     => 'Slovakia',
			'currency' => 'EUR',
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
			'sk' => __( 'Slovakia', 'klarna-payments-for-woocommerce' ),
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
	 * Get the credential section fields.
	 *
	 * @param array $settings The settings array.
	 *
	 * @return array
	 */
	public static function get_credential_section_fields( $settings = array() ) {
		$saved_settings = get_option( 'woocommerce_klarna_payments_settings', array() );

		$merchant_portal_html = '<a href="https://portal.klarna.com" target="_blank">' . __( 'Klarna Merchant Portal', 'klarna-payments-for-woocommerce' ) . '</a>';

		$privacy_policy_html = '<a href="https://portal.klarna.com/privacy-policy" target="_blank">' . __( 'Klarna Merchant Privacy Notice', 'klarna-payments-for-woocommerce' ) . '</a>';
		// translators: %s: privacy policy link.
		$credentials_html = sprintf( __( 'By activating Klarna using API credentials you agree to and accept the %s.', 'klarna-payments-for-woocommerce' ), $privacy_policy_html );

		$credentials_section = array(
			'credentials'         => array(
				'id'          => 'credentials',
				'title'       => 'Credentials',
				'description' => __( 'To unlock the plugin\'s features, enter your credentials', 'klarna-payments-for-woocommerce' ),
				'type'        => 'kp_section_start',
				'links'       => array(
					array(
						'url'   => 'https://docs.klarna.com/',
						'title' => __( 'Documentation', 'klarna-payments-for-woocommerce' ),
					),
				),
			),
			'credentials_info'    => array(
				'type'        => 'kp_text_info',
				'title'       => __( 'Client Identifier & API Credentials', 'klarna-payments-for-woocommerce' ),
				// translators: %s: merchant portal link.
				'description' => sprintf( __( 'Enter the credentials for production and test for each market Klarna is used. Get the client identifier and API credentials from the %1$s, under Settings. <br><br><b>%2$s</b>', 'klarna-payments-for-woocommerce' ), $merchant_portal_html, $credentials_html ),
			),
			'available_countries' => array(
				'title'       => __( 'Enter the countries where Klarna will be available', 'klarna-payments-for-woocommerce' ) . ':',
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'options'     => self::available_countries(),
				'placeholder' => __( 'Start typing', 'klarna-payments-for-woocommerce' ),
				'default'     => '',
			),
			'testmode'            => array(
				'title'    => __( 'Test mode', 'klarna-payments-for-woocommerce' ),
				'label'    => __( 'Enable Klarna in Klarna\'s test environment.', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => true,
			),
			'eu_credentials'      => array(
				'title' => __( 'European Market', 'klarna-payments-for-woocommerce' ),
				'type'  => 'kp_text_info',
			),
		);

		$eu_countries = self::available_countries( 'eu' );

		// Add the credentials fields.
		$eu = self::get_credential_fields( 'eu', __( 'Credentials for Europe', 'klarna-payments-for-woocommerce' ) );

		// Add input fields for all EU countries as well.
		foreach ( $eu_countries as $key => $name ) {
			$eu = array_merge( $eu, self::get_eu_country_fields( $key, $name ) );
		}
		$eu['combine_eu_credentials'] = array(
			'title'       => __( 'Combine EU credentials', 'klarna-payments-for-woocommerce' ),
			'label'       => __( 'Combine all EU country credentials into a single', 'klarna-payments-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => empty( $saved_settings ) ? 'yes' : 'no', // Default to yes for new installations, but no for existing.
		);
		$na                           = array_merge(
			array(
				'na_credentials' => array(
					'title' => __( 'North American Market', 'klarna-payments-for-woocommerce' ),
					'type'  => 'kp_text_info',
				),
			),
			self::get_credential_fields( 'us', __( 'Credentials for the US', 'klarna-payments-for-woocommerce' ) ),
			self::get_credential_fields( 'ca', __( 'Credentials for Canada', 'klarna-payments-for-woocommerce' ) ),
			self::get_credential_fields( 'mx', __( 'Credentials for Mexico', 'klarna-payments-for-woocommerce' ) )
		);

		$oc = array_merge(
			array(
				'oc_credentials' => array(
					'title' => __( 'Oceania Market', 'klarna-payments-for-woocommerce' ),
					'type'  => 'kp_text_info',
				),
			),
			self::get_credential_fields( 'au', __( 'Credentials for Australia', 'klarna-payments-for-woocommerce' ) ),
			self::get_credential_fields( 'nz', __( 'Credentials for New Zealand', 'klarna-payments-for-woocommerce' ) )
		);

		$credentials_section = array_merge( $credentials_section, $eu, $na, $oc );

		$credentials_section['credentials_end'] = array(
			'type' => 'kp_section_end',
		);

		return array_merge( $settings, $credentials_section );
	}

	/**
	 * Get the general section fields.
	 *
	 * @param array $settings The settings array.
	 *
	 * @return array
	 */
	public static function get_kp_section_fields( $settings = array() ) {
		$kp_section = array(
			'general'              => array(
				'id'          => 'general',
				'title'       => 'Klarna Payments',
				'description' => __( 'Give your customers the ability to pay in flexible ways such as Buy now, Pay Later, Invoicing, Installments and Financing.', 'klarna-payments-for-woocommerce' ),
				'links'       => array(
					array(
						'url'   => 'https://docs.klarna.com/klarna-payments/',
						'title' => __( 'Documentation', 'klarna-payments-for-woocommerce' ),
					),
				),
				'type'        => 'kp_section_start',
			),
			'enabled'              => array(
				'title'       => __( 'Enable/Disable', 'klarna-payments-for-woocommerce' ),
				'label'       => __( 'Enable Klarna Payments', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'logging'              => array(
				'title'       => __( 'Logging', 'klarna-payments-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'klarna-payments-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'klarna-payments-for-woocommerce' ),
				'default'     => 'no',
				'options'     => array(
					'no'    => __( 'No', 'klarna-payments-for-woocommerce' ),
					'yes'   => __( 'Yes', 'klarna-payments-for-woocommerce' ),
					'extra' => __( 'Yes (with extra debug data)', 'klarna-payments-for-woocommerce' ),
				),
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
				'desc_tip' => false,
			),
			'send_product_urls'    => array(
				'title'    => __( 'Product URLs', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'Send product and product image URLs to Klarna', 'klarna-payments-for-woocommerce' ),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'add_to_email'         => array(
				'title'    => __( 'Add Klarna URLs to order email', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'label'    => __( 'This will add Klarna URLs to the order emails that are sent. You can read more about this here: ', 'klarna-payments-for-woocommerce' ) . '<a href="https://docs.klarna.com/payments/web-payments/additional-resources/ux-guidelines/post-purchase-experience/" target="_blank">Klarna URLs</a>',
				'default'  => 'no',
				'desc_tip' => false,
			),
			'customer_type'        => array(
				'title'       => __( 'Customer type', 'klarna-payments-for-woocommerce' ),
				'type'        => 'select',
				'label'       => __( 'Customer type', 'klarna-payments-for-woocommerce' ),
				'description' => __( 'Select the customer for the store.', 'klarna-payments-for-woocommerce' ),
				'options'     => array(
					'b2c' => __( 'Business to Consumer (B2C)', 'klarna-payments-for-woocommerce' ),
					'b2b' => __( 'Business to Business (B2B)', 'klarna-payments-for-woocommerce' ),
				),
				'default'     => 'b2c',
				'desc_tip'    => true,
			),
			'general_end'          => array(
				'type'     => 'kp_section_end',
				'previews' => array(
					array(
						'title' => __( 'Preview', 'klarna-payments-for-woocommerce' ),
						'image' => WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/kp-preview.png',
					),
				),
			),
		);

			return array_merge( $settings, $kp_section );
	}

	/**
	 * Get the fields for a specific EU country.
	 *
	 * @param string $key  The key for the settings field.
	 * @param string $name The country name.
	 *
	 * @return array
	 */
	private static function get_eu_country_fields( $key, $name ) {
		// translators: %s: country name.
		$title = sprintf( __( 'Credentials for %s:', 'klarna-payments-for-woocommerce' ), $name );

		$fields = self::get_credential_fields( $key, $title );

		// Set the class to hide the fields unless advanced checkbox is set.
		foreach ( $fields as $field_key => $field ) {
			$classes                       = isset( $field['class'] ) ? $field['class'] : '';
			$fields[ $field_key ]['class'] = $classes . ' kp_settings__credentials_eu_country_field';
		}

		return $fields;
	}

	/**
	 * Get credential settings fields.
	 * Hidden settings are required for our custom input fields to work properly when saving the settings.
	 *
	 * @param string $key   The key for the settings field.
	 * @param string $title The title for the settings field.
	 *
	 * @return array
	 */
	private static function get_credential_fields( $key, $title ) {
		return array(
			"merchant_id_{$key}"        => array(
				'type'    => 'hidden',
				'default' => '',
			),
			"shared_secret_{$key}"      => array(
				'type'    => 'hidden',
				'default' => '',
			),
			"client_id_{$key}"          => array(
				'type'    => 'hidden',
				'default' => '',
			),
			"test_merchant_id_{$key}"   => array(
				'type'    => 'hidden',
				'default' => '',
			),
			"test_shared_secret_{$key}" => array(
				'type'    => 'hidden',
				'default' => '',
			),
			"test_client_id_{$key}"     => array(
				'type'    => 'hidden',
				'default' => '',
			),
			"credentials_{$key}"        => array(
				'title' => $title,
				'type'  => 'kp_credentials',
				'class' => '',
				'key'   => $key,
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
		add_filter( 'wc_gateway_klarna_payments_settings', array( __CLASS__, 'get_credential_section_fields' ), 1 );
		add_filter( 'wc_gateway_klarna_payments_settings', array( __CLASS__, 'get_kp_section_fields' ), 2 );

		$form_fields        = apply_filters( 'wc_gateway_klarna_payments_settings', array() );
		$parsed_form_fields = array();

		$has_section_end = true;
		$previous_key    = 'none';

		foreach ( $form_fields as $key => $value ) {
			$type = isset( $value['type'] ) ? $value['type'] : '';
			// Replace any title types with the custom type kp_section_start.
			if ( 'title' === $type || 'kp_section_start' === $type ) {
				// If we don't have a section end when we find a new title, add one before it.
				if ( ! $has_section_end ) {
					$parsed_form_fields[ 'section_end_' . $previous_key ] = array(
						'type' => 'kp_section_end',
					);
				}

				$value['type']   = 'kp_section_start';
				$value['id']     = $key;
				$has_section_end = false;
				$previous_key    = $key;
			} elseif ( 'sectionend' === $type ) { // Replace any sectionend types with the custom type kp_section_end.
				$has_section_end = true;
				$value['type']   = 'kp_section_end';
			} elseif ( 'kp_section_end' === $type ) {
				$has_section_end = true;
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
