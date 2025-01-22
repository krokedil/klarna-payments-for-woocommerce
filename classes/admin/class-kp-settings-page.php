<?php
/**
 * Settings page class.
 *
 * @package Klarna_Payments_For_WooCommerce/Classes/Admin
 */

defined( 'ABSPATH' ) || exit;

use KrokedilKlarnaPaymentsDeps\Krokedil\SettingsPage\SettingsPage;

/**
 * KP_Settings_Page.
 */
class KP_Settings_Page {
	/**
	 * Instance of the settings page class from the Krokedil/SettingsPage package.
	 *
	 * @var SettingsPage $settings_page
	 */
	protected $settings_page;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// WC_Settings.
		add_action( 'woocommerce_admin_field_kp_section_start', array( __CLASS__, 'section_start_html' ) );
		add_action( 'woocommerce_admin_field_kp_section_end', array( __CLASS__, 'section_end_html' ) );
		add_action( 'woocommerce_admin_field_kp_text_info', array( __CLASS__, 'text_info_html' ) );
		add_action( 'woocommerce_admin_field_kp_credentials_info', array( __CLASS__, 'credentials_html' ) );

		// WC_Settings_API.
		add_filter( 'woocommerce_generate_kp_section_start_html', array( __CLASS__, 'section_start' ), 10, 3 );
		add_filter( 'woocommerce_generate_kp_section_end_html', array( __CLASS__, 'section_end' ), 10, 3 );
		add_filter( 'woocommerce_generate_kp_text_info_html', array( __CLASS__, 'text_info' ), 10, 3 );
		add_filter( 'woocommerce_generate_kp_credentials_html', array( __CLASS__, 'credentials' ), 10, 3 );

		// Preload the fonts before the settings page is loaded.
		add_action( 'admin_head', array( $this, 'preload_fonts' ) );
	}

	/**
	 * Preload fonts by adding the fonts to the head of the admin page.
	 * This prevents the fonts from being loaded after the CSS file is loaded and the page is rendered.
	 *
	 * @return void
	 */
	public function preload_fonts() {
		// Only if we are on the settings page for Klarna.
		if ( ! isset( $_GET['page'] ) || ! isset( $_GET['section'] ) || 'wc-settings' !== $_GET['page'] || 'klarna_payments' !== $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		?>
		<link rel="preload" href="<?php echo esc_url( WC_KLARNA_PAYMENTS_PLUGIN_URL ); ?>/assets/fonts/KlarnaText-Regular.otf" as="font" type="font/otf" crossorigin>
		<link rel="preload" href="<?php echo esc_url( WC_KLARNA_PAYMENTS_PLUGIN_URL ); ?>/assets/fonts/KlarnaText-Bold.otf" as="font" type="font/otf" crossorigin>
		<?php
	}

	/**
	 * Outputs the HTML for the settings page header.
	 *
	 * @return void
	 */
	public static function header_html() {
		$navigation = SettingsPage::get_instance()->navigation( 'klarna_payments' );
		?>
		<div class="kp_settings__header">
			<img class="kp_settings__header_logo" src="<?php echo esc_url( WC_KLARNA_PAYMENTS_PLUGIN_URL ); ?>/assets/img/klarna-icon.svg" alt="Klarna Payments" />
			<div class="kp_settings__header_text">
				<h1 class="kp_settings__header_title"><?php esc_html_e( 'Klarna for WooCommerce', 'klarna-payments-for-woocommerce' ); ?></h1>
				<p class="kp_settings__header_description"><?php esc_html_e( 'Supercharge your business with one single plugin for increased sales and enhanced shopping experiences.', 'klarna-payments-for-woocommerce' ); ?></p>
				<p class="kp_settings__header_links">
					<a href="https://docs.klarna.com/platform-solutions/e-commerce-platforms/woocommerce/before-you-start" target="_blank" class="kp_settings__header_link"><?php esc_html_e( 'Set-up guidelines', 'klarna-payments-for-woocommerce' ); ?></a>
					<a href="https://docs.klarna.com" target="_blank" class="kp_settings__header_link"><?php esc_html_e( 'Learn more about Klarna', 'klarna-payments-for-woocommerce' ); ?></a>
				</p>
			</div>
		</div>
		<?php if ( $navigation ) : ?>
			<?php $navigation->output(); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Outputs the HTML for a Klarna Payments section start.
	 *
	 * @param array $section The arguments for the section.
	 *
	 * @return void
	 */
	public static function section_start_html( $section ) {
		$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
		$availability               = in_array( $section['id'], $kp_unavailable_feature_ids ) ? ' unavailable' : '';
		$link_count                 = count( $section['links'] ?? array() );
		$link_count        = count( $section['links'] ?? array() );
		$setting_is_active = self::get_setting_status( $section['id'] );
		$feature_status    = array(
			'class' => $setting_is_active ? ' active' : '',
			'title' => $setting_is_active ? __( 'Active', 'klarna-payments-for-woocommerce' ) : __( 'Not active', 'klarna-payments-for-woocommerce' ),
		);

		?>
		<div id="klarna-payments-settings-<?php echo esc_attr( $section['id'] ); ?>" class="kp_settings__section<?php echo esc_attr( $availability ); ?>">
			<div class="kp_settings__section_info">
				<h3 class="kp_settings__section_title">
					<?php echo esc_html( $section['title'] ); ?>
					<span class="kp_settings__section_toggle dashicons dashicons-arrow-up-alt2"></span>
					<?php
					if ( $feature_status ) {
						?>
						<span class="kp_settings__mode_badge<?php echo esc_attr( $feature_status['class'] ); ?>"><?php echo esc_html( $feature_status['title'] ); ?> </span>
						<?php
					}
					?>
				</h3>
				<div class="kp_settings__section_info_text">
					<p class="kp_settings__section_description"><?php echo esc_html( $section['description'] ?? '' ); ?></p>
					<?php for ( $i = 0; $i < $link_count; $i++ ) : ?>
						<a class="kp_settings__section_link" href="<?php echo esc_url( $section['links'][ $i ]['url'] ); ?>" target="_blank"><?php echo esc_html( $section['links'][ $i ]['title'] ); ?></a>
						<?php if ( $i < count( $section['links'] ) - 1 ) : ?>
							|
						<?php endif; ?>
					<?php endfor; ?>
				</div>
			</div>
			<div class="kp_settings__section_content">
				<span class="kp_settings__section_toggle dashicons dashicons-arrow-up-alt2"></span>
				<div class="kp_settings__content_gradient"></div>
				<table class="form-table">
		<?php
	}


	/**
	 * Get the HTML as a string for a Klarna Payments section start.
	 *
	 * @param string $html The HTML to append the section start to.
	 * @param string $key The key for the section.
	 * @param array  $section The arguments for the section.
	 *
	 * @return string
	 */
	public static function section_start( $html, $key, $section ) {
		ob_start();
		self::section_start_html( $section );
		return ob_get_clean();
	}

	/**
	 * Outputs the HTML for a Klarna Payments section end.
	 *
	 * @param array $section The arguments for the section.
	 *
	 * @return void
	 */
	public static function section_end_html( $section ) {
		$previews = $section['previews'] ?? array();
		?>
				</table>
			</div>
			<div class="kp_settings__section_previews">
				<?php foreach ( $previews as $preview ) : ?>
					<div class="kp_settings_section_preview">
						<?php if ( isset( $preview['title'] ) ) : ?>
							<h3 class="kp_settings__preview_title"><?php echo esc_html( $preview['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( isset( $preview['image'] ) ) : ?>
							<img width="100%" alt="test" src="<?php echo esc_url( $preview['image'] ); ?>" />
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the HTML as a string for a Klarna Payments section end.
	 *
	 * @param string $html The HTML to append the section end to.
	 * @param string $key The key for the section end.
	 * @param array  $section The arguments for the section.
	 *
	 * @return string
	 */
	public static function section_end( $html, $key, $section ) {
		ob_start();
		self::section_end_html( $section );
		return ob_get_clean();
	}

	/**
	 * Outputs the HTML for the Klarna Payments text info.
	 *
	 * @param array $args The arguments for the text info.
	 *
	 * @return void
	 */
	public static function text_info_html( $args ) {
		?>
		<tr class="kp_settings__text_info" valign="top">
			<th scope="row" class="titledesc">
				<h4><?php echo wp_kses_post( $args['title'] ?? '' ); ?></h4>
			</th>
			<td class="forminp">
				<p><?php echo wp_kses_post( $args['description'] ?? '' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get the HTML as a string for the Klarna Payments text info.
	 *
	 * @param string $html The HTML to append the text info to.
	 * @param string $key The key for the text info.
	 * @param array  $args The arguments for the text info.
	 *
	 * @return string
	 */
	public static function text_info( $html, $key, $args ) {
		ob_start();
		self::text_info_html( $args );
		return ob_get_clean();
	}

	/**
	 * Outputs the HTML for the Klarna Payments credentials.
	 *
	 * @param array $args The arguments for the credentials.
	 *
	 * @return void
	 */
	public static function credentials_html( $args ) {
		$key           = $args['key'] ?? '';
		$settings      = get_option( 'woocommerce_klarna_payments_settings' );
		$eu_countries  = KP_Form_Fields::available_countries( 'eu' );
		$is_eu_country = key_exists( $key, $eu_countries );
		$is_eu_region  = 'eu' === $key;

		// If settings are empty and the combine eu credentials is not set, default to yes. Otherwise default to no.
		if ( empty( $settings ) ) {
			$combine_eu = true;
		} else {
			$combine_eu = 'yes' === ( isset( $settings['combine_eu_credentials'] ) ? $settings['combine_eu_credentials'] : 'no' );
		}

		$test_enabled = 'yes' === ( isset( $settings['testmode'] ) ? $settings['testmode'] : 'no' );
		$hide         = false;

		if ( $combine_eu && $is_eu_country ) {
			$hide = true;
		} elseif ( ! $combine_eu && $is_eu_region ) {
			$hide = true;
		}

		?>
		<tr class="kp_settings__credentials" style="<?php echo esc_attr( $hide ? 'display:none;' : '' ); ?>" valign="top" <?php echo esc_attr( $is_eu_region ? 'data-eu-region' : ( $is_eu_country ? 'data-eu-country' : '' ) ); ?>>
			<th scope="row" class="titledesc">
				<label
					data-field-key="<?php echo esc_attr( $args['key'] ?? '' ); ?>"
					class="kp_settings__fields_toggle <?php echo esc_attr( $args['class'] ?? '' ); ?>"
				>
					<?php echo wp_kses_post( $args['title'] ?? '' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</label>
			</th>
			<td class="forminp kp_settings__credentials_field_hidden">
				<?php self::credentials_fields_html( $key, false, ! $test_enabled ); ?>
				<?php self::credentials_fields_html( $key, true, $test_enabled ); ?>
			</td>
		<?php
	}

	/**
	 * Output the HTML of the Klarna Payments Credentials fields.
	 *
	 * @param string $key The key for the credentials.
	 * @param bool   $test_mode Whether the credentials are for test mode.
	 * @param bool   $hide Whether to hide the fields.
	 *
	 * @return void
	 */
	public static function credentials_fields_html( $key, $test_mode, $hide ) {
		$settings = get_option( 'woocommerce_klarna_payments_settings' );
		$prefix   = $test_mode ? 'test_' : '';

		$mid_key           = "{$prefix}merchant_id_{$key}";
		$shared_secret_key = "{$prefix}shared_secret_{$key}";
		$client_id_key     = "{$prefix}client_id_{$key}";

		$mid_name           = 'woocommerce_klarna_payments_' . $mid_key;
		$shared_secret_name = 'woocommerce_klarna_payments_' . $shared_secret_key;
		$client_id_name     = 'woocommerce_klarna_payments_' . $client_id_key;

		$wrapper_classes = $test_mode ? 'kp_settings__test_credentials' : 'kp_settings__production_credentials';

		$label_suffix = $test_mode ? __( '(Test)', 'klarna-payments-for-woocommerce' ) : __( '(Production)', 'klarna-payments-for-woocommerce' );

		?>
		<div class="kp_settings__credentials <?php echo esc_attr( $wrapper_classes ); ?>" style="<?php echo esc_attr( $hide ? 'display:none;' : '' ); ?>">
			<div class="kp_settings__fields_credentials" data-field-key="<?php echo esc_attr( $key ); ?>">
				<div class="kp_settings__field">
					<label for="<?php echo esc_attr( $mid_key ); ?>"><?php echo esc_html( __( 'Username', 'klarna-payments-for-woocommerce' ) . ' ' . $label_suffix ); ?></label>
					<input autocomplete="off new-password" type="text" class="kp_settings__fields_mid" id="<?php echo esc_attr( $mid_key ); ?>" name="<?php echo esc_attr( $mid_name ); ?>" value="<?php echo esc_attr( $settings[ $mid_key ] ?? '' ); ?>" placeholder=" " />
				</div>
				<div class="kp_settings__field">
					<label for="<?php echo esc_attr( $shared_secret_key ); ?>"><?php echo esc_html( __( 'Password', 'klarna-payments-for-woocommerce' ) . ' ' . $label_suffix ); ?></label>
					<input autocomplete="off new-password" type="password" class="kp_settings__fields_secret" id="<?php echo esc_attr( $shared_secret_key ); ?>" name="<?php echo esc_attr( $shared_secret_name ); ?>" value="<?php echo esc_attr( $settings[ $shared_secret_key ] ?? '' ); ?>" placeholder=" " />
				</div>
			</div>
			<div class="kp_settings__field">
				<label for="<?php echo esc_attr( $client_id_key ); ?>"><?php echo esc_html( __( 'Client ID', 'klarna-payments-for-woocommerce' ) . ' ' . $label_suffix ); ?></label>
				<input autocomplete="off new-password" type="text" class="kp_settings__fields_mid" id="<?php echo esc_attr( $client_id_key ); ?>" name="<?php echo esc_attr( $client_id_name ); ?>" value="<?php echo esc_attr( $settings[ $client_id_key ] ?? '' ); ?>" placeholder=" " />
			</div>
		</div>
		<?php
	}

	/**
	 * Get the HTML as a string for the Klarna Payments credentials.
	 *
	 * @param string $html The HTML to append the credentials to.
	 * @param string $key The key for the credentials.
	 * @param array  $args The arguments for the credentials.
	 *
	 * @return string
	 */
	public static function credentials( $html, $key, $args ) {
		ob_start();
		self::credentials_html( $args );
		return ob_get_clean();
	}

	/**
	 * Get the status of a setting.
	 *
	 * @param string $section_id The ID of the section.
	 *
	 * @return bool
	 */
	public static function get_setting_status( $section_id ) {
		$setting_key = self::get_setting_by_section_id( $section_id );
		$settings    = get_option( 'woocommerce_klarna_payments_settings', array() );

		// If kp_has_valid_credentials is not set, check credentials once & set option accordingly.
		if ( ! get_option( 'kp_has_valid_credentials' ) && ! empty( $settings ) ) {
			$kp_settings = new KP_Settings_Saved();
			$kp_settings->check_api_credentials();
		}

		// If the KOM plugin is active and therefore has a settings section, it is always active.
		if ( 'kom_enabled' === $setting_key ) {
			return true;
		}

		// Credentials section is active if any valid credentials are set.
		if ( 'credentials' === $setting_key && 'yes' === get_option( 'kp_has_valid_credentials' ) ) {
			return true;
		}

		// If no setting is yet set for KOSM, always default to enabled.
		if ( 'onsite_messaging_enabled' === $setting_key && ! isset( $settings[ $setting_key ] ) ) {
			return true;
		}

		if ( isset( $settings[ $setting_key ] ) && 'yes' === $settings[ $setting_key ] ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the setting key by the section ID.
	 *
	 * @param string $section_id The ID of the section.
	 *
	 * @return string
	 */
	public static function get_setting_by_section_id( $section_id ) {

		switch ( $section_id ) {
			// Credentials.
			case 'credentials':
				return 'credentials';
			// Klarna Payments.
			case 'general':
				return 'enabled';
			// Onsite Messaging.
			case 'onsite_messaging':
				return 'onsite_messaging_enabled';
			// Express Checkout.
			case 'kec_settings':
				return 'kec_enabled';
			// Sign in with Klarna.
			case 'siwk':
				return 'siwk_enabled';
			// Order Management.
			case 'kom':
				return 'kom_enabled';
			default:
				'';
		}
	}
}
