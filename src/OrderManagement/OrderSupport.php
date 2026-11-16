<?php
namespace Krokedil\Klarna\OrderManagement;

use Krokedil\Klarna\OrderManagement;
use Krokedil\Klarna\Logging\LogMasking;
use KrokedilKlarnaPaymentsDeps\Krokedil\Support\OrderSupport as SupportOrderSupport;
use KrokedilKlarnaPaymentsDeps\Krokedil\WpApi\Masking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The support package's order export, with the order as Klarna has it added as the integration order.
 */
class OrderSupport extends SupportOrderSupport {
	/**
	 * The order management instance, used to retrieve the Klarna order.
	 *
	 * @var OrderManagement
	 */
	private $order_management;

	/**
	 * Class constructor.
	 *
	 * @param OrderManagement $order_management The order management instance.
	 */
	public function __construct( $order_management ) {
		$this->order_management = $order_management;
		parent::__construct( LogMasking::SLUG, '_wc_klarna_order_id' );
	}

	/**
	 * Get the order as Klarna has it, masked like the order management logs. When it cannot be
	 * retrieved, the reason is returned under 'error' so the export says why it is missing.
	 *
	 * @param \WC_Order $order The order object.
	 *
	 * @return array
	 */
	public function get_integration_order_data( $order ) {
		try {
			$klarna_order_id = ! empty( $order->get_transaction_id() ) ? $order->get_transaction_id() : $order->get_meta( '_wc_klarna_order_id', true );
			if ( empty( $klarna_order_id ) ) {
				return array( 'error' => 'The order has no Klarna order ID.' );
			}

			$klarna_order = $this->order_management->retrieve_klarna_order( $order->get_id() );

			// Only the message: the error data holds the request args, with the Authorization header.
			if ( is_wp_error( $klarna_order ) ) {
				return array( 'error' => 'Could not retrieve the order from Klarna: ' . $klarna_order->get_error_message() );
			}

			$data = json_decode( (string) wp_json_encode( $klarna_order ), true );
			if ( ! is_array( $data ) ) {
				return array( 'error' => 'Klarna returned an unexpected response for the order.' );
			}

			// The same two steps the order management logs go through.
			$data = LogMasking::mask_response( $data );
			$data = Masking::mask_keys( $data, LogMasking::SLUG );

			return is_array( $data ) ? $data : array( 'error' => 'The Klarna order could not be masked, so it was left out.' );
		} catch ( \Throwable $e ) {
			return array( 'error' => 'Could not retrieve the order from Klarna.' );
		}
	}
}
