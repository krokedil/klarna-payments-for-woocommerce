<?php
/**
 * Klarna IFrame options class file.
 *
 * @package KP_IFrame/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		'background',
		'color_button',
		'color_button_text',
		'color_checkbox',
		'color_checkbox_checkmark',
		'color_header',
		'color_link',
		'color_border',
		'color_border_selected',
		'color_text',
		'color_details',
		'color_text_secondary',
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
			if ( array_key_exists( 'background', $this->kp_color_options ) ) {
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
		// only settings with value.
		$kp_settings_filter = array_filter( $this->kp_settings, array( $this, 'has_value' ) );
		foreach ( $kp_settings_filter as $setting_key => $setting_value ) {
			foreach ( $this->kp_color_keys as $color_key ) {
				if ( $setting_key === $color_key ) {
					$this->kp_color_options[ $color_key ] = $setting_value;
				}
			}
		}
	}

	/**
	 * Add <head> CSS for Klarna Payments iframe background.
	 *
	 * @hook wp_head
	 */
	public function iframe_background() {
		echo "<style type='text/css'>div#klarna_container { background:" . esc_html( $this->get_color_option( 'background' ) ) . ' !important; padding: 10px; } div#klarna_container:empty { padding: 0; } </style>';
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
		return $this->get_kp_settings;
	}
}
