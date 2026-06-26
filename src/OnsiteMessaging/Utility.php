<?php
namespace Krokedil\Klarna\OnsiteMessaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The orchestrator class.
 */
class Utility {
	/**
	 * Prints <klarna-placement> HTML element.
	 *
	 * @param array $data The data arguments.
	 * @return void
	 */
	public static function print_placement( $data ) {
		// Normalize keys.
		foreach ( $data as $key => $value ) {
			unset( $data[ $key ] );
			$key          = str_replace( 'data-', '', $key );
			$key          = str_replace( '-', '_', $key );
			$data[ $key ] = $value;
		}

		// Remove any empty elements so that the defaults can be used instead.
		$data = array_filter(
			$data,
			function ( $property ) {
				return ! empty( $property );
			}
		);

		// The defaults to use.
		$data = wp_parse_args(
			$data,
			array(
				'key'             => '',
				'theme'           => 'default',
				'purchase_amount' => '',
			)
		);

		$key             = $data['key'];
		$theme           = $data['theme'];
		$purchase_amount = $data['purchase_amount'];

		$locale = self::get_locale_from_currency();
		if ( empty( $locale ) ) {
			return;
		}

		$class = 'klarna-onsite-messaging';
		if ( ! is_cart() ) {
			if ( empty( $purchase_amount ) ) {
				$product = self::get_product();
				// If the purchase amount is not set, try to retrieve it from the product.
				if ( is_a( $product, 'WC_Product' ) ) {
					if ( $product->is_type( 'variable' ) ) {
						$purchase_amount = $product->get_variation_price( 'min' );
					} elseif ( $product->is_type( 'bundle' ) ) {
						$purchase_amount = $product->get_bundle_price( 'min' );
					} else {
						$purchase_amount = wc_get_price_to_display( $product );
					}

					// WOO-DISCOUNT-RULES: Check if the filter for retrieving the discounted price exists. Note: by default, quantity is 1.
					// See: https://gist.github.com/AshlinRejo/c37a155a42c0e30beafbbad183f0c4e8.
					if ( has_filter( 'advanced_woo_discount_rules_get_product_discount_price_from_custom_price' ) ) {
						$maybe_price     = apply_filters( 'advanced_woo_discount_rules_get_product_discount_price_from_custom_price', $purchase_amount, $product, 1, $purchase_amount, 'discounted_price', true );
						$purchase_amount = false !== $maybe_price ? $maybe_price : $purchase_amount;
					}

					// Force a numeric value.
					$purchase_amount = intval( number_format( $purchase_amount * 100, 0, '', '' ) );
					$class           = 'klarna-onsite-messaging-product';
				}

				// If we still cannot retrieve a product, we're most likely on a non-shop page, and there is no price amount to set.
			}
		} else {
			// Cart.
			$purchase_amount = empty( $purchase_amount ) ? WC()->cart->get_total( 'kosm' ) * 100 : $purchase_amount;
		}

		// Allow the merchant to hide the placement based on the purchase amount.
		if ( apply_filters( 'kosm_hide_placement', false, $purchase_amount ) ) {
			return;
		}

		?>
	<klarna-placement class=<?php echo esc_attr( $class ); ?>
		data-preloaded="true"
		data-key="<?php echo esc_attr( $key ); ?>"
		data-locale="<?php echo esc_attr( $locale ); ?>"
		data-theme="<?php echo esc_html( $theme ); ?>"
		data-purchase-amount="<?php echo esc_attr( $purchase_amount ); ?>"
	></klarna-placement>
		<?php
	}

	/**
	 * Get the product from the global product variable set by WooCommerce.
	 *
	 * @return \WC_Product|null|false The product if it can be retrieved. Otherwise, FALSE or null.
	 */
	public static function get_product() {
		global $product;
		global $post;

		if ( is_a( $product, 'WC_Product' ) ) {
			return $product;
		}

		if ( isset( $post ) && is_a( $post, 'WP_Post' ) ) {
			$product = wc_get_product( $post->ID );
			return is_a( $product, 'WC_Product' ) ? $product : false;
		}

		return false;
	}

	/**
	 * Retrieve the purchase country.
	 *
	 * @return string
	 */
	public static function get_purchase_country() {
		if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) ) {
			$country = WC()->customer->get_billing_country();
			if ( ! empty( $country ) ) {
				return $country;
			}
		}

		$base_location = wc_get_base_location();
		return $base_location['country'];
	}

	/**
	 * Gets the locale need for the klarna country.
	 *
	 * @param string $klarna_country Klarna country.
	 * @return string
	 */
	public static function get_klarna_locale( $klarna_country ) {
		$locale             = get_locale();
		$has_english_locale = 'en_US' === $locale || 'en_GB' === $locale;
		switch ( $klarna_country ) {
			case 'AT':
				$klarna_locale = $has_english_locale ? 'en-AT' : 'de-AT';
				break;
			case 'AU':
				$klarna_locale = 'en-AU';
				break;
			case 'BE':
				if ( $has_english_locale ) {
					$klarna_locale = 'en-BE';
				} elseif ( 'fr_be' === strtolower( $locale ) ) {
					$klarna_locale = 'fr-BE';
				} else {
					$klarna_locale = 'nl-BE';
				}
				break;
			case 'CA':
				$klarna_locale = 'fr_ca' === strtolower( $locale ) ? 'fr-CA' : 'en-CA';
				break;
			case 'CH':
				$klarna_locale = $has_english_locale ? 'en-CH' : 'de-CH';
				break;
			case 'DE':
				$klarna_locale = $has_english_locale ? 'en-DE' : 'de-DE';
				break;
			case 'DK':
				$klarna_locale = $has_english_locale ? 'en-DK' : 'da-DK';
				break;
			case 'ES':
				$klarna_locale = $has_english_locale ? 'en-ES' : 'es-ES';
				break;
			case 'FI':
				if ( $has_english_locale ) {
					$klarna_locale = 'en-FI';
				} elseif ( 'sv_se' === strtolower( $locale ) ) {
					$klarna_locale = 'sv-FI';
				} else {
					$klarna_locale = 'fi-FI';
				}
				break;
			case 'FR':
				$klarna_locale = $has_english_locale ? 'en-FR' : 'fr-FR';
				break;
			case 'GR': // Greece.
				$klarna_locale = $has_english_locale ? 'en-GR' : 'el-GR';
				break;
			case 'HU': // Hungary.
				$klarna_locale = $has_english_locale ? 'en-HU' : 'hu-HU';
				break;
			case 'IE':
				$klarna_locale = 'en-IE';
				break;
			case 'IT':
					$klarna_locale = $has_english_locale ? 'en-IT' : 'it-IT';
				break;
			case 'NL':
					$klarna_locale = $has_english_locale ? 'en-NL' : 'nl-NL';
				break;
			case 'NO':
				$klarna_locale = $has_english_locale ? 'en-NO' : 'no-NO';
				break;
			case 'NZ':
				$klarna_locale = 'en-NZ';
				break;
			case 'PL':
				$klarna_locale = $has_english_locale ? 'en-PL' : 'pl-PL';
				break;
			case 'PT':
				$klarna_locale = $has_english_locale ? 'en-PT' : 'pt-PT';
				break;
			case 'SE':
				$klarna_locale = $has_english_locale ? 'en-SE' : 'sv-SE';
				break;
			case 'SK':
				$klarna_locale = $has_english_locale ? 'en-SK' : 'sk-SK';
				break;
			case 'GB':
				$klarna_locale = 'en-GB';
				break;
			case 'US':
				$klarna_locale = 'en-US';
				break;
			default:
				$klarna_locale = 'en-US';
		}
		return $klarna_locale;
	}

	/**
	 * Gets the locale needed for the specified currency.
	 *
	 * @return string|bool The locale on success or false if unsupported.
	 */
	public static function get_locale_from_currency() {
		$locale       = get_locale();
		$currency     = get_woocommerce_currency();
		$country_code = self::get_purchase_country();

		switch ( $currency ) {
			case 'EUR': // Euro.
				$locale = self::get_locale_from_eur( $country_code, $locale );
				break;
			case 'AUD': // Australian Dollars.
				$locale = 'en-AU';
				break;
			case 'CAD': // Canadian Dollar.
				$locale = ( 'fr_CA' === $locale ) ? 'fr-CA' : 'en-CA';
				break;
			case 'CHF': // Swiss Frank.
				$locale = ( strpos( $locale, 'de_CH' ) !== false ) ? 'de-CH' : 'en-CH';
				break;
			case 'DKK': // Danish Kronor.
				$locale = ( 'da_DK' === $locale ) ? 'da-DK' : 'en-DK';
				break;
			case 'GBP': // Pounds.
				$locale = 'en-GB';
				break;
			case 'HUF': // Hungarian Forint.
				$locale = ( 'hu_HU' === $locale ) ? 'hu-HU' : 'en-HU';
				break;
			case 'NOK': // Norwegian Kronor.
				$locale = ( 'nn_NO' === $locale || 'nb_NO' === $locale ) ? 'no-NO' : 'en-NO';
				break;
			case 'SEK': // Swedish Kronor.
				$locale = ( 'sv_SE' === $locale ) ? 'sv-SE' : 'en-SE';
				break;
			case 'PLN': // Polish złoty.
				$locale = ( 'pl_PL' === $locale ) ? 'pl-PL' : 'en-PL';
				break;
			case 'USD': // Dollars.
				$locale = 'en-US';
				break;
			case 'NZD': // New Zealand Dollars.
				$locale = 'en-NZ';
				break;
			case 'RON':
				$locale = ( 'ro_RO' === $locale ) ? 'ro-RO' : 'en-RO';
				break;
			default:
				$locale = false;
		}
		return apply_filters( 'kosm_locale', $locale );
	}

	/**
	 * Retrieve the locale from European countries that utilize the EUR currency.
	 *
	 * Note: if the country code do not match any european country, the default locale will be returned.
	 *
	 * @param string $country_code ISO 3166-1 alpha-2 country code.
	 * @param string $locale WordPress locale.
	 * @return string locale (IETF BCP 47 language + region tag).
	 */
	public static function get_locale_from_eur( $country_code, $locale ) {
		// The merchant can set a fixed locale for EUR if no country code could be identified.
		$default_locale = apply_filters( 'kosm_default_euro_locale', 'en-DE' );
		// However, if they want to enforce is regardless of country code, they must use this filter.
		if ( apply_filters( 'kosm_force_euro_locale', false ) ) {
			return $default_locale;
		}

		switch ( $country_code ) {
			case 'AT': // Austria.
				$locale = ( 'de_AT' === $locale ) ? 'de-AT' : 'en-AT';
				break;
			case 'BE': // Belgium.
				if ( 'fr_BE' === $locale ) {
					$locale = 'fr-BE';
				} elseif ( 'nl_BE' === $locale ) {
					$locale = 'nl-BE';
				} else {
					$locale = 'en-BE';
				}
				break;
			case 'DE': // Germany.
				$locale = ( strpos( $locale, 'de_DE' ) !== false ) ? 'de-DE' : 'en-DE';
				break;
			case 'ES': // Spain.
				$locale = ( 'es_ES' === $locale ) ? 'es-ES' : 'en-ES';
				break;
			case 'FI': // Finland.
				if ( 'fi' === $locale ) {
					$locale = 'fi-FI';
				} elseif ( 'sv_SE' === $locale ) {
					$locale = 'sv-FI';
				} else {
					$locale = 'en-FI';
				}
				break;
			case 'FR': // France.
				$locale = ( 'fr_FR' === $locale ) ? 'fr-FR' : 'en-FR';
				break;
			case 'GR': // Greece.
				$locale = 'el-GR';
				break;
			case 'IE': // Ireland.
				$locale = 'en-IE';
				break;
			case 'IT': // Italy.
				$locale = ( 'it_IT' === $locale ) ? 'it-IT' : 'en-IT';
				break;
			case 'NL': // Netherlands.
				$locale = ( 'nl_NL' === $locale ) ? 'nl-NL' : 'en-NL';
				break;
			case 'PT': // Portugal.
				$locale = ( 'pt_PT' === $locale ) ? 'pt-PT' : 'en-PT';
				break;
			case 'SK': // Slovakia.
				$locale = ( 'sk_SK' === $locale ) ? 'sk-SK' : 'en-SK';
				break;
			default:
				$locale = $default_locale;
				break;
		}

		return $locale;
	}
}

