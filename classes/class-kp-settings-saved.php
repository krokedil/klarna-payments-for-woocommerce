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
		update_option( 'kp_has_valid_credentials', 'no' );
		$eu_countries                     = KP_Form_Fields::available_countries( 'eu' );
		$unavailable_features_credentials = array();

		// If not enabled bail.
		if ( $options && 'yes' !== $options['enabled'] ) {
			return;
		}
		$countries = array_keys( KP_Form_Fields::$kp_form_auto_countries );

		foreach ( $countries as $cc ) {
			$combine_eu_credentials = $options['combine_eu_credentials'] ?? 'no';
			$cc                     = 'yes' === $combine_eu_credentials && isset( $eu_countries[ $cc ] ) ? 'eu' : $cc;
			$cc                     = 'uk' === $cc ? 'gb' : $cc;
			$password               = '';
			$username               = '';

			if ( 'yes' !== $options['testmode'] ) {
				// Live.
				if ( '' !== $options[ 'merchant_id_' . $cc ] ) {
					$username = $options[ 'merchant_id_' . $cc ];
					$password = $options[ 'shared_secret_' . $cc ];

					// Create request arguments.
					$args = array(
						'username' => $username,
						'password' => $password,
						'country'  => $cc,
						'testmode' => false,
					);

					if ( 'eu' !== $cc ) {
						$test_response = ( new KP_Test_Credentials( $args ) )->request();
						$this->process_test_response( $test_response, self::PROD, $cc );
					}
				}
			} elseif ( '' !== $options[ 'test_merchant_id_' . $cc ] ?? '' ) { // Test.
				$username = $options[ 'test_merchant_id_' . $cc ] ?? '';
				$password = $options[ 'test_shared_secret_' . $cc ] ?? '';

				// Create request arguments.
				$args = array(
					'username' => $username,
					'password' => $password,
					'country'  => $cc,
					'testmode' => true,
				);

				if ( 'eu' !== $cc ) {
					$test_response = ( new KP_Test_Credentials( $args ) )->request();
					$this->process_test_response( $test_response, self::TEST, $cc );
				}
			}

			// If the password and username is set and combined EU credentials are enabled, set the valid credentials flag.
			if ( 'eu' === $cc && ! empty( $password ) && ! empty( $username ) ) {

				if ( 'yes' !== get_option( 'kp_has_valid_credentials' ) ?? 'no' ) {
					update_option( 'kp_has_valid_credentials', 'yes' );
				}
			}

			if ( ! empty( $password ) && ! isset( $unavailable_features_credentials[ $cc ] ) ) {
				$unavailable_features_credentials[ $cc ] = array(
					'mode'          => 'yes' === $options['testmode'] ? 'test' : 'live',
					'shared_secret' => $password,
					'country_code'  => $cc,
				);

				$this->maybe_handle_error();
			}
		}

		$unavailable_features = $unavailable_features_credentials ? kp_get_unavailable_feature_ids( $unavailable_features_credentials ) : array();

		// If we have any collected errors, save the unavailable features as a empty array.
		if ( ! empty( $unavailable_features['errors'] ?? array() ) ) {
			update_option( 'kp_unavailable_feature_ids', array() );
		} else {
			update_option( 'kp_unavailable_feature_ids', $unavailable_features['feature_ids'] ?? array() );
		}
	}

	/**
	 * Processes the test response.
	 *
	 * @param array|WP_Error $test_response The response from the test.
	 * @param string         $test The test that was run.
	 * @param string         $cc The county code.
	 * @return void
	 */
	public function process_test_response( $test_response, $test, $cc ) {
		// If this is not a WP Error then its ok.
		if ( ! is_wp_error( $test_response ) ) {
			// Set the valid credentials flag as there is at least one valid set of credentials.
			if ( 'yes' !== get_option( 'kp_has_valid_credentials' ) ?? 'no' ) {
				update_option( 'kp_has_valid_credentials', 'yes' );
			}
			return;
		}
		$cc    = strtoupper( $cc );
		$code  = $test_response->get_error_code();
		$error = $test_response->get_error_message();
		$data  = json_decode( $test_response->get_error_data() ?? '', true );

		if ( 400 === $code || 401 === $code || 403 === $code ) {
			switch ( $code ) {
				case 400:
					$message = "It seems like your Klarna $cc $test credentials are not configured correctly, please review your Klarna contract and ensure that your account is configured correctly for this country. ";
					break;
				case 401:
					$message = "Your Klarna $cc $test credentials are not authorized. Please verify the credentials and environment (production or test mode) or remove these credentials and save again. API credentials only work in either production or test, not both environments. ";
					break;
				case 403:
					$message = "It seems like your Klarna $cc $test API credentials are not working for the Klarna Payments plugin, please verify your Klarna contract is for the Klarna Payments solution.  If your Klarna contract is for Klarna Checkout, please instead use the <a href='https://docs.woocommerce.com/document/klarna-checkout/'>Klarna Checkout for WooCommerce</a> plugin. ";
					break;
			}
			$message .= "API error code: $code, Klarna API error message: $error";

			if ( isset( $data['correlation_id'] ) ) {
				$correlation_id = $data['correlation_id'];
				$message       .= " Klarna correlation_id: $correlation_id";
			}

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
