<?php

namespace Krokedil\Klarna\ExpressCheckout;

use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class for Klarna Express Checkout.
 */
class Settings {
	/**
	 * If KEC is enabled or not.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * The options key to get the KEC settings from.
	 *
	 * @var string
	 */
	private $options_key;

	/**
	 * The options array from the options key.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Class constructor.
	 *
	 * @param string $options_key The options key to get the KEC settings from.
	 *
	 * @return void
	 */
	public function __construct( $options_key ) {
		add_filter( 'wc_gateway_klarna_payments_settings', array( $this, 'add_settings' ), 10 );

		add_action( 'woocommerce_admin_field_kec_webhook_button', array( __CLASS__, 'webhook_button' ) );
		add_filter( 'woocommerce_generate_kec_webhook_button_html', array( __CLASS__, 'webhook_button_html' ), 10, 3 );

		$this->options_key = $options_key;
		$this->options     = $this->get_settings();
	}

	/**
	 * Add the KEC settings to the Klarna Payments settings page.
	 *
	 * @param array $settings The existing settings array.
	 *
	 * @return array
	 */
	public function add_settings( $settings ) {
		return array_merge( $settings, $this->get_setting_fields() );
	}

	/**
	 * Update a stored setting with a new value.
	 *
	 * @param string $key   The key to update.
	 * @param mixed  $value The value to set.
	 *
	 * @return void
	 */
	public function update_setting( $key, $value ) {
		$this->options[ $key ] = $value;

		if ( 'kec_webhook' === $key || 'kec_signing_key' === $key ) {
			update_option( $key, $value );
		}
	}

	/**
	 * Get the KEC settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( $this->options_key, array() );

		return array(
			'kec_enabled'            => $settings['kec_enabled'] ?? 'no',
			'kec_credentials_secret' => $settings['kec_credentials_secret'] ?? '',
			'kec_theme'              => $settings['kec_theme'] ?? 'dark',
			'kec_shape'              => $settings['kec_shape'] ?? 'default',
			'kec_placement'          => $settings['kec_placement'] ?? 'both',
			'kec_flow'               => $settings['kec_flow'] ?? 'two_step',
			'kec_webhook'            => get_option( 'kec_webhook', array() ),
			'kec_signing_key'        => get_option( 'kec_signing_key', array() ),
		);
	}

	/**
	 * Get whether KEC is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
		if ( in_array( 'kec_settings', $kp_unavailable_feature_ids ) ) {
			return false;
		}

		return 'yes' === ( $this->options['kec_enabled'] ?? 'no' );
	}

	/**
	 * Get the credentials secret from Klarna for KEC.
	 *
	 * @return string
	 */
	public function get_credentials_secret() {
		if ( function_exists( 'kp_get_client_id_by_currency' ) ) {
			return kp_get_client_id_by_currency();
		}

		return $this->options['kec_credentials_secret'] ?? '';
	}

	/**
	 * Get the button theme for Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_theme() {
		return $this->options['kec_theme'] ?? 'dark';
	}

	/**
	 * Get the button shape for Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_shape() {
		return $this->options['kec_shape'] ?? 'default';
	}

	/**
	 * Get the placement setting for Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_placements() {
		return $this->options['kec_placement'] ?? 'both';
	}

	/**
	 * Get the webhook created in Klarna.
	 *
	 * @return array
	 */
	public function get_webhook() {
		return $this->options['kec_webhook'] ?? array();
	}

	/**
	 * Get the signing key for the webhook.
	 *
	 * @return array
	 */
	public function get_signing_key() {
		return $this->options['kec_signing_key'] ?? array();
	}

	/**
	 * Get the KEC checkout flow value.
	 *
	 * @return string
	 */
	public function get_kec_flow() {
		$one_step_available = PluginFeatures::is_available( Features::KEC_ONE_STEP );

		if ( ! $one_step_available ) {
			return 'two_step';
		}

		return $this->options['kec_flow'] ?? 'two_step';
	}

	/**
	 * Whether Klarna is in test mode.
	 *
	 * @return bool
	 */
	public function is_testmode() {
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings' );
		$testmode    = $kp_settings['testmode'] ?? 'no';

		return wc_string_to_bool( $testmode );
	}

	/**
	 * Get the KEC setting fields for the admin settings page.
	 *
	 * @return array
	 */
	public function get_setting_fields() {
		$created_webhook    = get_option( 'kec_webhook', array() );
		$webhook_created    = ! empty( $created_webhook );
		$one_step_available = PluginFeatures::is_available( Features::KEC_ONE_STEP );
		$two_step_available = PluginFeatures::is_available( Features::KEC_TWO_STEP );

		$flow_options = array();

		if ( $two_step_available ) {
			$flow_options['two_step'] = __( 'Two step', 'klarna-payments-for-woocommerce' );
		}

		if ( $one_step_available ) {
			$flow_options['one_step'] = __( 'One step', 'klarna-payments-for-woocommerce' );
		}

		return array(
			'kec_settings'       => array(
				'id'          => 'kec_settings',
				'title'       => 'Express Checkout',
				'description' => __( 'Offer a 5x faster check-out process that lowers the threshold for shoppers to complete a purchase.', 'klarna-payments-for-woocommerce' ),
				'links'       => array(
					array(
						'url'   => 'https://docs.klarna.com/express-checkout/',
						'title' => __( 'Documentation', 'klarna-payments-for-woocommerce' ),
					),
				),
				'type'        => 'kp_section_start',
			),
			'kec_enabled'        => array(
				'title'   => __( 'Enable/Disable', 'klarna-payments-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Klarna Express Checkout', 'klarna-payments-for-woocommerce' ),
				'default' => 'no',
			),
			'kec_info'           => array(
				'type'        => 'kp_text_info',
				'title'       => __( 'Placements & button style', 'klarna-payments-for-woocommerce' ),
				'description' => __( 'Tailor the express checkout button to fit your brand by adjusting the button theme, shape and selecting placements.', 'klarna-payments-for-woocommerce' ),
			),
			'kec_theme'          => array(
				'title'       => __( 'Theme', 'klarna-payments-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the theme for the Klarna Express Checkout.', 'klarna-payments-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'dark'     => __( 'Dark', 'klarna-payments-for-woocommerce' ),
					'light'    => __( 'Light', 'klarna-payments-for-woocommerce' ),
					'outlined' => __( 'Outlined', 'klarna-payments-for-woocommerce' ),
				),
				'default'     => 'dark',
			),
			'kec_shape'          => array(
				'title'       => __( 'Shape', 'klarna-payments-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the shape for the Klarna Express Checkout.', 'klarna-payments-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'default' => __( 'Rounded', 'klarna-payments-for-woocommerce' ),
					'rect'    => __( 'Rectangular', 'klarna-payments-for-woocommerce' ),
					'pill'    => __( 'Pill', 'klarna-payments-for-woocommerce' ),
				),
				'default'     => 'default',
			),
			'kec_placement'      => array(
				'title'   => __( 'Placements', 'klarna-payments-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'both',
				'options' => array(
					'both'    => __( 'All (recommended)', 'klarna-payments-for-woocommerce' ),
					'product' => __( 'Product pages', 'klarna-payments-for-woocommerce' ),
					'cart'    => __( 'Cart page', 'klarna-payments-for-woocommerce' ),
				),
			),
			'kec_flow'           => array(
				'title'       => __( 'Flow', 'klarna-payments-for-woocommerce' ),
				'description' => __( 'Select the checkout flow for Klarna Express Checkout.', 'klarna-payments-for-woocommerce' ),
				'type'        => ! empty( $flow_options ) ? 'select' : 'hidden',
				'default'     => 'two_step',
				'options'     => $flow_options,
			),
			'kec_webhook'        => array(
				'class'       => 'kec-webhook-section',
				'type'        => 'kp_text_info',
				'title'       => sprintf( __( 'Webhook %s', 'klarna-payments-for-woocommerce' ), self::webhook_status_badge( $webhook_created ) ),
				'description' => __( 'For one step Express Checkout to function, Klarna needs to be able to send callbacks to your store. Enable the callbacks and configure a signing key to use for the authentication.', 'klarna-payments-for-woocommerce' ),
			),
			'kec_webhook_button' => array(
				'class'           => 'kec-webhook-section',
				'type'            => 'kec_webhook_button',
				'webhook_created' => $webhook_created,
			),
			'kec_end'            => array(
				'type'     => 'kp_section_end',
				'previews' => array(
					array(
						'title' => __( 'Preview', 'klarna-payments-for-woocommerce' ),
						'image' => $this->get_preview_img_url(),
					),
				),
			),
		);
	}

	/**
	 * Get the preview image URL for the current button style.
	 *
	 * @return string
	 */
	public function get_preview_img_url() {
		$shape = $this->get_shape();
		$theme = $this->get_theme();

		if ( '' === $shape ) {
			$shape = 'default';
		}

		if ( '' === $theme || 'default' === $theme ) {
			$theme = 'dark';
		}

		return Assets::get_assets_path() . 'img/preview-' . $shape . '-' . $theme . '.png';
	}

	/**
	 * Get the HTML badge for the webhook status.
	 *
	 * @param bool $webhook_created Whether the webhook has been created.
	 *
	 * @return string
	 */
	public static function webhook_status_badge( $webhook_created ) {
		if ( $webhook_created ) {
			return '<span class="badge" style="background:#46b450; color:#fff; padding:1px 8px; border-radius:3px; font-size:0.9em; float:right;">' . esc_html__( 'Created', 'klarna-payments-for-woocommerce' ) . '</span>';
		} else {
			return '<span class="badge" style="background:#e74c3c; color:#fff; padding:1px 8px; border-radius:3px; font-size:0.9em; float:right;">' . esc_html__( 'Not Created', 'klarna-payments-for-woocommerce' ) . '</span>';
		}
	}

	/**
	 * Output the webhook button field HTML.
	 *
	 * @param array $section The field arguments.
	 *
	 * @return void
	 */
	public static function webhook_button( $section ) {
		$settings_link = KP_WC()->get_setting_link();

		self::maybe_show_messages();
		self::maybe_show_errors();

		if ( $section['webhook_created'] ) {
			$simulate_nonce = wp_create_nonce( 'kec_simulate_webhook' );
			$delete_nonce   = wp_create_nonce( 'kec_delete_webhook' );
			?>
			<tr class="kp_settings__text_info <?php echo esc_attr( $section['class'] ); ?>">
				<td colspan="2" class="forminp" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
					<a style="margin-top: 10px;" href="<?php echo esc_url( "{$settings_link}&kec_action=simulate_webhook&nonce={$simulate_nonce}#klarna-payments-settings-kec_settings" ); ?>" class="button-link">
						<?php esc_html_e( 'Test Webhook', 'klarna-payments-for-woocommerce' ); ?>
					</a>
					<a style="margin-top: 10px;" href="<?php echo esc_url( "{$settings_link}&kec_action=delete_webhook&nonce={$delete_nonce}#klarna-payments-settings-kec_settings" ); ?>" class="button-link button-link-delete">
						<?php esc_html_e( 'Remove Webhook', 'klarna-payments-for-woocommerce' ); ?>
					</a>
				</td>
			</tr>
			<?php
			return;
		}

		$nonce = wp_create_nonce( 'kec_create_webhook' );
		?>
		<tr class="kp_settings__text_info <?php echo esc_attr( $section['class'] ); ?>">
			<td colspan="2" class="forminp">
				<p><?php esc_html_e( 'Create the necessary webhook in Klarna for the Express Checkout.', 'klarna-payments-for-woocommerce' ); ?></p>
				<a style="margin-top: 10px;" href="<?php echo esc_url( "{$settings_link}&kec_action=create_webhook&nonce={$nonce}#klarna-payments-settings-kec_settings" ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Webhook', 'klarna-payments-for-woocommerce' ); ?>
				</a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Show any success messages from the URL parameter as inline notices.
	 *
	 * @return void
	 */
	public static function maybe_show_messages() {
		if ( ! isset( $_GET['kec_messages'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$messages = json_decode( base64_decode( wp_unslash( $_GET['kec_messages'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! is_array( $messages ) ) {
			return;
		}

		foreach ( $messages as $message ) {
			?>
			<tr class="kp_settings__text_info">
				<td class="forminp">
					<p class="notice notice-success" style="padding: 6px 12px;">
						<?php echo esc_html( $message ); ?>
					</p>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Show any error messages from the URL parameter as inline notices.
	 *
	 * @return void
	 */
	public static function maybe_show_errors() {
		if ( ! isset( $_GET['kec_errors'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$errors = json_decode( base64_decode( wp_unslash( $_GET['kec_errors'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! is_array( $errors ) ) {
			return;
		}

		foreach ( $errors as $message ) {
			?>
			<tr class="kp_settings__text_info">
				<td class="forminp">
					<p class="notice notice-error" style="padding: 6px 12px;">
						<?php echo esc_html( $message ); ?>
					</p>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Get the HTML for the webhook button field as a string.
	 *
	 * @param string $html    The existing HTML to return.
	 * @param string $key     The field key.
	 * @param array  $section The field arguments.
	 *
	 * @return string
	 */
	public static function webhook_button_html( $html, $key, $section ) {
		ob_start();
		self::webhook_button( $section );
		$html = ob_get_clean();

		return $html;
	}
}
