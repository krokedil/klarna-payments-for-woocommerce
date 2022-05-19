<?php
/**
 * Admin View: Page - Status Report.
 *
 * @package WC_Klarna_Payments\Includes\Admin\Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="wc_status_table widefat" cellspacing="0">
	<thead>
	<tr>
		<th colspan="6" data-export-label="Klarna Payments Request Log">
			<h2><?php esc_html_e( 'Klarna Payments', 'klarna-payments-for-woocommerce' ); ?><?php echo wc_help_tip( esc_html__( 'Klarna Payments System Status.', 'klarna-payments-for-woocommerce' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></h2>
		</th>
	</tr>
	<?php
	$db_logs = get_option( 'krokedil_debuglog_kp', array() );
	if ( ! empty( $db_logs ) ) {
		$db_logs = array_reverse( json_decode( $db_logs, true ) );
		?>
			<tr>
				<td ><strong><?php esc_html_e( 'Time', 'klarna-payments-for-woocommerce' ); ?></strong></td>
				<td class="help"></td>
				<td ><strong><?php esc_html_e( 'Request', 'klarna-payments-for-woocommerce' ); ?></strong></td>
				<td ><strong><?php esc_html_e( 'Response Code', 'klarna-payments-for-woocommerce' ); ?></strong></td>
				<td ><strong><?php esc_html_e( 'Response Message', 'klarna-payments-for-woocommerce' ); ?></strong></td>
				<td ><strong><?php esc_html_e( 'Correlation ID', 'klarna-payments-for-woocommerce' ); ?></strong></td>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $db_logs as $log ) {
			$timestamp      = isset( $log['timestamp'] ) ? $log['timestamp'] : '';
			$log_title      = isset( $log['title'] ) ? $log['title'] : '';
			$code           = isset( $log['response']['code'] ) ? $log['response']['code'] : '';
			$body           = isset( $log['response']['body'] ) ? wp_json_encode( $log['response']['body'] ) : '';
			$error_code     = isset( $log['response']['body']['error_code'] ) ? 'Error code: ' . $log['response']['body']['error_code'] . '.' : '';
			$error_messages = isset( $log['response']['body']['error_messages'] ) ? 'Error messages: ' . wp_json_encode( $log['response']['body']['error_messages'] ) : '';
			$correlation_id = isset( $log['response']['body']['correlation_id'] ) ? $log['response']['body']['correlation_id'] : '';

			?>
			<tr>
				<td><?php echo esc_html( $timestamp ); ?></td>
				<td class="help"></td>
				<td><?php echo esc_html( $log_title ); ?><span style="display: none;">, Response code: <?php echo esc_html( $code ); ?>, Response message: <?php echo esc_html( $body ); ?>, Correlation ID: <?php echo esc_html( $correlation_id ); ?></span</td>
				<td><?php echo esc_html( $code ); ?></td>
				<td><?php echo esc_html( $error_code ) . ' ' . esc_html( $error_messages ); ?></td>
				<td><?php echo esc_html( $correlation_id ); ?></td>
			</tr>
			<?php
		}
	} else {
		?>
		</thead>
		<tbody>
			<tr>
				<td colspan="6" data-export-label="No Klarna Payment errors"><?php esc_html_e( 'No error logs', 'klarna-payments-for-woocommerce' ); ?></td>
			</tr>
		<?php
	}
	?>
	</tbody>
</table>
<?php
	$list_of_countries      = array();
	$test_list_of_countries = array();

foreach ( get_option( 'woocommerce_klarna_payments_settings', array() ) as $key => $value ) {
	if ( '' !== $value ) {
		if ( preg_match( '/test_merchant_id/i', $key ) ) {
			array_push( $test_list_of_countries, ( strtoupper( substr( $key, -2 ) ) ) );
		} elseif ( preg_match( '/merchant_id/i', $key ) ) {
			array_push( $list_of_countries, ( strtoupper( substr( $key, -2 ) ) ) );
		}
	}
}
$live_countries = esc_html( 'No countries selected' );
$test_countries = esc_html( 'No countries selected' );
if ( ( isset( $list_of_countries ) ) && ( count( $list_of_countries ) > 0 ) ) {
	$live_countries = ( implode( ' ', $list_of_countries ) );
}
if ( ( isset( $test_list_of_countries ) ) && ( count( $test_list_of_countries ) > 0 ) ) {
	$test_countries = ( implode( ' ', $test_list_of_countries ) );
}

?>
<table class="wc_status_table widefat" autofocus>
	<thead>
		<tr>
			<th colspan="6" data-export-label="Klarna Countries">
				<h2><?php esc_html_e( 'Klarna Payments Countries', 'klarna-payments-for-woocommerce' ); ?><?php echo wc_help_tip( esc_html__( 'Klarna Payments Countries System Status.', 'klarna-payments-for-woocommerce' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></h2>
			</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><strong><?php esc_html_e( 'Production Countries', 'klarna-payments-for-woocommerce' ); ?></strong></td>
			<td><span style="display: none;"><?php echo esc_html( $live_countries ); ?></span></td>
			<td><?php echo esc_html( $live_countries ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Test Countries', 'klarna-payments-for-woocommerce' ); ?></strong></td>
			<td><span style="display: none;"><?php echo esc_html( $test_countries ); ?></span></td>
			<td><?php echo esc_html( $test_countries ); ?></td>
		</tr>
	</tbody>
</table>
