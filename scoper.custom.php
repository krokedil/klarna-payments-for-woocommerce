<?php //phpcs:disable

function customize_php_scoper_config( array $config ): array {
    // Ignore the abspath constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'KOSM_VERSION';
	$config['exclude-constants'][] = 'SIWK_VERSION';
	$config['exclude-classes'][] = 'WooCommerce';
	$config['exclude-classes'][] = 'Klarna_OnSite_Messaging';
	$config['exclude-classes'][] = 'Klarna_OnSite_Messaging_For_WooCommerce';
	$config['exclude-classes'][] = 'WC_Product';
	$config['exclude-classes'][] = 'KP_Form_Fields';

	$functions = array(
		'KP_WC',
		'kp_unset_session_values',
		'kp_extract_error_message',
		'get_klarna_customer',
		'kp_get_klarna_country',
		'kp_save_order_meta_data',
		'kp_process_accepted',
		'kp_process_pending',
		'kp_process_rejected',
		'kp_get_locale',
		'kp_print_error_message',
		'kp_is_available',
		'kp_is_checkout_blocks_page',
		'kp_is_checkout_page',
		'kp_is_order_pay_page',
		'kp_is_wc_blocks_order',
		'kp_get_client_id',
		'kp_get_client_id_by_currency',
		'kp_is_country_available'
	);

	$config['exclude-functions'] = array_merge( $config['exclude-functions'] ?? array(), $functions );
	$config['exclude-namespaces'][] = 'Automattic';

	return $config;
}
