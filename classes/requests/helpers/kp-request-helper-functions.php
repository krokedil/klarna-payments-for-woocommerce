<?php
/**
 * File for simple helper functions for API request.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

/**
 * Gets the locale need for the klarna country.
 *
 * @param string $klarna_country Klarna country.
 * @return string
 */
function get_locale_for_klarna_country( $klarna_country ) {
	$$has_english_locale = 'en_US' === get_locale() || 'en_GB' === get_locale();
	switch ( $klarna_country ) {
		case 'AT':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-at';
			} else {
				$klarna_locale = 'de-at';
			}
			break;
		case 'BE':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-be';
			} elseif ( 'fr_be' === strtolower( get_locale() ) ) {
				$klarna_locale = 'fr-be';
			} else {
				$klarna_locale = 'nl-be';
			}
			break;
		case 'CA':
			$klarna_locale = 'en-ca';
			break;
		case 'CH':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-ch';
			} else {
				$klarna_locale = 'de-ch';
			}
			break;
		case 'DE':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-de';
			} else {
				$klarna_locale = 'de-de';
			}
			break;
		case 'DK':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-dk';
			} else {
				$klarna_locale = 'da-dk';
			}
			break;
		case 'ES':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-es';
			} else {
				$klarna_locale = 'es-es';
			}
			break;
		case 'FI':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-fi';
			} elseif ( 'sv_se' === strtolower( get_locale() ) ) {
				$klarna_locale = 'sv-fi';
			} else {
				$klarna_locale = 'fi-fi';
			}
			break;
		case 'IT':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-it';
			} else {
				$klarna_locale = 'it-it';
			}
			break;
		case 'NL':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-nl';
			} else {
				$klarna_locale = 'nl-nl';
			}
			break;
		case 'NO':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-no';
			} else {
				$klarna_locale = 'nb-no';
			}
			break;
		case 'PL':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-pl';
			} else {
				$klarna_locale = 'pl-pl';
			}
			break;
		case 'SE':
			if ( $has_english_locale ) {
				$klarna_locale = 'en-se';
			} else {
				$klarna_locale = 'sv-se';
			}
			break;
		case 'GB':
			$klarna_locale = 'en-gb';
			break;
		case 'US':
			$klarna_locale = 'en-us';
			break;
		default:
			$klarna_locale = 'en-us';
	}
	return $klarna_locale;
}

/**
 * Adds the customer object to the request arguments.
 *
 * @param string $customer_type The customer type from the settings.
 * @return array
 */
function get_klarna_customer( $customer_type ) {
	$type = ( 'b2c' === $customer_type ) ? 'person' : 'organization';
	return array(
		'type' => $type,
	);
}
