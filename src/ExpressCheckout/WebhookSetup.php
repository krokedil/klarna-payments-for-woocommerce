<?php

namespace Krokedil\Klarna\ExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the lifecycle of KEC webhooks and signing keys in Klarna.
 */
class WebhookSetup {
	const CREATE_WEBHOOK_ACTION   = 'create_webhook';
	const SIMULATE_WEBHOOK_ACTION = 'simulate_webhook';
	const DELETE_WEBHOOK_ACTION   = 'delete_webhook';

	/**
	 * Instance of the ExpressCheckout main class.
	 *
	 * @var \Krokedil\Klarna\ExpressCheckout
	 */
	private $kec;

	/**
	 * Errors generated during webhook setup.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Messages generated during webhook setup.
	 *
	 * @var array
	 */
	private $messages = array();

	/**
	 * Class constructor.
	 *
	 * @param \Krokedil\Klarna\ExpressCheckout $kec Instance of the main KEC class.
	 *
	 * @return void
	 */
	public function __construct( $kec ) {
		$this->kec = $kec;
		add_action( 'admin_init', array( $this, 'maybe_process_webhook_action' ) );
	}

	/**
	 * Process a webhook action from the admin settings URL.
	 *
	 * @return void
	 */
	public function maybe_process_webhook_action() {
		if ( ! isset( $_GET['kec_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$actions = array( self::CREATE_WEBHOOK_ACTION, self::SIMULATE_WEBHOOK_ACTION, self::DELETE_WEBHOOK_ACTION );
		$action  = sanitize_text_field( wp_unslash( $_GET['kec_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $action, $actions, true ) ) {
			return;
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), "kec_$action" ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		try {
			switch ( $action ) {
				case self::CREATE_WEBHOOK_ACTION:
					$this->create_webhook();
					break;
				case self::SIMULATE_WEBHOOK_ACTION:
					$this->simulate_webhook();
					break;
				case self::DELETE_WEBHOOK_ACTION:
					$this->delete_webhook();
					break;
			}

			$this->redirect_to_settings();
		} catch ( \Exception $e ) {
			$this->add_error( $e->getMessage() );
		}
	}

	/**
	 * Add a success message.
	 *
	 * @param string $message The message to add.
	 *
	 * @return void
	 */
	private function add_message( $message ) {
		$this->messages[] = $message;
	}

	/**
	 * Add an error message.
	 *
	 * @param string $message The error message to add.
	 *
	 * @return void
	 */
	private function add_error( $message ) {
		$this->errors[] = $message;
	}

	/**
	 * Trigger a test webhook delivery from Klarna.
	 *
	 * @throws \Exception If the webhook simulation request fails.
	 * @return void
	 */
	private function simulate_webhook() {
		$webhook = $this->kec->settings()->get_webhook();

		if ( empty( $webhook ) ) {
			return;
		}

		$response = Requests::simulate_webhook( $webhook['webhook_id'], 'payment.request.state-change.submitted', 'v2' );

		if ( is_wp_error( $response ) ) {
			$message = __( 'Could not send test webhook', 'klarna-payments-for-woocommerce' );
			throw new \Exception( esc_html( $message ) );
		}

		$this->add_message( __( 'Test webhook sent successfully.', 'klarna-payments-for-woocommerce' ) );
	}

	/**
	 * Delete the webhook and signing key in Klarna.
	 *
	 * @throws \Exception If deleting the webhook or signing key fails.
	 * @return void
	 */
	private function delete_webhook() {
		$signing_key = $this->kec->settings()->get_signing_key();
		$webhook     = $this->kec->settings()->get_webhook();

		if ( empty( $signing_key ) && empty( $webhook ) ) {
			return;
		}

		if ( ! empty( $webhook ) ) {
			$delete_webhook_response = Requests::delete_webhook( $webhook['webhook_id'] );

			if ( is_wp_error( $delete_webhook_response ) ) {
				$message = __( 'Could not remove webhook', 'klarna-payments-for-woocommerce' );
				throw new \Exception( esc_html( $message ) );
			}

			delete_option( 'kec_webhook' );
		}

		if ( ! empty( $signing_key ) ) {
			$delete_signing_key_response = Requests::delete_signing_key( $signing_key['signing_key_id'] );

			if ( is_wp_error( $delete_signing_key_response ) ) {
				$message = __( 'Could not remove signing key', 'klarna-payments-for-woocommerce' );
				throw new \Exception( esc_html( $message ) );
			}

			delete_option( 'kec_signing_key' );
		}

		$this->add_message( __( 'Webhook removed successfully.', 'klarna-payments-for-woocommerce' ) );
	}

	/**
	 * Create a signing key for the webhook in Klarna.
	 *
	 * @throws \Exception If the signing key creation request fails.
	 * @return array|\WP_Error
	 */
	public function create_signing_key() {
		$response = Requests::create_signing_key();

		if ( is_wp_error( $response ) ) {
			$message = __( 'Could not create signing key', 'klarna-payments-for-woocommerce' );
			throw new \Exception( esc_html( $message ) );
		}

		$this->kec->settings()->update_setting( 'kec_signing_key', $response );

		return $response;
	}

	/**
	 * Create the webhook in Klarna.
	 *
	 * @throws \Exception If the webhook creation request fails.
	 * @return void
	 */
	public function create_webhook() {
		$stored_webhook = $this->kec->settings()->get_webhook();
		if ( ! empty( $stored_webhook ) ) {
			return;
		}

		$signing_key = $this->kec->settings()->get_signing_key();
		if ( empty( $signing_key ) ) {
			$signing_key = $this->create_signing_key();
		}

		if ( is_wp_error( $signing_key ) ) {
			$message = __( 'Could not create a signing key with Klarna for the webhook.', 'klarna-payments-for-woocommerce' );
			throw new \Exception( esc_html( $message ) );
		}

		$url           = get_rest_url( null, '/klarna/v1/kec/notifications' );
		$event_types   = array(
			'payment.request.state-change.completed',
			'payment.request.state-change.expired',
		);
		$event_version = 'v2';
		$signing_key   = $this->kec->settings()->get_signing_key();

		$response = Requests::create_webhook( $url, $event_types, $event_version, $signing_key['signing_key_id'] );

		if ( is_wp_error( $response ) ) {
			$message = __( 'Could not create webhook with Klarna', 'klarna-payments-for-woocommerce' );
			throw new \Exception( esc_html( $message ) );
		}

		$this->kec->settings()->update_setting( 'kec_webhook', $response );
		$this->add_message( __( 'Webhook created successfully.', 'klarna-payments-for-woocommerce' ) );
	}

	/**
	 * Redirect to the settings page, appending any messages or errors as URL parameters.
	 *
	 * @return void
	 */
	private function redirect_to_settings() {
		$settings_url = KP_WC()->get_setting_link();

		if ( ! empty( $this->errors ) ) {
			$settings_url = add_query_arg( 'kec_errors', base64_encode( wp_json_encode( $this->errors ) ), $settings_url ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		if ( ! empty( $this->messages ) ) {
			$settings_url = add_query_arg( 'kec_messages', base64_encode( wp_json_encode( $this->messages ) ), $settings_url ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$settings_url = "$settings_url#klarna-payments-settings-kec_settings";
		wp_safe_redirect( $settings_url );
		exit;
	}
}
