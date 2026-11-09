<?php
/**
 * Admin View: Page - Status Report.
 *
 * @package WC_Klarna_Payments\Includes\Admin\Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kp_live_countries = array();
$kp_test_countries = array();

foreach ( get_option( 'woocommerce_klarna_payments_settings', array() ) as $kp_key => $kp_value ) {
	if ( '' === $kp_value ) {
		continue;
	}

	if ( preg_match( '/test_merchant_id/i', $kp_key ) ) {
		$kp_test_countries[] = strtoupper( substr( $kp_key, -2 ) );
	} elseif ( preg_match( '/merchant_id/i', $kp_key ) ) {
		$kp_live_countries[] = strtoupper( substr( $kp_key, -2 ) );
	}
}

/* translators: [merchant-facing]. */
$kp_no_countries = __( 'No countries configured', 'klarna-payments-for-woocommerce' );
$kp_rows         = array(
	/* translators: [merchant-facing]. */
	__( 'Production countries', 'klarna-payments-for-woocommerce' ) => empty( $kp_live_countries ) ? $kp_no_countries : implode( ' ', $kp_live_countries ),
	/* translators: [merchant-facing]. */
	__( 'Test countries', 'klarna-payments-for-woocommerce' )       => empty( $kp_test_countries ) ? $kp_no_countries : implode( ' ', $kp_test_countries ),
);
?>
<table class="wc_status_table widefat" cellspacing="0">
	<thead>
		<tr>
			<th colspan="3" data-export-label="Klarna for WooCommerce credentials">
				<?php /* translators: [merchant-facing]. */ ?>
				<h2><?php esc_html_e( 'Klarna for WooCommerce credentials', 'klarna-payments-for-woocommerce' ); ?><?php echo wp_kses_post( wc_help_tip( /* translators: [merchant-facing]. */ esc_html__( 'The countries that have a Klarna merchant ID configured.', 'klarna-payments-for-woocommerce' ) ) ); ?></h2>
			</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $kp_rows as $kp_label => $kp_countries ) : ?>
			<tr>
				<td data-export-label="<?php echo esc_attr( $kp_label ); ?>"><?php echo esc_html( $kp_label ); ?>:</td>
				<td class="help"></td>
				<td><?php echo esc_html( $kp_countries ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
