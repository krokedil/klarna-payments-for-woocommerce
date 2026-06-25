<?php
namespace Krokedil\Klarna\OnsiteMessaging;

use KP_Assets;
use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;
use Krokedil\Klarna\OnsiteMessaging\Pages\Product;
use Krokedil\Klarna\OnsiteMessaging\Pages\Cart;
use Krokedil\Klarna\OnsiteMessaging\Blocks\CartBlockIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOSM_VERSION', '2.1.3' );

/**
 * The orchestrator class.
 */
class KlarnaOnsiteMessaging {
	/**
	 * The internal settings state.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Display placement on product page.
	 *
	 * @var Product
	 */
	private $product;

	/**
	 * Display placement on cart page.
	 *
	 * @var Cart
	 */
	private $cart;

	/**
	 * Display placement with shortcode.
	 *
	 * @var Shortcode
	 */
	private $shortcode;


	/**
	 * Class constructor.
	 *
	 * @param array $settings Any existing KOSM settings.
	 */
	public function __construct( $settings ) {
		$this->settings = new Settings( $settings );

		// Skip if On-Site Messaging is not enabled.
		if ( ! $this->settings()->is_enabled() ) {
			return;
		}

		add_action( 'kp_plugin_features_initialized', array( $this, 'init' ) );
	}

	/**
	 * Initialize Onsite Messaging.
	 *
	 * @return void
	 */
	public function init() {
		// If the feature for KOSM is not available, do not proceed.
		if ( ! PluginFeatures::is_available( Features::OSM ) ) {
			return;
		}

		$this->product   = new Product( $this->settings );
		$this->cart      = new Cart( $this->settings );
		$this->shortcode = new Shortcode();

		add_action( 'widgets_init', array( $this, 'init_widget' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			// Lower hook priority to ensure the dequeue of the KOSM plugin scripts happens AFTER they have been enqueued.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
		}

		add_action( 'admin_notices', array( $this, 'kosm_installed_admin_notice' ) );

		// Unhook the KOSM plugin's action hooks.
		if ( class_exists( 'Klarna_OnSite_Messaging_For_WooCommerce' ) ) {
			$hooks    = wc_get_var( $GLOBALS['wp_filter']['wp_head'] );
			$priority = 10;
			foreach ( $hooks->callbacks[ $priority ] as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) ) {
					$class  = reset( $function );
					$method = end( $function );
					if ( is_object( $class ) && strpos( get_class( $class ), 'Klarna_OnSite_Messaging' ) !== false ) {
						remove_action( 'wp_head', array( $class, $method ), $priority );
					}
				}
			}
		}

		// Register WooCommerce Blocks Cart Block integration.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_wc_cart_block_integration' ) );
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	public function init_widget() {
		register_widget( new Widget( $this ) );
	}

	/**
	 * Check if the Klarna On-Site Messaging plugin is active, and notify the admin about the new changes.
	 *
	 * @return void
	 */
	public function kosm_installed_admin_notice() {
		$plugin = 'klarna-onsite-messaging-for-woocommerce/klarna-onsite-messaging-for-woocommerce.php';
		if ( is_plugin_active( $plugin ) ) {
			$message = __( 'The "Klarna On-Site Messaging for WooCommerce" plugin is now integrated into Klarna Payments. Please disable the plugin.', 'klarna-payments-for-woocommerce' );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );

		}
	}

	/**
	 * Add data- attributes to <script> tag.
	 *
	 * @param array $attributes Existing attributes.
	 * @return array
	 */
	public function add_data_attributes( $attributes ) {
		$settings       = get_option( 'woocommerce_klarna_payments_settings', array() );
		$environment    = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'] ? 'playground' : 'production';
		$data_client_id = apply_filters( 'kosm_data_client_id', $this->settings->get( 'data_client_id' ) );

		$attributes['data-environment'] = $environment;
		$attributes['data-client-id']   = $data_client_id;

		return $attributes;
	}

	/**
	 * Enqueue KOSM and library scripts.
	 *
	 * @param bool $show_everywhere Whether to enqueue scripts on all pages.
	 * @return void
	 */
	public function enqueue_scripts( $show_everywhere = false ) {
		global $post;

		if ( ! apply_filters( 'kosm_show_everywhere', $show_everywhere ) ) {
			$has_shortcode = ( ! empty( $post ) && has_shortcode( $post->post_content, 'onsite_messaging' ) );
			if ( ! ( $has_shortcode || is_product() || is_cart() ) ) {
				return;
			}
		}

		$region        = 'eu-library';
		$base_location = wc_get_base_location();
		if ( is_array( $base_location ) && isset( $base_location['country'] ) ) {
			if ( in_array( $base_location['country'], array( 'US', 'CA' ), true ) ) {
				$region = 'na-library';
			} elseif ( in_array( $base_location['country'], array( 'AU', 'NZ' ), true ) ) {
				$region = 'oc-library';
			}
		}
		$region    = apply_filters( 'kosm_region_library', $region );
		$client_id = apply_filters( 'kosm_data_client_id', $this->settings->get( 'data_client_id' ) );

		if ( empty( $client_id ) ) {
			return;
		}

		// Deregister the script that is registered by the KOSM plugin.
		wp_deregister_script( 'klarna_onsite_messaging' );
		wp_deregister_script( 'klarna-onsite-messaging' );
		wp_deregister_script( 'onsite_messaging_script' );

		$script_path = plugin_dir_url( __FILE__ ) . 'assets/js/klarna-onsite-messaging.js';
		wp_register_script_module( '@klarna/onsite_messaging', $script_path, array( '@klarna/network_session_token' ), KOSM_VERSION );

		$localize = array(
			'client_id'          => $client_id,
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
			'get_cart_total_url' => \WC_AJAX::get_endpoint( 'kosm_get_cart_total' ),
		);

		if ( isset( $_GET['osmDebug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$localize['debug_info'] = array(
				'product'        => is_product(),
				'cart'           => is_cart(),
				'shortcode'      => $has_shortcode,
				'data_client'    => ! ( empty( $client_id ) ),
				'locale'         => Utility::get_locale_from_currency(),
				'currency'       => get_woocommerce_currency(),
				'library'        => ( wp_scripts() )->registered[ KP_Assets::KP_WEBSDK_HANDLE_V2 ]->src ?? $region,
				'base_location'  => $base_location['country'],
				'hide_placement' => has_filter( 'kosm_hide_placement' ),
			);

			$product = Utility::get_product();
			if ( ! empty( $product ) ) {
				$type                                   = $product->get_type();
				$localize['debug_info']['product_type'] = $type;
				if ( method_exists( $product, 'get_available_variations' ) ) {
					foreach ( $product->get_available_variations() as $variation ) {
						$attribute                                   = wc_get_var( $variation['attributes'] );
						$localize['debug_info']['default_variation'] = reset( $attribute );
						break;
					}
				}
			}
		}
		KP_Assets::register_module_data( $localize, '@klarna/onsite_messaging' );

		wp_enqueue_script_module( '@klarna/onsite_messaging' );
	}

	/**
	 * Get the settings object.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get the product object.
	 *
	 * @return Product
	 */
	public function product() {
		return $this->product;
	}

	/**
	 * Get the cart object.
	 *
	 * @return Cart
	 */
	public function cart() {
		return $this->cart;
	}

	/**
	 * Get the shortcode object.
	 *
	 * @return Shortcode
	 */
	public function shortcode() {
		return $this->shortcode;
	}

	/**
	 * Register WooCommerce Blocks Cart Block integration.
	 *
	 * @return void
	 */
	public function register_wc_cart_block_integration() {
		// Return if blocks does not exist for backwards compatibility.
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			return;
		}

		// Register the block integration for the cart block.
		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new CartBlockIntegration() );
			}
		);
	}
}
