<?php
namespace Krokedil\Klarna\OnsiteMessaging;

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing the onsite_messaging shortcode.
 */
class Shortcode {
	/**
	 * Instance of the main KlarnaOnsiteMessaging class.
	 *
	 * @var KlarnaOnsiteMessaging
	 */
	private $onsite_messaging;

	/**
	 * Register the shortcode, and its function.
	 *
	 * @param KlarnaOnsiteMessaging $onsite_messaging Instance of the main KlarnaOnsiteMessaging class.
	 */
	public function __construct( $onsite_messaging ) {
		$this->onsite_messaging = $onsite_messaging;
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
			// The shortcode can be rendered outside the post content (page builders, headers, templates), so enqueue the script on render.
			$this->onsite_messaging->enqueue_scripts( true );

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
