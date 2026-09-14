<?php
/**
 * Template file for the page shown while waiting for Klarna to confirm a one-step Express Checkout payment.
 *
 * This template can be overridden by copying it to yourtheme/klarna-payments/kec-one-step-redirect.php.
 *
 * @package WC_Klarna_Payments/Templates
 *
 * @var string $poll_url     The URL to poll for the redirect URL.
 * @var string $fallback_url The URL to continue to if the confirmation does not arrive in time.
 * @var int    $max_attempts The maximum number of times to poll.
 * @var int    $interval     The time to wait between polls, in milliseconds.
 */

defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Confirming your payment', 'klarna-payments-for-woocommerce' ); ?></title>
	<noscript>
		<meta http-equiv="refresh" content="<?php echo absint( ceil( $max_attempts * $interval / 1000 ) ); ?>;url=<?php echo esc_url( $fallback_url ); ?>">
	</noscript>
	<style>
		body {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			margin: 0;
			padding: 1.5em;
			box-sizing: border-box;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			font-size: 1rem;
			line-height: 1.5;
			text-align: center;
		}
	</style>
</head>
<body>
	<p><?php esc_html_e( 'Please wait while we confirm your payment with Klarna. Do not close this window.', 'klarna-payments-for-woocommerce' ); ?></p>
	<p><a href="<?php echo esc_url( $fallback_url ); ?>"><?php esc_html_e( 'Continue to your order', 'klarna-payments-for-woocommerce' ); ?></a></p>
	<script>
		( function () {
			var pollUrl = <?php echo wp_json_encode( $poll_url ); ?>;
			var fallbackUrl = <?php echo wp_json_encode( $fallback_url ); ?>;
			var maxAttempts = <?php echo wp_json_encode( $max_attempts ); ?>;
			var interval = <?php echo wp_json_encode( $interval ); ?>;
			var attempts = 0;

			function poll() {
				if ( ++attempts > maxAttempts ) {
					window.location.replace( fallbackUrl );
					return;
				}

				fetch( pollUrl, { credentials: 'same-origin' } )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( result ) {
						var redirectUrl = result && result.data && result.data.redirect_url;

						if ( redirectUrl ) {
							window.location.replace( redirectUrl );
							return;
						}

						window.setTimeout( poll, interval );
					} )
					.catch( function () {
						window.setTimeout( poll, interval );
					} );
			}

			window.setTimeout( poll, interval );
		} )();
	</script>
</body>
</html>
