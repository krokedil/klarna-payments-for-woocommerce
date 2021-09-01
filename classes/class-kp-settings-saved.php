<?php
/**
 * File for checking settings on save.
 *
 * @package Klarna_Checkout/Classes
 */

/**
 * Class for checking settings on save.
 */
class KP_Settings_Saved {
	const PROD = 'Production';
	const TEST = 'Test';

	/**
	 * If there was an error detected or not.
	 *
	 * @var boolean
	 */
	private $error = false;

	/**
	 * Error message array.
	 *
	 * @var array
	 */
	private $message = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_update_options_checkout_klarna_payments', array( $this, 'check_api_credentials' ), 10 );
	}

	/**
	 * Clears whitespace from API settings.
	 *
	 * @return void
	 */
	public function check_api_credentials() {
		// Get settings from KCO.
		$options = get_option( 'woocommerce_klarna_payments_settings', array() );

		// If not enabled bail.
		if ( $options && 'yes' !== $options['enabled'] ) {
			return;
		}
		$countries = array(
			'AU' => array(
				'region'   => 'oc',
				'currency' => 'AUD',
			),
			'AT' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'BE' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'CA' => array(
				'region'   => 'na',
				'currency' => 'CAD',
			),
			'DK' => array(
				'region'   => 'eu',
				'currency' => 'DKK',
			),
			'DE' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'FI' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'FR' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'IT' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'NL' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'NO' => array(
				'region'   => 'eu',
				'currency' => 'NOK',
			),
			'NZ' => array(
				'region'   => 'oc',
				'currency' => 'NZD',
			),
			'PL' => array(
				'region'   => 'eu',
				'currency' => 'PLN',
			),
			'SE' => array(
				'region'   => 'eu',
				'currency' => 'SEK',
			),
			'ES' => array(
				'region'   => 'eu',
				'currency' => 'EUR',
			),
			'CH' => array(
				'region'   => 'eu',
				'currency' => 'CHF',
			),
			'UK' => array(
				'region'   => 'eu',
				'currency' => 'GBP',
			),
			'US' => array(
				'region'   => 'na',
				'currency' => 'USD',
			),
		);

		foreach ( $countries as $cc => $country ) {
			$lc_cc = strtolower( $cc );
			$lc_cc = 'uk' === $lc_cc ? 'gb' : $lc_cc;
			// Live.
			if ( '' !== $options[ 'merchant_id_' . $lc_cc ] ) {
				$username = $options[ 'merchant_id_' . $lc_cc ];
				$password = $options[ 'shared_secret_' . $lc_cc ];

				$test_response = ( new KP_Test_Credentials() )->request( $username, $password, false, $country, $lc_cc );
				$this->process_test_response( $test_response, self::PROD, $cc );
			}

			// Test.
			if ( '' !== $options[ 'test_merchant_id_' . $lc_cc ] ) {
				$username = $options[ 'test_merchant_id_' . $lc_cc ];
				$password = $options[ 'test_shared_secret_' . $lc_cc ];

				$test_response = ( new KP_Test_Credentials() )->request( $username, $password, true, $country, $lc_cc );
				$this->process_test_response( $test_response, self::TEST, $cc );
			}

			$this->maybe_handle_error();
		}
	}

	/**
	 * Processes the test response.
	 *
	 * @param array|WP_Error $test_response The response from the test.
	 * @param array          $test The test that was run.
	 * @param string         $cc The county code.
	 * @return void
	 */
	public function process_test_response( $test_response, $test, $cc ) {
		// If this is not a WP Error then its ok.
		if ( ! is_wp_error( $test_response ) ) {
			return;
		}
		$code           = $test_response->get_error_code();
		$error          = json_decode( $test_response->get_error_message(), true );
		$data           = json_decode( $test_response->get_error_data(), true );
		$error_message  = $error['message'];
		$correlation_id = $data['correlation_id'];
		if ( 401 === $code || 403 === $code ) {
			switch ( $code ) {
				case 401:
					$message = "It seems like your Klarna $cc $test credentials are incorrect, please verify or remove these credentials and save again. ";
					break;
				case 403:
					$message = "It seems like your Klarna $cc $test API credentials are not working for the Klarna Payments plugin, please verify your Klarna contract is for the Klarna Payments solution.  If your Klarna contract is for Klarna Checkout, please instead use the <a href='https://docs.woocommerce.com/document/klarna-checkout/'>Klarna Checkout for WooCommerce</a> plugin. ";
					break;
			}
			$message        .= "API error code: $code, Klarna API error message: $error_message, Klarna correlation_id: $correlation_id";
			$this->message[] = $message;
			$this->error     = true;
		}
	}

	/**
	 * Adds a error message if an error was detected.
	 *
	 * @return void
	 */
	public function maybe_handle_error() {
		// Remove any potential error displays if there are no errors detected.
		if ( ! $this->error ) {
			delete_option( 'kp_credentials_error' );
			return;
		}
		update_option( 'kp_credentials_error', $this->message );
	}

	/**
	 * Displays errors if they exists for the credentials check.
	 *
	 * @return void
	 */
	public static function maybe_show_errors() {
		$error_messages = get_option( 'kp_credentials_error' );

		// If plugin file exists.
		if ( $error_messages ) {
			?>
				<div class="kp-message notice notice-error">
				<?php
				foreach ( $error_messages as $error_message ) {
					?>
					<p><?php echo wp_kses_post( $error_message ); ?></p>
				<?php } ?>
				</div>
			<?php
		}
	}
}
new KP_Settings_Saved();
