<?php // phpcs:ignore
/**
 * Plugin Name: Klarna Payments for WooCommerce
 * Plugin URI: https://krokedil.com/klarna-payments/
 * Description: Provides Klarna Payments as payment method to WooCommerce.
 * Author: krokedil, klarna, automattic
 * Author URI: https://krokedil.com/
 * Version: 3.2.1
 * Text Domain: klarna-payments-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 5.6.0
 * WC tested up to: 8.0.0
 *
 * Copyright (c) 2017-2023 Krokedil
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package WC_Klarna_Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KlarnaPayments\Blocks\Payments\KlarnaPayments;

/**
 * Required minimums and constants
 */
define( 'WC_KLARNA_PAYMENTS_VERSION', '3.2.1' );
define( 'WC_KLARNA_PAYMENTS_MIN_PHP_VER', '7.4.0' );
define( 'WC_KLARNA_PAYMENTS_MIN_WC_VER', '5.6.0' );
define( 'WC_KLARNA_PAYMENTS_MAIN_FILE', __FILE__ );
define( 'WC_KLARNA_PAYMENTS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_KLARNA_PAYMENTS_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! class_exists( 'WC_Klarna_Payments' ) ) {

	/**
	 * Class WC_Klarna_Payments
	 */
	class WC_Klarna_Payments {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * *Singleton* clone.
		 *
		 * @return void
		 */
		public function __clone() {
		}

		/**
		 * *Singleton* wakeup.
		 *
		 * @return void
		 */
		public function __wakeup() {
		}

		/**
		 * Notices (array)
		 *
		 * @var array
		 */
		public $notices = array();

		/**
		 * KP Api class. Handles communication with Klarna.
		 *
		 * @var KP_Api|null
		 */
		public $api = null;

		/**
		 * KP Session class. Stores and handles the session data.
		 *
		 * @var KP_Session|null
		 */
		public $session = null;


		/**
		 * KP Subscription class. Handles the integration with WooCommerce Subscription
		 *
		 * @var KP_Subscription|null
		 */
		public $subscription = null;

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'admin_notices', array( $this, 'check_permalinks' ) );
			add_action( 'admin_notices', array( $this, 'order_management_check' ) );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_filter( 'woocommerce_checkout_posted_data', array( $this, 'filter_payment_method_id' ) );

			// Load text domain.
			load_plugin_textdomain( 'klarna-payments-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Include the autoloader from composer. If it fails, we'll just return and not load the plugin. But an admin notice will show to the merchant.
			if ( ! $this->init_composer() ) {
				return;
			}

			// Init the gateway itself.
			$this->include_files();

			$this->api     = new KP_Api();
			$this->session = new KP_Session();
			if ( class_exists( 'WC_Subscriptions' ) ) {
				$this->subscription = new KP_Subscription();
			}

			$this->register_payment_block();

			add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
		}

		/**
		 * Declare compatibility with WooCommerce features.
		 *
		 * @return void
		 */
		public function declare_wc_compatibility() {
			// Declare HPOS compatibility.
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Changes payment_method in posted data to klarna_payments when one of KP methods is selected.
		 *
		 * @param array $data Posted data.
		 *
		 * @return mixed
		 */
		public function filter_payment_method_id( $data ) {
			if ( strpos( $data['payment_method'], 'klarna_payments_' ) !== false ) {
				$data['payment_method'] = 'klarna_payments';
			}

			return $data;
		}

		/**
		 * Show admin notice if Order Management plugin is not active.
		 */
		public function order_management_check() {
			if ( ! class_exists( 'WC_Klarna_Order_Management' ) ) {
				$current_screen = get_current_screen();
				if ( 'shop_order' === $current_screen->id || 'plugins' === $current_screen->id || 'woocommerce_page_wc-settings' === $current_screen->id ) {
					?>
<div class="notice notice-warning">
	<p><?php esc_html_e( 'Klarna Order Management is not active. Please activate it so you can capture, cancel, update and refund Klarna orders.', 'woocommerce-klarna-payments' ); ?></p>
</div>
					<?php
				}
			}
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'klarna-payments-for-woocommerce' ) . '</a>',
				'<a href="https://docs.woocommerce.com/document/klarna-payments/">' . __( 'Docs', 'klarna-payments-for-woocommerce' ) . '</a>',
				'<a href="https://krokedil.com/support/">' . __( 'Support', 'klarna-payments-for-woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'klarna_payments' : strtolower( 'WC_Gateway_Klarna_Payments' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Check if pretty permalinks are used.
		 */
		public function check_permalinks() {

			if ( ! get_user_meta( get_current_user_id(), 'dismissed_kp_check_permalinks_notice', true ) ) {
				$permalinks = get_option( 'permalink_structure' );
				if ( empty( $permalinks ) ) {
					?>
					<div class="kp-message notice woocommerce-message notice-error">
						<a class="woocommerce-message-close notice-dismiss"
							href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'kp_check_permalinks' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></a>
					<?php
						echo wp_kses_post(
							wpautop(
								'<p>' . sprintf(
									// translators: URL to docs.
									__( 'It looks as if you don\'t have pretty permalinks enabled in WordPress. In order for Klarna Payments for Woocommerce to function properly, this setting needs to be enabled. <a href="%1$s">Learn more</a>', 'klarna-payments-for-woocommerce' ),
									esc_url( __( 'https://wordpress.org/support/article/using-permalinks/', 'klarna-payments-for-woocommerce' ) )
								)
							)
						);
					?>
					</div>
					<?php
				}
			}
		}

		/**
		 * Includes the files needed for the plugin
		 *
		 * @since 2.0.0
		 */
		public function include_files() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Classes.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/admin/class-kp-form-fields.php'; // This is loaded very early becasue we'll need these settings right away.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-wc-gateway-klarna-payments.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-gdpr.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-ajax.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-logger.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-email.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-settings-saved.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-callbacks.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-checkout.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-assets.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-api.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-session.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-subscriptions.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/admin/class-kp-status.php';

			// Requests.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/class-kp-requests.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/class-kp-requests-post.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/class-kp-requests-patch.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/patch/class-kp-cancel-recurring.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-create-session.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-update-session.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-place-order.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-test-credentials.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-create-hpp.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-create-customer-token.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/post/class-kp-create-recurring.php';

			// Request helpers.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/helpers/class-kp-iframe.php';
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/requests/helpers/class-kp-order-data.php';

			// Includes.
			include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/kp-functions.php';

			if ( is_admin() ) {
				include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/admin/class-klarna-for-woocommerce-addons.php';
				include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/classes/class-kp-banners.php';
			}
		}

		public function init_composer() {
			$autoloader = WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/vendor/autoload.php';

			if ( ! is_readable( $autoloader ) ) {
				self::missing_autoloader();
				return false;
			}

			$autoloader_result = require $autoloader;
			if ( ! $autoloader_result ) {
				return false;
			}

			return $autoloader_result;
		}

		protected static function missing_autoloader() {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore
					esc_html__( 'Your installation of Klarna Payments is not complete. If you installed this plugin directly from Github please refer to the README.DEV.md file in the plugin.', 'klarna-payments-for-woocommerce' )
				);
			}
			add_action(
				'admin_notices',
				function () {
					?>
						<div class="notice notice-error">
							<p>
								<?php echo esc_html__( 'Your installation of Klarna Payments is not complete. If you installed this plugin directly from Github please refer to the README.DEV.md file in the plugin.', 'klarna-payments-for-woocommerce' ); ?>
							</p>
						</div>
					<?php
				}
			);
		}

		/**
		 * Registers the Klarna payments block with WooCommerce.
		 *
		 * @return void
		 */
		public function register_payment_block() {
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				require_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/Blocks/Payment/KlarnaPayments.php';
				KlarnaPayments::register();
			}
		}
	}
	WC_Klarna_Payments::get_instance();

	/**
	 * Main instance WC_Klarna_Payments.
	 *
	 * Returns the main instance of WC_Klarna_Payments.
	 *
	 * @return WC_Klarna_Payments
	 */
	function KP_WC() { // phpcs:ignore
		return WC_Klarna_Payments::get_instance();
	}
}
