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
	 * Build the settings array.
	 *
	 * @return array
	 */
	public static function build_settings() {
		$form_fields = array(
			'general'                => array(
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
			'enabled'                => array(
				'label'       => __( 'Enable Klarna Payments', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
				'class'       => 'kp_settings__hide_label',
			),
			'testmode'               => array(
				'label'    => __( 'Enable Klarna Payments in Klarna\'s test environment.', 'klarna-payments-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc_tip' => true,
				'class'    => 'kp_settings__hide_label',
			),
			'title'                  => array(
				'title'    => __( 'Title', 'klarna-payments-for-woocommerce' ),
				'type'     => 'text',
				'default'  => 'Klarna',
				'desc_tip' => true,
			),
			'customer_type'          => array(
				'title'    => __( 'Select the type of customer that you sell to', 'klarna-payments-for-woocommerce' ),
				'type'     => 'select',
				'options'  => array(
					'b2c' => __( 'B2C', 'klarna-payments-for-woocommerce' ),
					'b2b' => __( 'B2B', 'klarna-payments-for-woocommerce' ),
				),
				'default'  => 'b2c',
				'desc_tip' => true,
			),
			'markets'                => array(
				'title'       => __( 'Markets & regional API Credentials', 'klarna-payments-for-woocommerce' ),
				'description' => __( 'Enter the countries you plan to make Klarna available and then enter the respective test and production credentials for each sales region', 'klarna-payments-for-woocommerce' ),
				'type'        => 'kp_text_info',
			),
			'available_countries'    => array(
				'title'       => __( 'Countries where you plan to make Klarna available', 'klarna-payments-for-woocommerce' ),
				'type'        => 'multiselect',
				'options'     => self::available_countries(),
				'class'       => 'wc-enhanced-select',
				'default'     => '',
				'placeholder' => __( 'Start typing', 'klarna-payments-for-woocommerce' ),
			),
			'general_end'            => array(
				'type'        => 'kp_section_end',
				'preview_img' => WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/kp-general-preview.png',
			),
			'credentials'            => array(
				'id'    => 'credentials',
				'title' => 'API Credentials',
				'type'  => 'kp_section_start',
			),
			'combine_eu_credentials' => array(
				'label'       => __( 'Combine all EU country credentials', 'klarna-payments-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
				'class'       => 'kp_settings__hide_label',
			),
		);

		$eu_countries = self::available_countries( 'eu' );

		// Add the credentials fields.
		$eu = self::get_credential_fields( 'eu', __( 'API Credentials for Europe:', 'klarna-payments-for-woocommerce' ) );

		// Add input fields for all EU countries as well.
		foreach ( $eu_countries as $key => $name ) {
			$eu = array_merge( $eu, self::get_eu_country_fields( $key, $name ) );
		}

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
	 * Get the fields for a specific EU country.
	 *
	 * @param string $key  The key for the settings field.
	 * @param string $name The country name.
	 *
	 * @return array
	 */
	private static function get_eu_country_fields( $key, $name ) {
		// translators: %s: country name.
		$title = sprintf( __( 'API Credentials for %s:', 'klarna-payments-for-woocommerce' ), $name );

		$fields = self::get_credential_fields( $key, $title );

		// Set the class to hide the fields unless advanced checkbox is set.
		foreach ( $fields as $field_key => $field ) {
			$classes                       = isset( $field['class'] ) ? $field['class'] : '';
			$fields[ $field_key ]['class'] = $classes . ' kp_settings__credentials_eu_country_field';
		}

		return $fields;
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
