<?php

namespace Krokedil\Klarna;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'KLARNA_ORDER_MANAGEMENT_VERSION', '1.1.0' );

define( 'KLARNA_ORDER_MANAGEMENT_MIN_PHP_VER', '5.3.0' );
define( 'KLARNA_ORDER_MANAGEMENT_MIN_WC_VER', '3.3.0' );
define( 'KLARNA_ORDER_MANAGEMENT_CHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );


use Krokedil\Klarna\OrderManagement\Settings;
use Krokedil\Klarna\OrderManagement\Request\Get\RequestGetOrder;
use Krokedil\Klarna\OrderManagement\Request\Post\RequestPostRefund;
use Krokedil\Klarna\OrderManagement\Request\Post\RequestPostCapture;
use Krokedil\Klarna\OrderManagement\Request\Patch\RequestPatchUpdate;
use Krokedil\Klarna\OrderManagement\Request\Post\RequestPostCancel;
use Krokedil\Klarna\OrderManagement\MetaBox;
use Krokedil\Klarna\OrderManagement\Ajax;
use Krokedil\Klarna\OrderManagement\Utility;

/**
 * Klarna Order Management class.
 *
 * The main class responsible for initializing the plugin.
 */
class KlarnaOrderManagement {

	/**
	 * Klarna Order Management settings.
	 *
	 * @var Settings $settings
	 */
	public $settings;

	/**
	 * Klarna Order Management metabox.
	 *
	 * @var MetaBox $metabox
	 */
	public $metabox;

	/**
	 * Klarna Order Management AJAX handler.
	 *
	 * @var Ajax $ajax
	 */
	public $ajax;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init the plugin at plugins_loaded.
	 */
	public function init() {

		// If the Klarna Order Management plugin is active, do nothing.
		if ( class_exists( 'WC_Klarna_Order_Management' ) ) {

			// KCO does not have order management included yet, so we don't want to encourage the disabling of the KOM plugin if KCO is active.
			if ( ! class_exists( 'KCO' ) ) {
				add_action(
					'admin_notices',
					function () {
						?>
						<div class="notice notice-error">
				
								<p><strong><?php esc_html_e( 'Klarna Order Management is now included in Klarna for WooCommerce.', 'klarna-order-management' ); ?></strong></p>
								<p><?php esc_html_e( 'Starting with version 4.3.0, you no longer need the separate Klarna Order Management plugin – unless you are also using the Kustom Checkout plugin (formerly Klarna Checkout).', 'klarna-order-management' ); ?></p>

								<p>
									<a href="https://docs.krokedil.com/klarna-for-woocommerce/get-started/order-management/#important-please-read" target="_blank">
										<?php esc_html_e( 'Read more about this change here.', 'klarna-order-management' ); ?>
									</a>
								</p>

						</div>
						<?php
					}
				);
			}
			return;
		}

		// If Klarna Order Management is an unavailable feature, do not include the rest of the plugin.
		$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
		if ( in_array( 'kom', $kp_unavailable_feature_ids, true ) ) {
			return;
		}

		$this->settings = new Settings();
		$this->metabox  = new MetaBox( $this );
		$this->ajax     = new Ajax();

		// Add refunds support to Klarna for WooCommerce.
		add_action( 'wc_klarna_payments_supports', array( $this, 'add_gateway_support' ) );

		// Cancel order.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_klarna_order' ) );

		// Capture an order.
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_klarna_order' ) );

		// Update an order.
		add_action( 'woocommerce_saved_order_items', array( $this, 'update_klarna_order_items' ), 10, 2 );

		// Refund an order.
		add_filter( 'wc_klarna_payments_process_refund', array( $this, 'refund_klarna_order' ), 10, 4 );
		add_filter( 'wc_klarna_checkout_process_refund', array( $this, 'refund_klarna_order' ), 10, 4 );

		// Pending orders.
		add_action(
			'wc_klarna_notification_listener',
			array(
				'WC_Klarna_Pending_Orders',
				'notification_listener',
			),
			10,
			2
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Register and enqueue scripts for the admin.
	 *
	 * @return void
	 */
	public function enqueue_admin() {
		wp_enqueue_style( 'kom-admin-style', plugin_dir_url( __FILE__ ) . 'assets/css/klarna-order-management.css', array(), '1.0.0' );
		wp_enqueue_script( 'kom-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/klarna-order-management.js', array( 'jquery' ), '1.0.0', true );
		if ( isset( $this->metabox ) && method_exists( $this->metabox, 'maybe_localize_script' ) ) {
			$this->metabox->maybe_localize_script( 'kom-admin-js' );
		}
	}

	/**
	 * Add refunds support to Klarna Payments gateway.
	 *
	 * @param array $features Supported features.
	 *
	 * @return array $features Supported features.
	 */
	public function add_gateway_support( $features ) {
		$features[] = 'refunds';

		return $features;
	}

	/**
	 * Cancels a Klarna order.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $action If this was triggered through an action or not.
	 *
	 * @return bool|WP_Error Returns bool true if cancellation was successful or a WP_Error object if not.
	 */
	public function cancel_klarna_order( $order_id, $action = false ) {

		$options = $this->settings->get_settings( $order_id );
		if ( ! isset( $options['kom_auto_cancel'] ) || 'yes' === $options['kom_auto_cancel'] || $action ) {
			$order = wc_get_order( $order_id );

			// If the order was not paid using the plugin that instanced this class, bail.
			if ( ! Utility::check_plugin_instance( 'klarna_payments', $order->get_payment_method() ) ) {
				return;
			}

			// The merchant has disconnected the order from the order manager.
			if ( $order->get_meta( '_kom_disconnect' ) ) {
				return new \WP_Error( 'order_sync_off', 'Order management is disabled' );
			}

			// Check if the order has been paid.
			if ( empty( $order->get_date_paid() ) ) {
				return new \WP_Error( 'not_paid', 'Order has not been paid.' );
			}

			// Not going to do this for non-Klarna orders.
			if ( 'klarna_payments' !== $order->get_payment_method() ) {
				return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments payment method.' );
			}

			// Don't do this if the order is being rejected in pending flow.
			if ( $order->get_meta( '_wc_klarna_pending_to_cancelled', true ) ) {
				return new \WP_Error( 'rejected_in_pending_flow', 'Order is being rejected in pending flow.' );
			}

			// Retrieve Klarna order first.
			$klarna_order = $this->retrieve_klarna_order( $order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$order->add_order_note( 'Klarna order could not be cancelled due to an error.' );
				$order->save();

				return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
			}

			// Captured, part-captured and cancelled orders cannot be cancelled.
			if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
				$order->add_order_note( 'The Klarna order cannot be cancelled due to it already being captured.' );
				$order->save();
				return new \WP_Error( 'already_captured', 'Klarna order is captured and must be refunded.' );
			} elseif ( 'CANCELLED' === $klarna_order->status ) {
				$order->add_order_note( 'Klarna order has already been cancelled.' );
				$order->save();
				return new \WP_Error( 'already_cancelled', 'Klarna order is already cancelled.' );
			} else {
				$request  = new RequestPostCancel( $this, array( 'order_id' => $order_id ) );
				$response = $request->request();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order cancelled.' );
					$order->update_meta_data( '_wc_klarna_cancelled', 'yes' );
					if ( $order->save() ) {
						return true;
					} else {
						return new \WP_Error( 'save_error', 'Could not save WooCommerce order object.' );
					}
				} else {
					$order->add_order_note( 'Could not cancel Klarna order. ' . $response->get_error_message() . '.' );
					$order->save();
					return new \WP_Error( 'unknown_error', 'Response object is of type WP_Error.', $response );
				}
			}
		}
	}

	/**
	 * Updates Klarna order items.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $items Order items.
	 * @param bool  $action If this was triggered by an action.
	 *
	 * @return WP_Error|true Returns true if updating was successful or a WP_Error object if not.
	 */
	public function update_klarna_order_items( $order_id, $items, $action = false ) {
		$options = $this->settings->get_settings( $order_id );
		$order   = wc_get_order( $order_id );

		// If the order was not paid using the plugin that instanced this class, bail.
		if ( ! Utility::check_plugin_instance( 'klarna_payments', $order->get_payment_method() ) ) {
			return;
		}

		if ( 'klarna_payments' !== $order->get_payment_method() ) {
			return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments payment method.' );
		}

		// Are we on the subscription page?
		if ( 'shop_subscription' === $order->get_type() ) {
			$token_key = KP_Subscription::RECURRING_TOKEN;

			// Did the customer update the subscription's recurring token?
			$recurring_token = wc_get_var( $items[ $token_key ] );
			$existing_token  = $order->get_meta( $token_key );
			if ( ! empty( $recurring_token ) && $existing_token !== $recurring_token ) {
				$order->update_meta_data( $token_key, $recurring_token );
				$order->add_order_note(
					sprintf(
					// translators: 1: User name, 2: Existing token, 3: New token.
						__( '%1$s updated the subscription recurring token from "%2$s" to "%3$s".', 'klarna-order-management' ),
						ucfirst( wp_get_current_user()->display_name ),
						$existing_token,
						$recurring_token
					)
				);
				$order->save();

				// If the recurring token was changed, we can assume the merchant didn't update the subscription as that would require a recurring token which as has now been modified, but not yet saved.
				return true;
			}
		}

		if ( ! isset( $options['kom_auto_update'] ) || 'yes' === $options['kom_auto_update'] || $action ) {

			// The merchant has disconnected the order from the order manager.
			if ( $order->get_meta( '_kom_disconnect' ) ) {
				return new \WP_Error( 'order_sync_off', 'Order management is disabled' );
			}

			// Check if the order has been paid.
			if ( empty( $order->get_date_paid() ) ) {
				return new \WP_Error( 'not_paid', 'Order has not been paid.' );
			}

			// Changes are only possible if order is an allowed order status.
			if ( ! in_array( $order->get_status(), apply_filters( 'kom_allowed_update_statuses', array( 'on-hold' ) ), true ) ) {
				return new \WP_Error( 'not_allowed_status', 'Order is not in allowed status.' );
			}

			// Retrieve Klarna order first.
			$klarna_order = $this->retrieve_klarna_order( $order_id );
			if ( is_wp_error( $klarna_order ) ) {
				$order->add_order_note( 'Klarna order could not be updated due to an error.' );
				$order->save();

				return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
			}

			if ( ! in_array( $klarna_order->status, array( 'CANCELLED', 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
				$request  = new RequestPatchUpdate(
					$this,
					array(
						'request'      => 'update_order_lines',
						'order_id'     => $order_id,
						'klarna_order' => $klarna_order,
					)
				);
				$response = $request->request();
				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order updated.' );
					$order->save();
				} else {
					$reason = $response->get_error_message();
					if ( ! empty( $reason ) ) {
						// translators: %s: error message from Klarna.
						$order_note = sprintf( __( 'Could not update Klarna order lines: %s.', 'klarna-order-management' ), $reason );
					} else {
						$order_note = __( 'Could not update Klarna order lines. An unknown error occurred.', 'klarna-order-management' );
					}

					$order->add_order_note( $order_note );
					$order->save();
					return new \WP_Error( 'unknown_error', 'Response object is of type WP_Error.', $response );
				}
			}
		}

		return true;
	}

	/**
	 * Captures a Klarna order.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $action If this was triggered by an action.
	 *
	 * @return bool|WP_Error Returns bool true if capture was successful or a WP_Error object if not.
	 */
	public function capture_klarna_order( $order_id, $action = false ) {
		$options = $this->settings->get_settings( $order_id );
		$order   = wc_get_order( $order_id );

		// If the order was not paid using the plugin that instanced this class, bail.
		if ( ! Utility::check_plugin_instance( 'klarna_payments', $order->get_payment_method() ) ) {
			return;
		}

		if ( ! isset( $options['kom_auto_capture'] ) || 'yes' === $options['kom_auto_capture'] || $action ) {

			// The merchant has disconnected the order from the order manager.
			if ( $order->get_meta( '_kom_disconnect' ) ) {
				return new \WP_Error( 'order_sync_off', 'Order management is disabled' );
			}

				// Check if the order has been paid.
			if ( empty( $order->get_date_paid() ) ) {
				return new \WP_Error( 'not_paid', 'Order has not been paid.' );
			}

			// Not going to do this for non-Klarna orders.
			if ( 'klarna_payments' !== $order->get_payment_method() ) {
				return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments payment method.' );
			}
			// Do nothing if Klarna order was already captured.
			if ( $order->get_meta( '_wc_klarna_capture_id', true ) ) {
				$order->add_order_note( 'Klarna order has already been captured.' );
				$order->save();

				return new \WP_Error( 'already_captured', 'Order has already been captured.' );
			}
			// Do nothing if we don't have Klarna order ID.
			if ( ! $order->get_meta( '_wc_klarna_order_id', true ) && ! $order->get_transaction_id() ) {
				$order->update_status( 'on-hold', 'Klarna order ID is missing, Klarna order could not be captured at this time.' );
				return new \WP_Error( 'klarna_id_missing', 'Klarna order id is missing for order.' );
			}
			// Retrieve Klarna order.
			$klarna_order = $this->retrieve_klarna_order( $order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$order->update_status( 'on-hold', 'Klarna order could not be captured due to an error.' );
				return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
			}
			// Check if order is pending review.
			if ( 'PENDING' === $klarna_order->fraud_status ) {
				$order->update_status( 'on-hold', 'Klarna order is pending review and could not be captured at this time.' );
				return new \WP_Error( 'pending_fraud_review', 'Order is pending fraud review and cannot be captured.' );
			}
			// Check if Klarna order has already been captured.
			if ( in_array( $klarna_order->status, array( 'CAPTURED' ), true ) ) {
				$order->add_order_note( 'Klarna order has already been captured on ' . $klarna_order->captures[0]->captured_at );
				$order->update_meta_data( '_wc_klarna_capture_id', $klarna_order->captures[0]->capture_id );
				$order->save();
				return new \WP_Error( 'already_captured', 'Order has already been captured.' );
			}
			// Check if Klarna order has already been canceled.
			if ( 'CANCELLED' === $klarna_order->status ) {
				$order->add_order_note( 'Klarna order failed to capture, the order has already been canceled' );
				$order->save();

				return new \WP_Error( 'klarna_order_cancelled', 'Order is cancelled. Capture failed.' );
			}
			// Only send capture request if Klarna order fraud status is accepted.
			if ( 'ACCEPTED' !== $klarna_order->fraud_status ) {
				$order->add_order_note( 'Klarna order could not be captured at this time.' );
				$order->save();

				return new \WP_Error( 'pending_fraud_review', 'Order is pending fraud review and cannot be captured.' );
			} else {
				$request  = new RequestPostCapture(
					$this,
					array(
						'request'      => 'capture',
						'order_id'     => $order_id,
						'klarna_order' => $klarna_order,
					)
				);
				$response = $request->request();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $response );
					$order->update_meta_data( '_wc_klarna_capture_id', $response );
					$order->save();
					return true;
				} else {

					/* The suggested approach by Klarna is to try again after some time. If that still fails, the merchant should inform the customer, and ask them to either "create a new subscription or add funds to their payment method if they wish to continue." */
					if ( isset( $response->get_error_data()['code'] ) && 403 === $response->get_error_data()['code'] && 'PAYMENT_METHOD_FAILED' === $response->get_error_code() ) {
						$order->update_status( 'on-hold', __( 'Klarna could not charge the customer. Please try again later. If that still fails, the customer may have to create a new subscription or add funds to their payment method if they wish to continue.', 'klarna-order-management' ) );
						return new \WP_Error( 'capture_failed', 'Capture failed. Please try again later.' );
					} else {
						$error_message = $response->get_error_message();

						if ( ! is_array( $error_message ) && false !== strpos( $error_message, 'Captured amount is higher than the remaining authorized amount.' ) ) {
							$error_message = str_replace( '. Capture not possible.', sprintf( ': %s %s.', $klarna_order->remaining_authorized_amount / 100, $klarna_order->purchase_currency ), $error_message );
						}

						// translators: %s: Error message from Klarna.
						$order->update_status( 'on-hold', sprintf( __( 'Could not capture Klarna order. %s', 'klarna-order-management' ), $error_message ) );
						return new \WP_Error( 'capture_failed', 'Capture failed.', $error_message );
					}
				}
				if ( $order->save() ) {
					return true;
				} else {
					return new \WP_Error( 'save_error', 'Could not save WooCommerce order object.' );
				}
			}
		}
	}

	/**
	 * Refund a Klarna order.
	 *
	 * @param bool        $result Refund attempt result.
	 * @param int         $order_id WooCommerce order ID.
	 * @param null|string $amount Refund amount, full order amount if null.
	 * @param string      $reason Refund reason.
	 *
	 * @return bool|WP_Error Returns bool true if refund was successful or a WP_Error object if not.
	 */
	public function refund_klarna_order( $result, $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		// If the order was not paid using the plugin that instanced this class, bail.
		if ( ! Utility::check_plugin_instance( 'klarna_payments', $order->get_payment_method() ) ) {
			return;
		}

		// The merchant has disconnected the order from the order manager.
		if ( $order->get_meta( '_kom_disconnect' ) ) {
			return new \WP_Error( 'order_sync_off', 'Order management is disabled' );
		}

		// Not going to do this for non-Klarna orders.
		if ( 'klarna_payments' !== $order->get_payment_method() ) {
			return new \WP_Error( 'not_klarna_order', 'Order does not have klarna_payments payment method.' );
		}

		// Do nothing if Klarna order is not captured.
		if ( ! $order->get_meta( '_wc_klarna_capture_id', true ) ) {
			$order->add_order_note( 'Klarna order has not been captured and cannot be refunded.' );
			$order->save();

			return new \WP_Error( 'not_captured', 'Order has not been captured and cannot be refunded.' );
		}

		// Retrieve Klarna order first.
		$klarna_order = $this->retrieve_klarna_order( $order_id );

		if ( is_wp_error( $klarna_order ) ) {
			$order->add_order_note( 'Could not capture Klarna order. ' . $klarna_order->get_error_message() . '.' );
			$order->save();

			return new \WP_Error( 'object_error', 'Klarna order object is of type WP_Error.', $klarna_order );
		}

		if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
			$request  = new RequestPostRefund(
				$this,
				array(
					'order_id'      => $order_id,
					'refund_amount' => $amount,
					'refund_reason' => $reason,
				)
			);
			$response = $request->request();

			if ( ! is_wp_error( $response ) ) {
				$order->add_order_note( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) . ' refunded via Klarna.' );
				$order->save();
				return true;
			} else {
				$order->add_order_note( 'Could not refund Klarna order. ' . $response->get_error_message() . '.' );
				$order->save();
				return new \WP_Error( 'unknown_error', 'Response object is of type WP_Error.', $response );
			}
		}
	}

	/**
	 * Retrieve a Klarna order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return object $klarna_order Klarna Order.
	 */
	public function retrieve_klarna_order( $order_id ) {
		$request      = new RequestGetOrder(
			$this,
			array(
				'order_id' => $order_id,
			)
		);
		$klarna_order = $request->request();

		return $klarna_order;
	}
}
