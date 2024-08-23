<?php
/**
 * Class for sending supplementary data to Klarna.
 *
 * @package WC_Klarna_Payments/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_Supplementary_Data class.
 */
class KP_Send_Supplementary_Data extends KP_Requests_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Send supplementary data';
		$this->request_filter = 'wc_klarna_payments_send_supplementary_data_args';
		$this->endpoint       = 'v1/shopping/sessions';
	}

	protected function get_base_url( $country, $settings ) {
		return 'https://api-global.test.klarna.com';
	}

	/**
	 * Get the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Authorization' => 'Basic ',
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Adds the confirmation URL to the request body for Place order calls.
	 *
	 * @return array
	 */
	public function get_body() {
		$body = parent::get_body();

		$merchant_references = $this->get_merchant_references();
		$request_body        = array(
			'supplementary_purchase_data' => array(
				'merchant_references' => $merchant_references,
				'content_type'        => 'vnd.klarna.supplementary-data.v1',
				'content'             => array(
					'acquiring_channel'  => 'ECOMMERCE',
					'merchant_reference' => $merchant_references[ array_key_first( $merchant_references ) ],
					'line_items'         => $this->get_order_lines( $body ),
					'shipping'           => $this->get_shipping( $body ),
					'customer'           => $this->get_customer( $body ),
				),
			),
		);

		return $request_body;
	}

	/**
	 * Get the order lines from the request body.
	 *
	 * @param array $body The request body.
	 * @return array
	 */
	private function get_order_lines( $body ) {
		// Order line fields that Klarna's supplementary data API supports.
		$allowed_keys = array(
			'name',
			'quantity',
			'total_amount',
			'total_tax_amount',
			'unit_price',
			'product_url',
			'image_url',
			'product_identifier',
			'reference',
		);

		// Filter only the required fields in the order lines.
		$order_lines = array();
		foreach ( $body['order_lines'] as $index => $order_line ) {
			$order_lines[ $index ]['product_identifier'] = $body['order_lines'][ $index ]['product_reference'] ?? $body['order_lines'][ $index ]['reference'];
			$order_lines[ $index ]                       = array_intersect_key( $order_line, array_flip( $allowed_keys ) );
		}

		return $order_lines;
	}

	/**
	 * Get the merchant references.
	 *
	 * @return array
	 */
	private function get_merchant_references() {
		// Create a new shopping session ID if we don't already have one.
		$merchant_references = WC()->session->get( KP_Supplementary_Data::$session_key );
		if ( empty( $merchant_references ) ) {
			$merchant_references[] = KP_Supplementary_Data::generate_unique_id();
			WC()->session->set( KP_Supplementary_Data::$session_key, $merchant_references );
		}

		// Check if it is a resumable WC order. This can happen if the customer returns to the checkout page if they don't finalize the purchase.
		$pending_order_id = WC()->session->get( 'order_awaiting_payment' );
		if ( $pending_order_id ) {
			$pending_order = wc_get_order( $pending_order_id );

			// We're only interested in Klarna orders.
			if ( false !== strpos( $pending_order->get_payment_method(), 'klarna' ) ) {
				$payment_specific_order_id = $pending_order->get_meta( '_mollie_order_id' );
				if ( empty( $payment_specific_order_id ) ) {
					$payment_specific_order_id = $pending_order->get_meta( '_payment_intent_id' );
				}

				if ( ! empty( $payment_specific_order_id ) ) {
					$merchant_references[] = $payment_specific_order_id;
				}
			}
		}

		// If a WC order has been created, retrieve the payment order ID.
		if ( isset( $this->arguments['order_number'], $this->arguments['transaction_id'] ) ) {
			$order_id       = $this->arguments['order_number'];
			$transaction_id = $this->arguments['transaction_id'];
			$order          = wc_get_order( $order_id );

			// Retrieve the payment ID from Mollie.
			$payment_specific_order_id = $order->get_meta( '_mollie_order_id' );
			if ( empty( $payment_specific_order_id ) ) {
				// If it is not Mollie, check for Stripe. We only support these two as of now.
				$payment_specific_order_id = $order->get_meta( '_payment_intent_id' );
			}

			// If $transaction_id is set, we can skip retrieving specific payment order ID. Check if that exists first.
			$transaction_id      = ! empty( $payment_specific_order_id ) ? $payment_specific_order_id : $transaction_id;
			$merchant_references = array_merge( $merchant_references, array( $order->get_order_number(), $transaction_id ) );
		}

		return apply_filters( 'kole_merchant_references', array_unique( array_filter( $merchant_references ) ) );
	}

	/**
	 * Get the shipping data.
	 *
	 * @param array $body The request body.
	 * @return array
	 */
	private function get_shipping( $body ) {
		// The keys that Klarna's supplementary data API supports for recipient and address.
		$recipient_keys         = array(
			'given_name',
			'family_name',
			'email',
			'phone',
		);
		$recipient_address_keys = array( 'street_address', 'street_address2', 'postal_code', 'city', 'region', 'country' );

		$shipping_method    = array_filter(
			$body['order_lines'],
			function ( $order_line ) {
				return 'shipping_fee' === $order_line['type'];
			}
		);
		$shipping_method    = reset( $shipping_method );
		$shipping_reference = empty( $shipping_method ) ? '' : $shipping_method['reference'];

		$shipping_type = explode( ':', $shipping_reference )[0];
		switch ( $shipping_method ) {
			case 'flat_rate':
				$shipping_type = 'PHYSICAL_OTHER';
				break;
			case 'local_pickup':
				$shipping_type = 'PICKUP_STORE';
				break;
			default:
				$shipping_type = 'PHYSICAL_OTHER';
				break;
		}

		$shipping = array(
			'recipient'        => array_intersect_key( $body['shipping_address'], array_flip( $recipient_keys ) ),
			'address'          => array_intersect_key( $body['shipping_address'], array_flip( $recipient_address_keys ) ),
			'shipping_options' => array( 'shipping_type' => $shipping_type ),
		);

		if ( ! empty( $shipping_reference ) ) {
			$shipping['shipping_reference'] = $shipping_reference;
		}

		return apply_filters( 'kole_shipping', $shipping );
	}

	private function get_customer( $body ) {
		$customer_keys = array(
			'given_name',
			'family_name',
			'email',
			'phone',
		);

		$customer            = array_intersect_key( $body['billing_address'], array_flip( $customer_keys ) );
		$customer['address'] = $body['billing_address'];

		$order_id                    = $this->arguments['order_number'];
		$order                       = wc_get_order( $order_id );
		$customer['customer_device'] = array(
			'user_agent' => $order->get_customer_user_agent(),
			'ip_address' => $order->get_customer_ip_address(),
			wc_get_user_agent(),
		);

		return apply_filters( 'kole_customer', $customer );
	}
}
