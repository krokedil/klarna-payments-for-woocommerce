<?php
/**
 * Klarna IFrame options class file.
 *
 * @package WC_Klarna_Payments/Classes/Requests/Helpers
 */

defined( 'ABSPATH' ) || exit;

/**
 * KP_IFrame class.
 */
class KP_IFrame {

	/**
	 * IFrame color keys thats match with keys from database.
	 *
	 * @var array
	 */
	protected $kp_color_keys = array(
		'color_border',
		'color_border_selected',
		'color_text',
		'color_details',
		'radius_border',
	);

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	protected $kp_settings_key = 'woocommerce_klarna_payments_settings';

	/**
	 * IFrame options.
	 *
	 * @var array
	 */
	protected $kp_color_options;

	/**
	 * All kp setting options.
	 *
	 * @var array
	 */
	protected $kp_settings;

	/**
	 * Class constructor
	 *
	 * @param array $kp_settings option.
	 */
	public function __construct( array $kp_settings ) {
		if ( is_checkout() ) {
			$this->kp_settings = $kp_settings;
			$this->init();
			if ( isset( $this->kp_color_options['background'] ) ) {
				add_action( 'wp_head', array( $this, 'iframe_background' ) );
			}
		}
	}
	/**
	 * Init options.
	 *
	 * @return void
	 */
	protected function init() {
		// Only settings with value.
		$kp_settings_filter = array_filter( $this->kp_settings, array( $this, 'has_value' ) );
		foreach ( $kp_settings_filter as $setting_key => $setting_value ) {
			foreach ( $this->kp_color_keys as $color_key ) {
				if ( $setting_key === $color_key ) {
					$this->kp_color_options[ $color_key ] = self::add_hash_to_color( $setting_value );
				}
			}
		}
	}

	/**
	 * Adds hash to color hex.
	 *
	 * @param string $hex Hex color code.
	 * @return string
	 */
	private static function add_hash_to_color( $hex ) {
		if ( '' !== $hex ) {
			$hex = str_replace( '#', '', $hex );
			$hex = '#' . $hex;
		}
		return $hex;
	}


	/**
	 * Test two string for equality
	 *
	 * @param string $value thats need to be tested.
	 * @return boolean
	 */
	public function has_value( $value ) {
		return '' !== $value;
	}

	/**
	 * Get option from array
	 *
	 * @param string $option value.
	 * @return string
	 */
	public function get_color_option( $option ) {
		if ( array_key_exists( $option, $this->kp_color_options ) ) {
			return $this->kp_color_options[ $option ];
		}
		return '';
	}

	/**
	 * Returns kp settings with a value.
	 *
	 * @return array
	 */
	public function get_kp_color_options() {
		return $this->kp_color_options;
	}

	/**
	 * Returns all kp settings
	 *
	 * @return array
	 */
	public function get_kp_settings() {
		return $this->kp_settings;
	}
}
