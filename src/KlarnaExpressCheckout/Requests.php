<?php

namespace Krokedil\Klarna\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Factory for KEC notification API requests.
 */
class Requests {
	/**
	 * Create a new signing key for notifications.
	 *
	 * @return array|\WP_Error
	 */
	public static function create_signing_key() {
		$request = new Requests\Notification\CreateSigningKey( array( 'country' => kp_get_klarna_country() ) );

		return $request->request();
	}

	/**
	 * Delete a signing key for notifications.
	 *
	 * @param string $signing_key_id The signing key ID to delete.
	 *
	 * @return array|\WP_Error
	 */
	public static function delete_signing_key( $signing_key_id ) {
		$request = new Requests\Notification\DeleteSigningKey(
			array(
				'signing_key_id' => $signing_key_id,
				'country'        => kp_get_klarna_country(),
			)
		);

		return $request->request();
	}

	/**
	 * Create a new webhook for notifications.
	 *
	 * @param string $url            The URL to receive notifications.
	 * @param array  $event_types    The event types to subscribe to.
	 * @param string $event_version  The event version to use.
	 * @param string $signing_key_id The signing key ID to use.
	 *
	 * @return array|\WP_Error
	 */
	public static function create_webhook( $url, $event_types, $event_version, $signing_key_id ) {
		$request = new Requests\Notification\CreateWebhook(
			array(
				'url'            => $url,
				'event_types'    => $event_types,
				'event_version'  => $event_version,
				'signing_key_id' => $signing_key_id,
				'country'        => kp_get_klarna_country(),
			)
		);

		return $request->request();
	}

	/**
	 * Delete a webhook for notifications.
	 *
	 * @param string $webhook_id The webhook ID to delete.
	 *
	 * @return array|\WP_Error
	 */
	public static function delete_webhook( $webhook_id ) {
		$request = new Requests\Notification\DeleteWebhook(
			array(
				'webhook_id' => $webhook_id,
				'country'    => kp_get_klarna_country(),
			)
		);

		return $request->request();
	}

	/**
	 * Simulate a webhook for testing purposes.
	 *
	 * @param string $webhook_id    The webhook ID to simulate.
	 * @param string $event_type    The event type to simulate.
	 * @param string $event_version The event version to simulate.
	 *
	 * @return array|\WP_Error
	 */
	public static function simulate_webhook( $webhook_id, $event_type, $event_version ) {
		$request = new Requests\Notification\SimulateWebhook(
			array(
				'webhook_id'    => $webhook_id,
				'event_type'    => $event_type,
				'event_version' => $event_version,
				'country'       => kp_get_klarna_country(),
			)
		);

		return $request->request();
	}
}
