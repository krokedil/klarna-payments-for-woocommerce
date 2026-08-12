<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * Canned Klarna responses for the checkout flow, the sibling of
 * CanDriveKlarnaOrderManagement, for the calls a purchase makes rather than
 * the ones an admin makes afterwards.
 */
trait CanDriveCheckout {

	/** Queues a successful create-session response. */
	protected function willCreateSession( array $overrides = [] ): void {
		$this->willRespondWith(
			array_merge(
				[
					'session_id'                => 'sess-1',
					'client_token'              => 'token-1',
					'payment_method_categories' => [
						[
							'identifier' => 'pay_later',
							'name'       => 'Pay later',
						],
					],
				],
				$overrides
			),
			200,
			'/payments/v1/sessions'
		);
	}

	/** Queues a successful hosted payment page. */
	protected function willCreateHpp( string $redirect_url = 'https://pay.playground.klarna.com/eu/hpp/payment/hpp-1' ): void {
		$this->willRespondWith(
			[
				'session_id'   => 'hpp-session-1',
				'redirect_url' => $redirect_url,
			],
			201,
			'hpp/v1/sessions'
		);
	}

	/** Queues a place-order response with the given verdict. */
	protected function willPlaceOrder( string $fraud_status = 'ACCEPTED', array $overrides = [], string $auth_token = 'auth-token-1' ): void {
		$this->willRespondWith(
			array_merge(
				[
					'order_id'                  => 'klarna-order-123',
					'fraud_status'              => $fraud_status,
					'authorized_payment_method' => [ 'type' => 'invoice' ],
				],
				$overrides
			),
			200,
			"/authorizations/{$auth_token}/order"
		);
	}

	/** Queues a successful customer token, the token a subscription renews on. */
	protected function willCreateCustomerToken( string $token_id = 'customer-token-1', string $auth_token = 'auth-token-1' ): void {
		$this->willRespondWith(
			[
				'token_id'       => $token_id,
				'payment_method' => [ 'type' => 'invoice' ],
			],
			200,
			"/authorizations/{$auth_token}/customer-token"
		);
	}
}
