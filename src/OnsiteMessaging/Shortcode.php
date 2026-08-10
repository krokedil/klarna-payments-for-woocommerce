<?php
namespace Krokedil\Klarna\OnsiteMessaging;

/**
 * Class for managing the onsite_messaging shortcode.
 */
class Shortcode {

	/**
	 * Register the shortcode, and its function.
	 */
	public function __construct() {
		add_shortcode( 'onsite_messaging', array( $this, 'shortcode_output' ) );
	}

	/**
	 * Print the Klarna placement.
	 *
	 * @param array $atts The attributes for the shortcode.
	 * @return string
	 */
	public function shortcode_output( $atts ) {
		$html = '';
		if ( ! is_admin() ) {
			/**
			 * Triggers when the Klarna on-site messaging shortcode is rendered on the frontend.
			 */
			do_action( 'osm_shortcode_added' );
			$atts = shortcode_atts(
				array(
					'data-key'             => 'homepage-promotion-wide',
					'data-theme'           => '',
					'data-purchase-amount' => '',
				),
				$atts
			);
			ob_start();
			Utility::print_placement( $atts );
			$html = ob_get_clean();
		}

		return $html;
	}
}
