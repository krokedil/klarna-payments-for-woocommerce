<?php
/**
 * Settings page class.
 *
 * @package Klarna_Payments_For_WooCommerce/Classes/Admin
 */

defined( 'ABSPATH' ) || exit;

use Krokedil\SettingsPage\SettingsPage;

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
	}

	/**
	 * Outputs the HTML for the settings page header.
	 *
	 * @return void
	 */
	public static function header_html() {
		?>
		<div class="kp_settings__header">
			<img class="kp_settings__header_logo" src="<?php echo esc_url( WC_KLARNA_PAYMENTS_PLUGIN_URL ); ?>/assets/img/klarna-icon.svg" alt="Klarna Payments" />
			<div class="kp_settings__header_text">
				<h1 class="kp_settings__header_title"><?php esc_html_e( 'Klarna for WooCommerce', 'klarna-payments-for-woocommerce' ); ?></h1>
				<p class="kp_settings__header_description"><?php esc_html_e( 'Supercharge your business with one single plugin for increased sales and enhanced shopping experiences.', 'klarna-payments-for-woocommerce' ); ?></p>
				<p class="kp_settings__header_links">
					<a href="https://krokedil.se" target="_blank" class="kp_settings__header_link"><?php esc_html_e( 'Set-up guidelines', 'klarna-payments-for-woocommerce' ); ?></a>
					<a href="https://krokedil.se" target="_blank" class="kp_settings__header_link"><?php esc_html_e( 'Learn more about Klarna', 'klarna-payments-for-woocommerce' ); ?></a>
				</p>
			</div>
		</div>
		<?php SettingsPage::get_instance()->navigation( 'klarna_payments' )->output(); ?>
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
		$link_count = count( $section['links'] ?? array() );
		?>
		<div id="klarna-payments-settings-<?php echo esc_attr( $section['id'] ); ?>" class="kp_settings__section">
			<div class="kp_settings__section_info">
				<h3 class="kp_settings__section_title"><?php echo esc_html( $section['title'] ); ?></h3>
				<p class="kp_settings__section_description"><?php echo esc_html( $section['description'] ); ?></p>
				<?php for ( $i = 0; $i < $link_count; $i++ ) : ?>
					<a class="kp_settings__section_link" href="<?php echo esc_url( $section['links'][ $i ]['url'] ); ?>" target="_blank"><?php echo esc_html( $section['links'][ $i ]['title'] ); ?></a>
					<?php if ( $i < count( $section['links'] ) - 1 ) : ?>
						|
					<?php endif; ?>
				<?php endfor; ?>
			</div>
			<table class="form-table kp_settings__section_content">
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
		?>
			</table>
			<div class="kp_settings__section_preview">
			<?php if ( isset( $section['preview_img'] ) ) : ?>
				<img width="400" alt="test" src="<?php echo esc_url( $section['preview_img'] ); ?>" />
			<?php endif; ?>
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
				<h4><?php echo esc_html( $args['title'] ?? '' ); ?></h4>
			</th>
			<td class="forminp">
				<p><?php echo esc_html( $args['description'] ?? '' ); ?></p>
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
		?>
		<tr class="kp_settings__credentials" valign="top">
			<th scope="row" class="titledesc">
				<label
					data-field-key="<?php echo esc_attr( $args['key'] ?? '' ); ?>"
					class="kp_settings__fields_toggle"
				>
					<?php echo esc_html( $args['title'] ?? '' ); ?>
					<span class="dashicons dashicons-arrow-up-alt2"></span>
				</label>
			</th>
			<td class="forminp">
				<?php echo esc_html( $args['description'] ?? '' ); ?>
			</td>
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
}
