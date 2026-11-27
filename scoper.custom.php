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
	$config['exclude-classes'][] = 'WC_Klarna_Order_Management';
	$config['exclude-classes'][] = 'KP_Form_Fields';
	$config['exclude-classes'][] = 'KP_Assets';
	$config['exclude-classes'][] = 'KP_Requests';
	$config['exclude-classes'][] = 'KP_Subscription';
	$config['exclude-classes'][] = 'KCO';

	$config['exclude-classes'][] = 'WC_Subscriptions_Cart';
	$config['exclude-classes'][] = 'WC_Subscriptions_Product';

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
	$config['exclude-namespaces'][] = 'Krokedil\Klarna';

	$config['patchers'][] = 'kp_scoper_patch_phpcs_annotations';

	return $config;
}

/**
 * Restores phpcs annotations that php-scoper displaces, and removes code the WooCommerce.com QIT security scan flags.
 *
 * @param string $file_path The path of the scoped file.
 * @param string $prefix    The scoper prefix.
 * @param string $content   The scoped file content.
 * @return string
 */
function kp_scoper_patch_phpcs_annotations( string $file_path, string $prefix, string $content ): string {
	$file_path = str_replace( '\\', '/', $file_path );

	if ( '.php' !== substr( $file_path, -4 ) ) {
		return $content;
	}

	if ( false !== strpos( $file_path, '/krokedil/' ) ) {
		// Only a previous line that ends a statement and has no comment of its own.
		$patched = preg_replace(
			'~^((?:(?!//|/\*)[^\r\n])*;)[ \t]*(\R[ \t]*(//\s*phpcs:ignore[^\r\n]*))$~m',
			'$1 $3$2',
			$content
		);
		$content = null !== $patched ? $patched : $content;
	}

	// The QIT scan reports debug_backtrace() under its own sniff, so name it next to the WordPress one.
	if ( false !== strpos( $file_path, '/krokedil/wp-api/src/Logger.php' ) ) {
		$content = str_replace(
			'\debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions ',
			'\debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions,QITStandard.PHP.DebugCode.DebugFunctionFound ',
			$content
		);
	}

	// The settings page navigation calls wp_verify_nonce() without using the result. It only reads which tab to show.
	if ( false !== strpos( $file_path, '/krokedil/settings-page/src/Navigation.php' ) ) {
		$patched = preg_replace( '~^[ \t]*// Verify the nonce\.\R[ \t]*\\\\?wp_verify_nonce\(\'__nonce\'\);[ \t]*\R~m', '', $content );
		$content = null !== $patched ? $patched : $content;
	}

	// Composer's deprecation notice for getRawData() is not debug code.
	if ( false !== strpos( $file_path, '/composer/InstalledVersions.php' ) ) {
		$patched = preg_replace(
			'~^([ \t]*@\\\\?trigger_error\([^\r\n]*\);)[ \t]*$~m',
			'$1 // phpcs:ignore WordPress.PHP.DevelopmentFunctions,QITStandard.PHP.DebugCode.DebugFunctionFound -- Composer deprecation notice, not debug code.',
			$content
		);
		$content = null !== $patched ? $patched : $content;
	}

	return $content;
}
