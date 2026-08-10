<?php

namespace Krokedil\Klarna\ExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX class for Klarna Express Checkout.
 */
class AJAX {
	/**
	 * The callable to get the payload for the Klarna Express Checkout.
	 *
	 * @var callable
	 */
	public $get_payload;

	/**
	 * The callback for the finalization of the Klarna Express Checkout.
	 *
	 * @var callable
	 */
	public $finalize_callback;

	/**
	 * The client token parser.
	 *
	 * @var ClientTokenParser
	 */
	private $client_token_parser;

	/**
	 * AJAX constructor.
	 *
	 * @param ClientTokenParser $client_token_parser The client token parser.
	 */
	public function __construct( $client_token_parser ) {
		$this->client_token_parser = $client_token_parser;
		$this->add_ajax_events();
	}

	/**
	 * Register all AJAX event hooks.
	 *
	 * @return void
	 */
	public function add_ajax_events() {
		$ajax_events = array(
			'kec_get_payload',
			'kec_set_cart',
			'kec_auth_callback',
			'kec_finalize_callback',
			'kec_one_step_get_initiate_body',
			'kec_one_step_shipping_address_change',
			'kec_one_step_shipping_option_changed',
		);

		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wc_ajax_' . $ajax_event, array( $this, $ajax_event ) );
		}
	}

	/**
	 * Set the callable to get the payload for the Klarna Express Checkout.
	 *
	 * @param callable $get_payload_method The callable.
	 *
	 * @return void
	 */
	public function set_get_payload( $get_payload_method ) {
		$this->get_payload = $get_payload_method;
	}

	/**
	 * Set the callback for the finalization of the Klarna Express Checkout.
	 *
	 * @param callable $finalize_callback The callable.
	 *
	 * @return void
	 */
	public function set_finalize_callback( $finalize_callback ) {
		$this->finalize_callback = $finalize_callback;
	}

	/**
	 * Get the payload for the Klarna Express Checkout.
	 *
	 * @return void
	 * @throws \Exception If the payload could not be retrieved.
	 */
	public function kec_get_payload() {
		check_ajax_referer( 'kec_get_payload', 'nonce' );

		try {
			$payload = call_user_func( $this->get_payload );

			if ( ! is_array( $payload ) ) {
				throw new \Exception( 'Could not get a Payload for the cart' );
			}

			wp_send_json_success( $payload );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Set the cart in WooCommerce to the product KEC was initiated from.
	 *
	 * @return void
	 * @codeCoverageIgnore
	 */
	public function kec_set_cart() {
		check_ajax_referer( 'kec_set_cart', 'nonce' );

		$posted_data = filter_input_array( INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$product_id   = $posted_data['product_id'] ?? '';
		$variation_id = $posted_data['variation_id'] ?? null;

		if ( empty( $product_id ) ) {
			wp_send_json_error( 'No product ID was posted' );
		}

		WC()->cart->empty_cart();

		$result = WC()->cart->add_to_cart( $product_id, 1, $variation_id );

		if ( ! $result ) {
			wp_send_json_error( 'Could not add the product to the cart' );
		}

		wp_send_json_success();
	}

	/**
	 * Handle the auth callback for the two-step KEC flow.
	 *
	 * @return void
	 * @codeCoverageIgnore
	 */
	public function kec_auth_callback() {
		check_ajax_referer( 'kec_auth_callback', 'nonce' );

		$posted_data = filter_input_array( INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$result = $posted_data['result'] ?? array();

		if ( empty( $result ) ) {
			wp_send_json_error( 'No result was posted' );
		}

		$approved       = $result['approved'] ?? false;
		$client_token   = $result['client_token'] ?? '';
		$klarna_address = $result['collected_shipping_address'] ?? array();

		try {
			$token = $this->client_token_parser->parse( $client_token );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		if ( ! $approved ) {
			wp_send_json_error( 'The payment was not approved by Klarna' );
		}

		$klarna_address = $result['collected_shipping_address'];

		$this->set_customer_address( $klarna_address );
		Session::set_client_token( $client_token );
		Session::set_klarna_address( $klarna_address );

		/**
		 * Triggers after the two-step Klarna Express Checkout auth callback has been processed and the customer address and client token stored in the session.
		 *
		 * @param array $result The auth callback result from Klarna, including the approval status, client token and collected shipping address.
		 */
		do_action( 'kec_auth_callback_processed', $result );

		wp_send_json_success( wc_get_checkout_url() );
	}

	/**
	 * Handle the finalize callback for the two-step KEC flow.
	 *
	 * @return void
	 * @throws \Exception If the order could not be finalized.
	 * @codeCoverageIgnore
	 */
	public function kec_finalize_callback() {
		check_ajax_referer( 'kec_finalize_callback', 'nonce' );

		$posted_data = filter_input_array( INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$result    = $posted_data['result'] ?? array();
		$order_id  = $posted_data['order_id'] ?? '';
		$order_key = $posted_data['order_key'] ?? '';

		if ( empty( $result ) ) {
			wp_send_json_error( 'No result was posted' );
		}

		$approved   = $result['approved'] ?? false;
		$auth_token = $result['authorization_token'] ?? '';

		if ( ! $approved ) {
			wp_send_json_error( 'The payment was not approved by Klarna' );
		}

		try {
			if ( ! is_callable( $this->finalize_callback ) ) {
				throw new \Exception( 'Could not finalize the order' );
			}

			$callback_response = call_user_func( $this->finalize_callback, $auth_token, $order_id, $order_key );

			if ( ! is_array( $callback_response ) ) {
				throw new \Exception( 'Could not finalize the order' );
			}

			wp_send_json_success( $callback_response );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Get the initiate body for the one-step checkout request.
	 *
	 * @return void
	 */
	public function kec_one_step_get_initiate_body() {
		check_ajax_referer( 'kec_one_step_get_initiate_body', 'nonce' );

		$source = filter_input( INPUT_POST, 'source', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $source ) || 'unknown' === $source ) {
			wp_send_json_error( "Missing or invalid source: {$source}" );
		}

		if ( 'cart' !== $source ) {
			$variation_id = filter_input( INPUT_POST, 'variation_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$source       = absint( $source );
			$product      = wc_get_product( $source );

			if ( ! $product ) {
				wp_send_json_error( 'Invalid product ID' );
			}

			WC()->cart->empty_cart();

			$result = WC()->cart->add_to_cart( $source, 1, $variation_id ?? 0 );

			if ( ! $result ) {
				wp_send_json_error( 'Could not add the product to the cart' );
			}
		}

		wp_send_json_success( OneStepCheckout::get_initiate_body() );
	}

	/**
	 * Handle the shipping address change event for the one-step flow.
	 *
	 * @return void
	 */
	public static function kec_one_step_shipping_address_change() {
		check_ajax_referer( 'kec_one_step_shipping_address_change', 'nonce' );

		$post_data = filter_input_array(
			INPUT_POST,
			array(
				'shippingAddress'  => array(
					'flags'    => FILTER_REQUIRE_ARRAY,
					'city'     => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
					'postcode' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
					'region'   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
					'country'  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
				),
				'paymentRequestId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
				'paymentToken'     => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			)
		);

		$shipping_address   = $post_data['shippingAddress'] ?? array();
		$payment_request_id = $post_data['paymentRequestId'] ?? '';

		if ( empty( $shipping_address ) ) {
			wp_send_json_error( 'No shipping address was posted' );
		}

		wp_send_json_success( OneStepCheckout::get_shipping_address_change_body( $shipping_address, $payment_request_id ) );
	}

	/**
	 * Set the selected shipping method and return the updated response body.
	 *
	 * @return void
	 */
	public static function kec_one_step_shipping_option_changed() {
		check_ajax_referer( 'kec_one_step_shipping_option_changed', 'nonce' );

		$selected_option = filter_input( INPUT_POST, 'selectedOption', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $selected_option ) ) {
			wp_send_json_error( 'No shipping option was selected' );
		}

		wp_send_json_success( OneStepCheckout::get_changed_shipping_option_response( $selected_option ) );
	}

	/**
	 * Set the customer address on the WooCommerce customer object.
	 *
	 * @param array $klarna_address The Klarna address.
	 *
	 * @return void
	 * @codeCoverageIgnore
	 */
	private function set_customer_address( $klarna_address ) {
		WC()->customer->set_billing_address( $klarna_address['street_address'] ?? '' );
		WC()->customer->set_billing_address_2( $klarna_address['street_address2'] ?? '' );
		WC()->customer->set_billing_city( $klarna_address['city'] ?? '' );
		WC()->customer->set_billing_postcode( $klarna_address['postal_code'] ?? '' );
		WC()->customer->set_billing_country( $klarna_address['country'] ?? '' );
		WC()->customer->set_billing_first_name( $klarna_address['given_name'] ?? '' );
		WC()->customer->set_billing_last_name( $klarna_address['family_name'] ?? '' );
		WC()->customer->set_billing_company( $klarna_address['organization_name'] ?? '' );
		WC()->customer->set_billing_email( $klarna_address['email'] ?? '' );
		WC()->customer->set_billing_phone( $klarna_address['phone'] ?? '' );

		WC()->customer->set_shipping_address( $klarna_address['street_address'] ?? '' );
		WC()->customer->set_shipping_address_2( $klarna_address['street_address2'] ?? '' );
		WC()->customer->set_shipping_city( $klarna_address['city'] ?? '' );
		WC()->customer->set_shipping_postcode( $klarna_address['postal_code'] ?? '' );
		WC()->customer->set_shipping_country( $klarna_address['country'] ?? '' );
		WC()->customer->set_shipping_first_name( $klarna_address['given_name'] ?? '' );
		WC()->customer->set_shipping_last_name( $klarna_address['family_name'] ?? '' );
		WC()->customer->set_shipping_company( $klarna_address['organization_name'] ?? '' );
		WC()->customer->set_shipping_phone( $klarna_address['phone'] ?? '' );
	}
}
