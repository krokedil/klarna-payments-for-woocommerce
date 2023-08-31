<?php
/**
 * Class for the issuing a customer token required for subscriptions.
 *
 * https://docs.klarna.com/api/payments/#operation/purchaseToken
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Create_Customer_Token class.
 */
class KP_Create_Customer_Token extends KP_Requests_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create customer token';
		$this->request_filter = 'wc_klarna_payments_create_customer_token_args';
		$this->endpoint       = "/payments/v1/authorizations/{$arguments['auth_token']}/customer-token";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$customer_type    = $this->settings['customer_type'] ?? 'b2c';
		$order_data       = new KP_Order_Data( $customer_type, $this->arguments['order_id'] );
		$order            = wc_get_order( $this->arguments['order_id'] );
		$customer_address = $order_data->get_klarna_customer_object();

		return array_merge(
			array(
				'billing_address'   => $customer_address['billing'],
				'customer'          => array(
					'type' => 'b2c' === $customer_type ? 'person' : 'organization',
				),
				'description'       => 'Subscription',
				'intended_use'      => 'SUBSCRIPTION',
				'locale'            => kp_get_locale(),
				'purchase_country'  => kp_get_klarna_country( $order ),
				'purchase_currency' => get_woocommerce_currency(),
			)
		);
	}
}
