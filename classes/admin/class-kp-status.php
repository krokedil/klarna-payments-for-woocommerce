<?php
/**
 * WooCommerce status page extension
 *
 * @class    KP_Status
 * @package  KP/Classes
 * @category Class
 * @author   Krokedil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Adds the configured Klarna markets to the WooCommerce system status report.
 *
 * The request log and the plugin settings are rendered by the support package's SystemReport.
 */
class KP_Status {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_system_status_report', array( $this, 'add_status_page_box' ) );
		add_action( 'woocommerce_cleanup_logs', array( $this, 'delete_legacy_request_log' ) );
	}

	/**
	 * Adds status page box for KP.
	 *
	 * @return void
	 */
	public function add_status_page_box() {
		include WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/admin/views/status-report.php';
	}

	/**
	 * Delete the request log that older versions stored in an option. Nothing writes to it anymore.
	 *
	 * @hook woocommerce_cleanup_logs
	 * @return void
	 */
	public function delete_legacy_request_log() {
		delete_option( 'krokedil_debuglog_kp' );
	}
}
$kp_status = new KP_Status();
