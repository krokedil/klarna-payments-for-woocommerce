<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

/**
 * Stands in for `WC_Subscription`: a real `WC_Order` plus the things KP calls.
 */
class SubscriptionOrder extends \WC_Order {

	/** The order type WooCommerce Subscriptions registers. */
	public function get_type() {
		return 'shop_subscription';
	}

	/** The statuses WooCommerce Subscriptions registers, without which `set_status()` falls back to 'pending'. */
	protected function get_valid_statuses() {
		return array_merge(
			parent::get_valid_statuses(),
			[ 'wc-active', 'wc-on-hold', 'wc-cancelled', 'wc-expired', 'wc-pending-cancel' ]
		);
	}

	/** Reactivates the subscription rather than completing it, the way `WC_Subscription` does. */
	public function payment_complete( $transaction_id = '' ) {
		$this->set_transaction_id( $transaction_id );
		$this->update_status( 'active' );

		return true;
	}

	/** The order the subscription was bought on. */
	public function get_parent() {
		$parent_id = $this->get_parent_id();

		return $parent_id ? wc_get_order( $parent_id ) : false;
	}

	/** Marks a renewal attempt as failed. */
	public function payment_failed( $new_status = 'failed' ) {
		$this->update_status( $new_status );
	}
}
