<?php
abstract class KP_Payment_Processor {
	/**
	 * The WooCommerce order.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * If testmode was used to place the order.
	 *
	 * @var bool
	 */
	protected $testmode;

	/**
	 * The country code for the Klarna Payments session.
	 *
	 * @var string
	 */
	protected $klarna_country;

	/**
	 * The session id for the Klarna Payments session.
	 *
	 * @var string
	 */
	protected $session_id;

	/**
	 * The Klarna Payments settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Class constructor.
	 * @param WC_Order $order The WooCommerce order.
	 */
	public function __construct( $order ) {
		$this->settings       = get_option( 'woocommerce_klarna_payments_settings', array() );
		$this->order          = $order;
		$this->klarna_country = $this->get_klarna_country();
		$this->session_id     = $this->get_session_id();
		$this->testmode       = $this->get_testmode();
	}

	/**
	 * Process the payment for order
	 *
	 * @return array The return data.
	 */
	public function process_payment() {
		return $this->set_environment_meta_data()->get_success_return();
	}

	/**
	 * Return the processor to use for the given order.
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return KP_Payment_Processor
	 */
	public static function get_processor( $order ) {
		if ( self::is_kec() ) {
			return new KP_Payment_Processor_KEC( $order );
		}

		if ( self::is_blocks( $order ) || self::is_subscription( $order ) ) {
			return new KP_Payment_Processor_HPP( $order );
		}

		return new KP_Payment_Processor_KP( $order );
	}

	/**
	 * If the order was created with WooCommerce subscriptions.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	protected static function is_subscription( $order ) {
		return function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order->get_id() );
	}

	/**
	 * If the order was created via the Klarna Express Checkout.
	 *
	 * @return bool
	 */
	protected static function is_kec() {
		$kec_client_token = KrokedilKlarnaPaymentsDeps\Krokedil\KlarnaExpressCheckout\Session::get_client_token();

		return ! empty( $kec_client_token );
	}

	/**
	 * If the order was created via the Store API using the blocks checkout.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	protected static function is_blocks( $order ) {
		return $order && is_a( $order, WC_Order::class) && $order->is_created_via( 'store-api' );
	}

	/**
	 * Get the Klarna country code for the order.
	 *
	 * @return string
	 */
	protected function get_klarna_country() {
		$klarna_country = KP_WC()->session->get_klarna_session_country( $this->order );

		if ( empty( $klarna_country ) ) {
			throw new WP_Exception( __( 'Failed to get required data from the Klarna session. Please try again.', 'krokedil-klarna-payments' ) );
		}

		return $klarna_country;
	}

	/**
	 * Get the Klarna session id for the order.
	 *
	 * @return string
	 */
	protected function get_session_id() {
		$session_id = KP_WC()->session->get_klarna_session_id();

		if ( empty( $session_id ) ) {
			throw new WP_Exception( __( 'Failed to get required data from the Klarna session. Please try again.', 'krokedil-klarna-payments' ) );
		}

		return $session_id;
	}

	/**
	 * Get the Klarna testmode setting for the order.
	 *
	 * @return bool
	 */
	protected function get_testmode() {
		return wc_string_to_bool( $this->settings['testmode'] ?? 'no' );
	}

	/**
	 * Get the customer type.
	 *
	 * @return string
	 */
	protected function get_customer_type() {
		return $this->settings['customer_type'] ?? 'b2c';
	}

	/**
	 * Add the Klarna environment meta data to the order. Save the order if $save is true.
	 *
	 * @param bool $save Whether to save the order or not.
	 *
	 * @return self
	 */
	protected function set_environment_meta_data( $save = true ) {
		$environment = $this->testmode ? 'test' : 'live';
		$this->order->add_meta_data( '_wc_klarna_environment', $environment, true );
		$this->order->add_meta_data( '_wc_klarna_country', $this->get_klarna_country(), true );
		$this->order->add_meta_data( '_kp_session_id', $this->get_session_id(), true );

		if ( $save ) {
			$this->order->save();
		}

		return $this;
	}

	/**
	 * Get the return for a successful Klarna Payments session.
	 *
	 * @return array The return data.
	 */
	protected function get_success_return() {
		return array(
			'result'    => 'success',
			'order_id'  => $this->order->get_id(),
			'order_key' => $this->order->get_order_key(),
		);
	}
}