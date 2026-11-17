<?php
namespace Krokedil\Klarna\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the integration metadata header Klarna uses to tag API traffic by integration and platform version.
 */
class IntegrationMetadataUtility {
	/**
	 * The name of the header the metadata is sent in.
	 */
	const HEADER_NAME = 'X-Klarna-Integration-Metadata';

	/**
	 * Get the integration metadata.
	 *
	 * @param string|null $session_reference The Klarna session id, if the request belongs to a session.
	 *
	 * @return array
	 */
	public static function get_metadata( $session_reference = null ) {
		$integrator = array(
			'name'           => 'KLARNA_PLUGIN',
			'module_name'    => 'KLARNA_FOR_WOOCOMMERCE',
			'module_version' => WC_KLARNA_PAYMENTS_VERSION,
		);

		if ( is_string( $session_reference ) && '' !== $session_reference ) {
			$integrator['session_reference'] = $session_reference;
		}

		$originators = array(
			self::get_originator( 'WOOCOMMERCE', function_exists( 'WC' ) ? WC()->version : '' ),
			self::get_originator( 'WORDPRESS', get_bloginfo( 'version' ) ),
			self::get_originator( 'PHP', phpversion() ),
		);

		return array(
			'integrator'  => $integrator,
			'originators' => array_values(
				array_filter(
					$originators,
					function ( $originator ) {
						return '' !== $originator['module_version'];
					}
				)
			),
		);
	}

	/**
	 * Get the integration metadata as a JSON encoded header value.
	 *
	 * @param string|null $session_reference The Klarna session id, if the request belongs to a session.
	 *
	 * @return string
	 */
	public static function get_header_value( $session_reference = null ) {
		return wp_json_encode( self::get_metadata( $session_reference ) );
	}

	/**
	 * Get a single originator entry.
	 *
	 * @param string      $name    The platform name, which is also used as the module name.
	 * @param string|bool $version The platform version.
	 *
	 * @return array
	 */
	private static function get_originator( $name, $version ) {
		return array(
			'name'           => $name,
			'module_name'    => $name,
			'module_version' => is_string( $version ) ? $version : '',
		);
	}
}
