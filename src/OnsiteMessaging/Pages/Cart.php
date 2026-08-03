<?php
namespace Krokedil\Klarna\OnsiteMessaging\Pages;

use Krokedil\Klarna\OnsiteMessaging\Settings;

/**
 * Class for handling and displaying the placement on the Cart page.
 */
class Cart extends Page {

	/**
	 * The setting keys for the Product.
	 *
	 * @var array
	 */
	protected $properties = array(
		'theme'        => 'onsite_messaging_theme_cart',
		'key'          => 'placement_data_key_cart',
		'client_id'    => 'data_client_id',
		'placement_id' => 'placement_data_key_cart',
		'target'       => 'onsite_messaging_cart_location',
	);

	/**
	 * Class constructor.
	 *
	 * @param Settings $settings The KOSM settings.
	 */
	public function __construct( $settings ) {
		parent::__construct( $settings, $this->properties );

		// The location of the placement on the product page.
		$this->priority = 5;

		add_action( 'wp_head', array( $this, 'register_placement' ) );
		add_action( 'wc_ajax_kosm_get_cart_total', array( $this, 'get_cart_total' ) );
	}

	/**
	 * Register hook for displaying the placement.
	 *
	 * @return void
	 */
	public function register_placement() {
		if ( $this->enabled && is_cart() ) {

			$target   = apply_filters( 'klarna_onsite_messaging_cart_target', $this->target );
			$priority = apply_filters( 'klarna_onsite_messaging_cart_priority', $this->priority );
			add_action( $target, array( $this, 'display_placement' ), $priority );

			add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'add_cart_total_input' ) );
		}
	}
	/**
	 * Retrieve the cart total amount.
	 *
	 * @return void
	 */
	public function get_cart_total() {
		if ( ! isset( WC()->cart ) ) {
			wp_send_json_error( 'no_cart' );
		}

		wp_send_json_success( WC()->cart->total );
	}

	/**
	 * Add a hidden input field with the cart totals.
	 *
	 * @return void
	 */
	public function add_cart_total_input() {
		?>
			<input type="hidden" id="kosm_cart_total" name="kosm_cart_total" value="<?php echo esc_html( WC()->cart->get_total( 'kosm' ) ); ?>">
		<?php
	}
}
