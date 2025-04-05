<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action(
	'wp',
	function () {
		// Kontrollera om query string "show_packages" finns i URL:en och om användaren är administratör
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) && isset( $_GET['show_packages'] ) ) {
			add_action(
				'wp_footer',
				function () {
					// Hämta och visa paketinformation från dependencies-mappen
					$dependencies_path = plugin_dir_path( __FILE__ ) . '../dependencies/';
					if ( is_dir( $dependencies_path ) ) {
						$packages = scandir( $dependencies_path );
						if ( $packages !== false ) {
							$found_packages = false; // Flagga för att kontrollera om några paket hittas
							echo '<div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">';
							echo '<h3>Composer Packages</h3>';
							echo '<ul>';
							foreach ( $packages as $package ) {
								if ( $package !== '.' && $package !== '..' ) {
									$found_packages = true; // Paket hittades
									$package_path   = $dependencies_path . $package;
									$version        = 'Unknown';

									// Kontrollera om changelog.md finns i paketmappen
									$changelog_file = $package_path . '/changelog.md';
									if ( file_exists( $changelog_file ) ) {
										$changelog_content = file_get_contents( $changelog_file );
										if ( preg_match( '/## \[(\d+\.\d+\.\d+)\]/', $changelog_content, $matches ) ) {
											$version = $matches[1]; // Hämta första matchande version
										}
									}

									echo '<li><strong>' . esc_html( $package ) . '</strong> - Version: ' . esc_html( $version ) . '</li>';
								}
							}
							echo '</ul>';
							if ( ! $found_packages ) {
								echo '<p style="color: orange;">No packages found in the dependencies directory.</p>';
							}
							echo '</div>';
						} else {
							echo '<div style="color: red;">Error: Could not read dependencies directory.</div>';
						}
					} else {
						echo '<div style="color: red;">Error: dependencies directory not found.</div>';
					}

					// Hämta och visa paketinformation från krokedil-mappen
					$krokedil_path = plugin_dir_path( __FILE__ ) . '../dependencies/krokedil/';
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

									// Kontrollera om changelog.md finns i paketmappen
									$changelog_file = $package_path . '/changelog.md';
									if ( file_exists( $changelog_file ) ) {
										$changelog_content = file_get_contents( $changelog_file );
										if ( preg_match( '/## \[(\d+\.\d+\.\d+)\]/', $changelog_content, $matches ) ) {
											$version = $matches[1]; // Hämta första matchande version
										}
									}

									echo '<li><strong>' . esc_html( $package ) . '</strong> - Version: ' . esc_html( $version ) . '</li>';
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
			);
		}
	}
);
