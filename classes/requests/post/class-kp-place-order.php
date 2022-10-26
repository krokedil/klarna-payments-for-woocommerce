<?php
/**
 * Class for the request to place a klarna order.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Place_Order class.
 */
class KP_Place_Order extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Place order';
		$this->request_filter = 'wc_klarna_payments_place_order_args';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$auth_token = $this->arguments['auth_token'];
		return $this->environment . "payments/v1/authorizations/${auth_token}/order";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order  = wc_get_order( $this->arguments['order_id'] );
		$helper = $this->get_helper();

		return array(
			'purchase_country'    => $this->country,
			'purchase_currency'   => $order->get_currency(),
			'locale'              => kp_get_locale(),
			'billing_address'     => KP_Customer_Data::get_billing_address( $this->order_id, $this->settings['customer_type'] ),
			'shipping_address'    => KP_Customer_Data::get_shipping_address( $this->order_id, $this->settings['customer_type'] ),
			'order_amount'        => $helper::get_kp_order_amount(),
			'order_tax_amount'    => $helper::get_kp_order_tax_amount(),
			'order_lines'         => $helper::get_kp_order_lines(),
			'customer'            => get_klarna_customer( $this->kp_settings['customer_type'] ),
			'merchant_reference1' => $order->get_order_number(),
			'merchant_urls'       => array(
				'confirmation' => $order->get_checkout_order_received_url(),
				'notification' => get_home_url() . '/wc-api/WC_Gateway_Klarna_Payments/?order_id=' . $this->order_id,
			),
		);
	}
}
