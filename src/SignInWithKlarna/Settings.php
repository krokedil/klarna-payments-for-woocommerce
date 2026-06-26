<?php //phpcs:ignore -- PCR-4 compliant.
namespace Krokedil\Klarna\SignInWithKlarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages settings for SIWK.
 */
class Settings {

	/**
	 * If the SIWK feature is enabled.
	 *
	 * @var string 'yes' or 'no'.
	 */
	private $enabled;

	/**
	 * The UUID you received after the Sign in with Klarna onboarding.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * The market or the country where this integration is available.
	 *
	 * @var string
	 */
	private $market;

	/**
	 * The environment to which the integration is pointing: playground or production.
	 *
	 * @var string 'yes' or 'no'.
	 */
	private $test_mode;

	/**
	 * The button's color theme.
	 *
	 * @var string
	 */
	private $button_theme;

	/**
	 * The button's shape.
	 *
	 * @var string
	 */
	private $button_shape;

	/**
	 * Change alignment of the Klarna badge on the call to action button based on the provided configuration.
	 *
	 * @var string
	 */
	private $logo_alignment;

	/**
	 * Change the position of the button on the cart page.
	 *
	 * @var int
	 */
	private $cart_placement;

	/**
	 * The locale.
	 *
	 * @var string
	 */
	private $locale;

	/**
	 * OAuth scopes.
	 *
	 * @var array
	 */
	private $scope;

	/**
	 * The SIWK settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Class constructor.
	 *
	 * @param array $settings The settings to extract from.
	 */
	public function __construct( $settings ) {
		$settings       = wp_parse_args(
			$settings,
			$this->default(),
		);
		$this->settings = $settings;
		add_action( 'init', array( $this, 'init_settings' ), 10, 0 );
	}

	/**
	 * Initialize the settings.
	 *
	 * @return void
	 */
	public function init_settings() {
		$this->update( $this->settings );

		if ( isset( $this->settings['siwk_callback_url'] ) && empty( $this->settings['siwk_callback_url'] ) ) {
			$this->settings['siwk_callback_url'] = Redirect::get_callback_url();
			update_option( 'woocommerce_klarna_payments_settings', $this->settings );
		}

		add_filter( 'wc_gateway_klarna_payments_settings', array( $this, 'extend_settings' ) );
	}

	/**
	 * Retrieve the value of a SIWK setting.
	 *
	 * @param string $setting The name of the setting.
	 * @return string|int
	 */
	public function get( $setting ) {
		$setting = str_replace( 'siwk_', '', $setting );
		if ( 'scope' === $setting ) {
			// These scopes are required for full functionality and shouldn't be modified by the merchant, and must be excluded from the filter.
			$required = 'openid offline_access customer:login profile:name profile:email profile:phone profile:billing_address ';
			return trim( $required . apply_filters( "siwk_{$setting}", implode( ' ', array_keys( array_filter( $this->scope ) ) ) ) );
		}

		return apply_filters( "siwk_{$setting}", $this->{$setting} );
	}

	/**
	 * Extend the plugin settings with the required SIWK settings.
	 *
	 * @param array $settings The plugin settings as an array.
	 * @return array
	 */
	public function extend_settings( $settings ) {
		return array_merge(
			$settings,
			array(
				'siwk'                                 => array(
					'title'       => __( 'Sign in with Klarna', 'klarna-payments-for-woocommerce' ),
					'description' => __( 'Let customers connect their accounts with pre-set payments and preferences to shop anywhere, improve personalized shopping experiences.', 'klarna-payments-for-woocommerce' ),
					'links'       => array(
						array(
							'url'   => 'https://docs.klarna.com/conversion-boosters/sign-in-with-klarna/before-you-start/',
							'title' => __( 'Documentation', 'klarna-payments-for-woocommerce' ),
						),
					),
					'type'        => 'kp_section_start',
				),
				'siwk_enabled'                         => array(
					'name'    => 'siwk_enabled',
					'title'   => __( 'Enable/Disable', 'klarna-payments-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Sign in with Klarna', 'klarna-payments-for-woocommerce' ),
					'default' => $this->default()['siwk_enabled'],
				),
				'siwk_callback_url'                    => array(
					'name'              => 'siwk_callback_url',
					'title'             => __( 'Redirect URL', 'klarna-payments-for-woocommerce' ),
					'type'              => 'text',
					'description'       => __( 'Add this URL to your list of allowed redirect URLs in the "Sign in with Klarna" settings on the <a href="https://portal.klarna.com/">Klarna merchant portal</a>.', 'klarna-payments-for-woocommerce' ),
					'default'           => Redirect::get_callback_url(),
					'css'               => 'min-width: 100%',
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
				),
				'siwk_button_theme'                    => array(
					'name'        => 'siwk_button_theme',
					'title'       => __( 'Button theme', 'klarna-payments-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'The button\'s color theme.', 'klarna-payments-for-woocommerce' ),
					'default'     => $this->default()['siwk_button_theme'],
					'options'     => array(
						'default'  => __( 'Dark', 'klarna-payments-for-woocommerce' ),
						'light'    => __( 'Light', 'klarna-payments-for-woocommerce' ),
						'outlined' => __( 'Outlined', 'klarna-payments-for-woocommerce' ),
					),
					'desc_tip'    => true,
				),
				'siwk_button_shape'                    => array(
					'name'        => 'siwk_button_shape',
					'title'       => __( 'Button shape', 'klarna-payments-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'The button\'s shape.', 'klarna-payments-for-woocommerce' ),
					'default'     => $this->default()['siwk_button_shape'],
					'options'     => array(
						'default' => __( 'Rounded', 'klarna-payments-for-woocommerce' ),
						'rect'    => __( 'Rectangular', 'klarna-payments-for-woocommerce' ),
						'pill'    => __( 'Pill', 'klarna-payments-for-woocommerce' ),
					),
					'desc_tip'    => true,
				),
				'siwk_logo_alignment'                  => array(
					'name'        => 'siwk_logo_alignment',
					'title'       => __( 'Badge alignment', 'klarna-payments-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Change alignment of the Klarna logo on the call to action button.', 'klarna-payments-for-woocommerce' ),
					'default'     => $this->default()['siwk_logo_alignment'],
					'options'     => array(
						'default' => __( 'Badge', 'klarna-payments-for-woocommerce' ),
						'left'    => __( 'Left', 'klarna-payments-for-woocommerce' ),
						'center'  => __( 'Centered', 'klarna-payments-for-woocommerce' ),
					),
					'desc_tip'    => true,
				),
				'siwk_cart_placement'                  => array(
					'name'        => 'siwk_cart_placement',
					'title'       => __( 'Cart page placement', 'klarna-payments-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Change the placement of the "Sign in with Klarna" button on the cart page.', 'klarna-payments-for-woocommerce' ),
					'default'     => $this->default()['siwk_cart_placement'],
					'options'     => array(
						'10'  => __( 'Before "Proceed to checkout" button', 'klarna-payments-for-woocommerce' ),
						'100' => __( 'After "Proceed to checkout" button', 'klarna-payments-for-woocommerce' ),
					),
					'desc_tip'    => true,
				),
				'siwk_required_scopes_email'           => array(
					'name'              => 'siwk_required_scopes',
					'title'             => __( 'Required Customer Data', 'klarna-payments-for-woocommerce' ),
					'type'              => 'checkbox',
					'label'             => __( 'Email Address', 'klarna-payments-for-woocommerce' ),
					'default'           => 'yes',
					'disabled'          => true,
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_required_scopes_name'            => array(
					'name'              => 'siwk_required_scopes_name',
					'type'              => 'checkbox',
					'label'             => __( 'Full name', 'klarna-payments-for-woocommerce' ),
					'default'           => 'yes',
					'disabled'          => true,
					'class'             => 'siwk',
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_required_scopes_phone'           => array(
					'name'              => 'siwk_required_scopes_phone',
					'type'              => 'checkbox',
					'label'             => __( 'Phone number', 'klarna-payments-for-woocommerce' ),
					'default'           => 'yes',
					'disabled'          => true,
					'class'             => 'siwk',
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_required_scopes_billing_address' => array(
					'name'              => 'siwk_required_scopes_billing_address',
					'type'              => 'checkbox',
					'label'             => __( 'Billing address', 'klarna-payments-for-woocommerce' ),
					'description'       => __( 'These scopes are included by default, as necessary for creating a WooCommerce customer account in your shop.  More about available scopes with Sign in with Klarna <a href="https://docs.klarna.com/conversion-boosters/sign-in-with-klarna/integrate-sign-in-with-klarna/web-sdk-integration/#scopes-and-claims">here</a>. Additional scopes can be customized if applicable, more info <a href="https://gist.github.com/mntzrr/4bf23ca394109d40575f2abc05811ddc">here</a>.', 'klarna-payments-for-woocommerce' ),
					'disabled'          => true,
					'class'             => 'siwk',
					'custom_attributes' => array(
						'checked' => 'true',
					),
				),
				'siwk_optional_scopes_date_of_birth'   => array(
					'name'    => 'siwk_optional_scopes_date_of_birth',
					'title'   => 'Request Additional Customer Data',
					'type'    => 'checkbox',
					'label'   => __( 'Date of birth', 'klarna-payments-for-woocommerce' ),
					'default' => 'no',
				),
				'siwk_optional_scopes_country'         => array(
					'name'    => 'siwk_optional_scopes_country',
					'type'    => 'checkbox',
					'label'   => __( 'Country', 'klarna-payments-for-woocommerce' ),
					'default' => 'no',
					'class'   => 'siwk',
				),
				'siwk_optional_scopes_language'        => array(
					'name'    => 'siwk_optional_scopes_language',
					'type'    => 'checkbox',
					'label'   => __( 'Language Preference', 'klarna-payments-for-woocommerce' ),
					'default' => 'no',
					'class'   => 'siwk',
				),
				'siwk_previews'                        => array(
					'type'     => 'kp_section_end',
					'previews' => array(
						array(
							'title' => __( 'Preview', 'klarna-payments-for-woocommerce' ),
							'image' => $this->get_preview_image(),
						),
					),
				),
			)
		);
	}

	/**
	 * Get the preview image URL for the "Sign in with Klarna" button.
	 *
	 * @return string The preview image url.
	 */
	private function get_preview_image() {
		$theme     = $this->button_theme;
		$shape     = $this->button_shape;
		$alignment = $this->logo_alignment;

		return plugin_dir_url( __FILE__ ) . "assets/img/preview-{$shape}_shape-{$theme}_theme-{$alignment}_alignment.png";
	}

	/**
	 * Update the internal settings state.
	 *
	 * @param array $settings The settings to extract from.
	 * @return void
	 */
	private function update( $settings ) {
		$default = $this->default();

		$this->enabled        = $settings['siwk_enabled'] ?? $default['siwk_enabled'];
		$this->test_mode      = $settings['testmode'] ?? $default['siwk_test_mode'];
		$this->button_theme   = $settings['siwk_button_theme'] ?? $default['siwk_button_theme'];
		$this->button_shape   = $settings['siwk_button_shape'] ?? $default['siwk_button_shape'];
		$this->logo_alignment = $settings['siwk_logo_alignment'] ?? $default['siwk_logo_alignment'];
		$this->cart_placement = $settings['siwk_cart_placement'] ?? $default['siwk_cart_placement'];
		$this->market         = kp_get_klarna_country();
		$this->client_id      = kp_get_client_id( $this->market );

		$this->locale = apply_filters( 'siwk_locale', str_replace( '_', '-', get_locale() ) );

		// The array keys match the name of the scopes they define.
		$this->scope = array(
			'profile:name'          => wc_string_to_bool( $settings['siwk_required_scopes_name'] ?? 'yes' ),
			'profile:email'         => wc_string_to_bool( $settings['siwk_required_scopes_email'] ?? 'yes' ),
			'profile:phone'         => wc_string_to_bool( $settings['siwk_required_scopes_phone'] ?? 'yes' ),
			'profile:locale'        => wc_string_to_bool( $settings['siwk_optional_scopes_language'] ?? 'no' ),
			'profile:country'       => wc_string_to_bool( $settings['siwk_optional_scopes_country'] ?? 'no' ),
			'profile:date_of_birth' => wc_string_to_bool( $settings['siwk_optional_scopes_date_of_birth'] ?? 'no' ),
		);
	}

	/**
	 * Retrieve the default settings values.
	 *
	 * @return array
	 */
	private function default() {
		return array(
			'siwk_client_id'      => '',
			'siwk_enabled'        => 'no',
			'siwk_test_mode'      => 'no',
			'siwk_title_theme'    => __( 'Theme, button shape & placements', 'klarna-payments-for-woocommerce' ),
			'siwk_button_theme'   => 'default',
			'siwk_button_shape'   => 'default',
			'siwk_logo_alignment' => 'default',
			'siwk_cart_placement' => 10,
			'siwk_callback_url'   => Redirect::get_callback_url(),
		);
	}
}
