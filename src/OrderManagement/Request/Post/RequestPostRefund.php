<?php
namespace Krokedil\Klarna\OrderManagement\Request\Post;

use Krokedil\Klarna\OrderManagement\Request\RequestPost;
use Krokedil\Klarna\OrderManagement\KlarnaOrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST request class for order refund
 */
class RequestPostRefund extends RequestPost {

	/**
	 * The Refund Reason
	 *
	 * @var string
	 */
	protected $refund_reason;

	/**
	 * The Refund Amount
	 *
	 * @var integer
	 */
	protected $refund_amount;

	/**
	 * The Return Fee
	 *
	 * @var array
	 */
	protected $return_fee;

	/**
	 * The Refund ID
	 *
	 * @var string
	 */
	protected $refund_id;

	/**
	 * Class constructor.
	 *
	 * @param KlarnaOrderManagement $order_management The order management instance.
	 * @param array                 $arguments The request arguments.
	 */
	public function __construct( $order_management, $arguments ) {
		parent::__construct( $order_management, $arguments );
		$this->log_title     = 'Refund Klarna order';
		$this->refund_reason = $arguments['refund_reason'];
		$this->refund_amount = $arguments['refund_amount'];
		$this->return_fee    = $arguments['return_fee'] ?? array();
		$this->refund_id     = $arguments['refund_id'] ?? '';
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$data = array(
			'refunded_amount' => round( $this->refund_amount * 100 ),
			'description'     => $this->refund_reason,
		);

		// Get the original order number.
		$order        = wc_get_order( $this->order_id );
		$order_number = empty( $order ) ? $this->order_id : $order->get_order_number();

		// Add the order number and refund id if available.
		if ( ! empty( $this->refund_id ) ) {
			$data['reference'] = "{$order_number}|{$this->refund_id}";
		}

		$refund_order_lines = $this->get_refund_order_lines();

		if ( isset( $refund_order_lines ) && ! empty( $refund_order_lines ) ) {
			$data['order_lines'] = $refund_order_lines;
		}

		return $data;
	}

	/**
	 * Returns the refund order lines.
	 *
	 * @return array
	 */
	public function get_refund_order_lines() {
		$refund_id = $this->get_refunded_order_id( $this->order_id );

		if ( ! empty( $refund_id ) ) {
			$refund_order            = wc_get_order( $refund_id );
			$order                   = wc_get_order( $this->order_id );
			$order_items             = $order->get_items();
			$refunded_items          = $refund_order->get_items();
			$refunded_shipping       = $refund_order->get_shipping_method();
			$refunded_shipping_items = $refund_order->get_items( 'shipping' );
			$settings                = $this->order_management->settings->get_settings( $this->order_id );
			$customer_type           = $settings['customer_type'] ?? 'b2c';
			$order_lines_processor   = new \KP_Order_Data( $customer_type, $this->order_id );
			$separate_sales_tax      = $order_lines_processor->separate_sales_tax;
			$data                    = array();

			if ( $refunded_items ) {
				/**
				 * Process order item products.
				 *
				 * @var WC_Order_Item_Product $item WooCommerce order item product.
				 */
				foreach ( $refunded_items as $item ) {
					$product = wc_get_product( $item->get_product_id() );

					/**
					 * Get the order line total from order for calculation.
					 *
					 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
					 */
					foreach ( $order_items as $order_item ) {
						if ( $item->get_product_id() === $order_item->get_product_id() ) {
							$order_line_total    = round( ( $order->get_line_subtotal( $order_item, false ) * 100 ) );
							$order_line_tax      = round( ( $order->get_line_tax( $order_item ) * 100 ) );
							$tax_rates           = \WC_Tax::get_base_tax_rates( $order_item->get_tax_class() );
							$first_tax_rate      = reset( $tax_rates );
							$order_line_tax_rate = ( 0 !== $order_line_tax && 0 !== $order_line_total ) ? ( isset( $first_tax_rate['rate'] ) ? $first_tax_rate['rate'] * 100 : round( ( $order_line_tax / $order_line_total ) * 100 * 100 ) ) : 0;
						}
					}

					/**
					 *
					 *  If a product is not available inside of WC anymore wc_get_product() will return false
					 *  and the default check will fail resulting in an fatal error, creating the Refund with WC but not sending it to Klarna
					 *  This fallback allows DEVs to provide the product type which they saved before.
					 *
					 *  Alternatively order management could save this information on order creation.
					 */

					if ( is_object( $product ) && method_exists( $product, 'is_downloadable' ) ) {
							$type = $product->is_downloadable() || $product->is_virtual() ? 'digital' : 'physical';
					} else {
							$type = apply_filters( 'kom_line_item_product_type', 'physical', $item );
					}

					$reference           = $this->get_refund_item_reference( $item );
					$name                = wp_strip_all_tags( $item->get_name() );
					$quantity            = abs( $item->get_quantity() ?? 1 );
					$refund_price_amount = round( abs( $refund_order->get_line_subtotal( $item, false ) ) * 100 );
					$total_discount      = $this->get_refund_item_discount_amount( $item, $separate_sales_tax );
					$refund_tax_amount   = $separate_sales_tax ? 0 : abs( $this->get_refund_item_tax_amount( $item, $separate_sales_tax ) );
					$unit_price          = round( ( $refund_price_amount + $refund_tax_amount ) / $quantity );
					$total               = round( $quantity * $unit_price );
					$item_data           = array(
						'type'                  => $type,
						'reference'             => $reference,
						'name'                  => $name,
						'quantity'              => $quantity,
						'unit_price'            => $unit_price,
						'tax_rate'              => $order_line_tax_rate,
						'total_amount'          => $total,
						'total_discount_amount' => $total_discount,
						'total_tax_amount'      => $refund_tax_amount,
					);

					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Can be float *or* integer, so non-strict is required.
						$data[] = $item_data;
					}
				}
			}
			// if shipping is refunded.
			if ( $refunded_shipping ) {
				/**
				 * Process Shipping
				 *
				 * @var WC_Order_Item_Shipping $shipping_item WooCommerce order item *shipping*.
				 */
				foreach ( $refunded_shipping_items as $shipping_item ) {

					$order_shipping_total    = round( $order->get_shipping_total() * 100 );
					$order_shipping_tax      = round( $order->get_shipping_tax() * 100 );
					$order_shipping_tax_rate = round( ( $order_shipping_tax / $order_shipping_total ) * 100 * 100 );

					$type                = 'shipping_fee';
					$reference           = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
					$name                = $shipping_item->get_name();
					$quantity            = 1;
					$total_discount      = $refund_order->get_total_discount( false );
					$refund_price_amount = round( abs( $shipping_item->get_total() ) * 100 );
					$refund_tax_amount   = $separate_sales_tax ? 0 : round( abs( $shipping_item->get_total_tax() ) * 100 );
					$unit_price          = round( $refund_price_amount + $refund_tax_amount );
					$total               = round( $quantity * $unit_price );
					$shipping_data       = array(
						'type'                  => $type,
						'reference'             => $reference,
						'name'                  => $name,
						'quantity'              => $quantity,
						'unit_price'            => $unit_price,
						'tax_rate'              => $order_shipping_tax_rate,
						'total_amount'          => $total,
						'total_discount_amount' => $total_discount,
						'total_tax_amount'      => $refund_tax_amount,
					);

					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Can be float *or* integer, so non-strict is required.
						$data[] = $shipping_data;
					}
				}
			}
			// If separate sales tax and if tax is being refunded.
			if ( $separate_sales_tax && '0' != $refund_order->get_total_tax() ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Can be float *or* integer, so non-strict is required.
				$sales_tax_amount = round( abs( $refund_order->get_total_tax() ) * 100 );

				// Add sales tax line item.
				$sales_tax = array(
					'type'                  => 'sales_tax',
					'reference'             => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
					'name'                  => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
					'quantity'              => 1,
					'unit_price'            => $sales_tax_amount,
					'tax_rate'              => 0,
					'total_amount'          => $sales_tax_amount,
					'total_discount_amount' => 0,
					'total_tax_amount'      => 0,
				);

				$data[] = $sales_tax;
			}

			// If return fees are set.
			if ( ! empty( $this->return_fee ) ) {
				add_filter( 'klarna_applied_return_fees', fn( $fees ) => array_merge( $fees, $this->return_fee ), 10, 1 );

				// Calculate the tax rate for the return fee.
				$return_fee_tax_rate = 0;
				$tax_rate_id         = $this->return_fee['tax_rate_id'] ?? 0;
				if ( $tax_rate_id ) {
					$tax_rate_data = \WC_Tax::_get_tax_rate( $tax_rate_id );
					if ( $tax_rate_data && isset( $tax_rate_data['tax_rate'] ) ) {
						$return_fee_tax_rate = round( floatval( $tax_rate_data['tax_rate'] ) * 100 );
					}
				}

				$return_fee = array(
					'type'             => 'return_fee',
					'name'             => __( 'Return fee', 'klarna-payments-for-woocommerce' ),
					'quantity'         => 1,
					'unit_price'       => round( -1 * ( abs( $this->return_fee['amount'] + $this->return_fee['tax_amount'] ) * 100 ) ),
					'tax_rate'         => $return_fee_tax_rate,
					'total_amount'     => round( -1 * ( abs( $this->return_fee['amount'] + $this->return_fee['tax_amount'] ) * 100 ) ),
					'total_tax_amount' => round( -1 * ( abs( $this->return_fee['tax_amount'] ) * 100 ) ),
				);

				$data[] = $return_fee;
			}
		}

		return apply_filters( 'kom_refund_order_args', $data, $this->order_id );
	}

	/**
	 * Returns the id of the refunded order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string
	 */
	public function get_refunded_order_id( $order_id ) {
		$order = wc_get_order( $order_id );

		/* Always retrieve the most recent (current) refund (index 0). */
		return $order->get_refunds()[0]->get_id();
	}

	/**
	 * Get the item reference for the refund order line based on the type of the order line item.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item WooCommerce order line item.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	public function get_refund_item_reference( $order_line_item ) {
		if ( 'line_item' === $order_line_item->get_type() ) {
			$product = $order_line_item['variation_id'] ? wc_get_product( $order_line_item['variation_id'] ) : wc_get_product( $order_line_item['product_id'] );
			if ( $product ) {
				if ( $product->get_sku() ) {
					$item_reference = $product->get_sku();
				} else {
					$item_reference = $product->get_id();
				}
			} else {
				$item_reference = $order_line_item->get_name();
			}
		} elseif ( 'shipping' === $order_line_item->get_type() ) {
			// Matching the shipping reference from Klarna order.
			$item_reference = $order_line_item->get_method_id() . ':' . $order_line_item->get_instance_id();
		} elseif ( 'coupon' === $order_line_item->get_type() ) {
			$item_reference = 'Discount';
		} elseif ( 'fee' === $order_line_item->get_type() ) {
			$item_reference = 'Fee';
		} else {
			$item_reference = $order_line_item->get_name();
		}

		return substr( (string) $item_reference, 0, 64 );
	}

	/**
	 * Get refund cart item discount.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 * @param bool                                                                                $separate_sales_tax Whether sales tax is separate.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	public function get_refund_item_discount_amount( $order_line_item, $separate_sales_tax ) {
		if ( $order_line_item['subtotal'] > $order_line_item['total'] ) {
			if ( $separate_sales_tax ) {
				$item_discount_amount = ( $order_line_item['subtotal'] - $order_line_item['total'] ) * 100;
			} else {
				$item_discount_amount = ( $order_line_item['subtotal'] + $order_line_item['subtotal_tax'] - $order_line_item['total'] - $order_line_item['total_tax'] ) * 100;
			}
		} else {
			$item_discount_amount = 0;
		}

		return round( $item_discount_amount );
	}

	/**
	 * Calculate refund item tax percentage.
	 *
	 * @param  WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_line_item Order line item.
	 * @param  bool                                                                                $separate_sales_tax Whether sales tax is separate.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_refund_item_tax_amount( $order_line_item, $separate_sales_tax ) {
		if ( $separate_sales_tax ) {
			$item_tax_amount = 00;
		} elseif ( in_array( $order_line_item->get_type(), array( 'line_item', 'fee', 'shipping' ), true ) ) {
				$item_tax_amount = $order_line_item->get_total_tax() * 100;
		} elseif ( 'coupon' === $order_line_item->get_type() ) {
			$item_tax_amount = $order_line_item->get_discount_tax() * 100;
		} else {
			$item_tax_amount = 00;
		}

		return round( $item_tax_amount );
	}
}
