<?php

namespace Krokedil\Klarna;

use Krokedil\Klarna\SignInWithKlarna\Settings;
use Krokedil\Klarna\SignInWithKlarna\JWT;
use Krokedil\Klarna\SignInWithKlarna\Ajax;
use Krokedil\Klarna\SignInWithKlarna\User;
use Krokedil\Klarna\SignInWithKlarna\Redirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sign In With Klarna class.
 */
class SignInWithKlarna {

	/**
	 * The handle name for the JavaScript library.
	 *
	 * @var string
	 */
	public static $library_handle = 'siwk_library';

	/**
	 * The action hook name for outputting the placement HTML.
	 *
	 * @var string
	 */
	public static $placement_hook = 'siwk_output_button';

	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * The interface used for reading from a JWT token.
	 *
	 * @var JWT
	 */
	public $jwt;

	/**
	 * Handles AJAX requests.
	 *
	 * @var Ajax
	 */
	public $ajax;

	/**
	 * Handles metadata associated with a WordPress user.
	 *
	 * @var User
	 */
	public $user;

	/**
	 * Class constructor.
	 *
	 * @param array $settings The plugin settings to extract from.
	 */
	public function __construct( $settings ) {
		$this->settings = new Settings( $settings );

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the SignInWithKlarna class.
	 *
	 * @return void
	 */
	public function init() {
		// If the feature for SIWK is not available, do not proceed.
		if ( ! PluginFeatures::is_available( Features::SIWK ) ) {
			return;
		}
		$this->settings->init_settings();
		$this->jwt  = new JWT( wc_string_to_bool( $this->settings->get( 'test_mode' ) ), $this->settings );
		$this->user = new User( $this->jwt );
		$this->ajax = new Ajax( $this->jwt, $this->user );
		// Initialize the callback endpoint for handling the redirect flow.
		new Redirect( $this->settings );

		if ( $this->should_display() ) {
			// Frontend hooks.
			add_action( 'woocommerce_proceed_to_checkout', array( $this, self::$placement_hook ), \intval( $this->settings->get( 'cart_placement' ) ) );
			add_action( 'woocommerce_login_form_start', array( $this, self::$placement_hook ) );
			add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'siwk_output_button' ), 5 );
			// Enqueue library and SDK.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
		// Enqueue settings page styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$script_path = plugin_dir_url( __FILE__ ) . 'SignInWithKlarna/assets/js/siwk.js';
		$siwk_params = array(
			'locale'                   => $this->settings->get( 'locale' ),
			'scope'                    => $this->settings->get( 'scope' ),
			'theme'                    => $this->settings->get( 'button_theme' ),
			'shape'                    => $this->settings->get( 'button_shape' ),
			'alignment'                => $this->settings->get( 'logo_alignment' ),
			'redirect_uri'             => Redirect::get_callback_url(),
			'sign_in_from_popup_url'   => \WC_AJAX::get_endpoint( 'siwk_sign_in_from_popup' ),
			'sign_in_from_popup_nonce' => wp_create_nonce( 'siwk_sign_in_from_popup' ),
		);
		wp_register_script_module( '@klarna/siwk', $script_path, array( '@klarna/network_session_token' ), WC_KLARNA_PAYMENTS_VERSION );
		\KP_Assets::register_module_data( $siwk_params, '@klarna/siwk' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( empty( $section ) || 'klarna_payments' !== $section ) {
			return;
		}

		$path = plugin_dir_url( __FILE__ ) . 'SignInWithKlarna/assets/css/admin-siwk.css';
		wp_enqueue_style( 'siwk_admin_css', $path, array(), WC_KLARNA_PAYMENTS_VERSION );

		$script_path = plugin_dir_url( __FILE__ ) . 'SignInWithKlarna/assets/js/admin-siwk.js';
		wp_enqueue_script( 'siwk_admin_script', $script_path, array( 'jquery' ), WC_KLARNA_PAYMENTS_VERSION, false );
	}

	/**
	 * Output the "Sign in with Klarna" button HTML.
	 *
	 * @param string $style The CSS style to apply to the button.
	 * @return void
	 */
	public function siwk_output_button( $style = '' ) {
		$style = 'width: 100%; max-width: 100%;' . ( ! empty( $style ) ? " {$style}" : '' );
		wp_enqueue_script_module( '@klarna/siwk' );
		echo "<div id='klarna-identity-button' class='siwk-button' style='" . esc_attr( $style ) . "'></div>";
	}

	/**
	 * Get the attributes for the Sign in with Klarna button.
	 *
	 * @return array
	 */
	public function add_websdk_attributes() {
		$locale      = esc_attr( $this->settings->get( 'locale' ) );
		$scope       = esc_attr( $this->settings->get( 'scope' ) );
		$market      = esc_attr( apply_filters( 'siwk_market', $this->settings->get( 'market' ) ) );
		$environment = esc_attr( apply_filters( 'siwk_environment', wc_string_to_bool( $this->settings->get( 'test_mode' ) ) ? 'playground' : 'production' ) );
		$client_id   = esc_attr( apply_filters( 'siwk_client_id', $this->settings->get( 'client_id' ) ) );
		$redirect_to = esc_attr( Redirect::get_callback_url() );

		$theme     = esc_attr( $this->settings->get( 'button_theme' ) );
		$shape     = esc_attr( $this->settings->get( 'button_shape' ) );
		$alignment = esc_attr( $this->settings->get( 'logo_alignment' ) );

		return array(
			'data-locale'         => $locale,
			'data-scope'          => $scope,
			'data-market'         => $market,
			'data-environment'    => $environment,
			'data-client-id'      => $client_id,
			'data-redirect-uri'   => $redirect_to,
			'data-logo-alignment' => $alignment,
			'data-theme'          => $theme,
			'data-shape'          => $shape,
		);
	}

	/**
	 * Determine whether the button should be displayed.
	 *
	 * @return bool
	 */
	private function should_display() {
		$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
		if ( in_array( 'siwk', $kp_unavailable_feature_ids ) ) {
			return false;
		}

		$enabled = $this->settings->get( 'enabled' );
		if ( ! wc_string_to_bool( $enabled ) ) {
			return false;
		}

		$tokens = get_user_meta( get_current_user_id(), User::TOKENS_KEY, true );
		return ! isset( $tokens['id_token'] );
	}
}
