<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action(
	'admin_notices',
	function () {
		// Kontrollera om query string "show_packages" finns i URL:en och om användaren är administratör
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) && isset( $_GET['show_packages'] ) ) {
			// Hämta och visa paketinformation från krokedil-mappen
			$krokedil_path = plugin_dir_path( __FILE__ ) . '../dependencies/krokedil/';
			$repositories  = array(
				'klarna-express-checkout' => 'https://api.github.com/repos/krokedil/klarna-express-checkout/releases/latest',
				'klarna-onsite-messaging' => 'https://api.github.com/repos/krokedil/klarna-onsite-messaging/releases/latest',
				'settings-page'           => 'https://api.github.com/repos/krokedil/settings-page/releases/latest',
				'wp-api'                  => 'https://api.github.com/repos/krokedil/wp-api/releases/latest',
				'woocommerce'             => 'https://api.github.com/repos/krokedil/woocommerce/releases/latest',
				'sign-in-with-klarna'     => 'https://api.github.com/repos/krokedil/sign-in-with-klarna/releases/latest',
			);

			if ( is_dir( $krokedil_path ) ) {
				$packages = scandir( $krokedil_path );
				if ( $packages !== false ) {
					$found_packages = false; // Flagga för att kontrollera om några paket hittas
					echo '<div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">';
					echo '<h3>Krokedil Packages</h3>';
					echo '<ul>';
					foreach ( $packages as $package ) {
						if ( $package !== '.' && $package !== '..' ) {
							$found_packages = true; // Paket hittades
							$package_path   = $krokedil_path . $package;
							$version        = 'Unknown';

							// Kontrollera om changelog.md eller CHANGELOG.md finns i paketmappen
							$changelog_file_lower = $package_path . '/changelog.md';
							$changelog_file_upper = $package_path . '/CHANGELOG.md';
							$changelog_file       = null;

							if ( file_exists( $changelog_file_lower ) ) {
								$changelog_file = $changelog_file_lower;
							} elseif ( file_exists( $changelog_file_upper ) ) {
								$changelog_file = $changelog_file_upper;
							}

							if ( $changelog_file ) {
								$changelog_content = file_get_contents( $changelog_file );
								if ( preg_match( '/## \[(\d+\.\d+\.\d+)\]/', $changelog_content, $matches ) ) {
									$version = $matches[1]; // Hämta första matchande version
								}
							}

							// Hämta senaste versionen från GitHub
							$latest_version = 'Unknown';
							if ( isset( $repositories[ $package ] ) ) {
								$response = wp_remote_get( $repositories[ $package ] );
								if ( is_array( $response ) && ! is_wp_error( $response ) ) {
									$body = json_decode( wp_remote_retrieve_body( $response ), true );
									if ( isset( $body['tag_name'] ) ) {
										$latest_version = $body['tag_name'];
									}
								}
							}

							// Jämför versioner
							$version_status = ( $version === $latest_version ) ? 'Up-to-date' : 'Outdated';

							echo '<li><strong>' . esc_html( $package ) . '</strong> - Installed Version: ' . esc_html( $version ) . ' - Latest Version: ' . esc_html( $latest_version ) . ' (' . esc_html( $version_status ) . ')</li>';
						}
					}
					echo '</ul>';
					if ( ! $found_packages ) {
						echo '<p style="color: orange;">No packages found in the krokedil directory.</p>';
					}
					echo '</div>';
				} else {
					echo '<div style="color: red;">Error: Could not read krokedil directory.</div>';
				}
			} else {
				echo '<div style="color: red;">Error: krokedil directory not found.</div>';
			}
		}
	}
);
