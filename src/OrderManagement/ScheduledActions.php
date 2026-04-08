<?php
namespace Krokedil\Klarna\OrderManagement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ScheduledActions class.
 *
 * Displays scheduled actions related to the order.
 */
class ScheduledActions {

	/**
	 * Gets the scheduled actions for the order.
	 *
	 * @param string $session_id The session ID.
	 * @param string $order_created_date The order creation date.
	 * @return array
	 */
	public static function get_scheduled_actions( $session_id, $order_created_date ) {
		$statuses          = array( 'complete', 'failed', 'pending' );
		$scheduled_actions = array(
			'complete' => array(),
			'failed'   => array(),
			'pending'  => array(),
		);

		$order_created_timestamp = strtotime( $order_created_date );
		$three_months_ago        = strtotime( '-3 months' );

		if ( $order_created_timestamp >= $three_months_ago ) {
			foreach ( $statuses as $status ) {
				$scheduled_actions[ $status ] = as_get_scheduled_actions(
					array(
						'search'       => $session_id,
						'status'       => array( $status ),
						'per_page'     => -1,
						'hook'         => 'kp_wc_authorization',
						'group'        => 'klarna_authorization',
						'date'         => $order_created_date,
						'date_compare' => '>=',
					),
					'ids'
				);
			}
		}

		return $scheduled_actions;
	}
}
