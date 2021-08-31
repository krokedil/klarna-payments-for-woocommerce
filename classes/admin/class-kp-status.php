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
 * Class for WooCommerce status page.
 */
class KP_Status {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_system_status_report', array( $this, 'add_status_page_box' ) );
	}

	/**
	 * Adds status page box for KP.
	 *
	 * @return void
	 */
	public function add_status_page_box() {
		include_once WC_KLARNA_PAYMENTS_PLUGIN_PATH . '/includes/admin/views/status-report.php';
	}
}
$kp_status = new KP_Status();
