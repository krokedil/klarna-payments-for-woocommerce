<?php //phpcs:disable

function customize_php_scoper_config( array $config ): array {
    // Ignore the abspath constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'KOSM_VERSION';
	$config['exclude-classes'][] = 'WooCommerce';
	$config['exclude-classes'][] = 'Klarna_OnSite_Messaging';
	$config['exclude-classes'][] = 'Klarna_OnSite_Messaging_For_WooCommerce';
	$config['exclude-classes'][] = 'WC_Product';
	$config['exclude-functions'][] = 'kp_get_client_id';
	$config['exclude-functions'][] = 'KP_WC';
	$config['exclude-namespaces'][] = 'Automattic';

	return $config;
}
