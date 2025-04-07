<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieve the installed package version from the changelog.
 *
 * @param string $package_path Directory path of the package.
 * @return string Installed version or 'Unknown' if not found.
 */
function get_installed_package_version( $package_path ) {
	$version         = 'Unknown';
	$changelog_files = array( 'changelog.md', 'CHANGELOG.md' );

	foreach ( $changelog_files as $file ) {
		$changelog_file = trailingslashit( $package_path ) . $file;
		if ( file_exists( $changelog_file ) ) {
			$changelog_content = file_get_contents( $changelog_file );
			if ( preg_match( '/## \[(\d+\.\d+\.\d+)\]/', $changelog_content, $matches ) ) {
				$version = $matches[1];
				break;
			}
		}
	}

	return $version;
}

/**
 * Retrieve the latest package version from a GitHub repository.
 *
 * @param string $repository_url The GitHub API URL for the package.
 * @return string Latest version tag or 'Unknown' if not retrievable.
 */
function get_latest_package_version( $repository_url ) {
	$latest_version = 'Unknown';
	$response       = wp_remote_get( $repository_url );

	if ( is_array( $response ) && ! is_wp_error( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['tag_name'] ) ) {
			$latest_version = $body['tag_name'];
		}
	}

	return $latest_version;
}

/**
 * Display an admin notice with package version details.
 */
function display_krokedil_packages_notice() {
	// Check if the user is logged in, is an admin, and the "show_packages" query parameter is set.
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! isset( $_GET['show_packages'] ) ) {
		return;
	}

	$krokedil_path = plugin_dir_path( __FILE__ ) . '../dependencies/krokedil/';
	$repositories  = array(
		'klarna-express-checkout' => 'https://api.github.com/repos/krokedil/klarna-express-checkout/releases/latest',
		'klarna-onsite-messaging' => 'https://api.github.com/repos/krokedil/klarna-onsite-messaging/releases/latest',
		'settings-page'           => 'https://api.github.com/repos/krokedil/settings-page/releases/latest',
		'wp-api'                  => 'https://api.github.com/repos/krokedil/wp-api/releases/latest',
		'woocommerce'             => 'https://api.github.com/repos/krokedil/woocommerce/releases/latest',
		'sign-in-with-klarna'     => 'https://api.github.com/repos/krokedil/sign-in-with-klarna/releases/latest',
	);

	if ( ! is_dir( $krokedil_path ) ) {
		echo '<div style="color: red;">Error: Krokedil directory not found.</div>';
		return;
	}

	$packages = scandir( $krokedil_path );
	if ( $packages === false ) {
		echo '<div style="color: red;">Error: Could not read Krokedil directory.</div>';
		return;
	}

	$found_packages = false;
	echo '<div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">';
	echo '<h3>Krokedil Packages</h3>';
	echo '<ul>';

	foreach ( $packages as $package ) {
		// Skip current and parent directories.
		if ( $package === '.' || $package === '..' ) {
			continue;
		}

		$found_packages    = true;
		$package_path      = trailingslashit( $krokedil_path ) . $package;
		$installed_version = get_installed_package_version( $package_path );
		$latest_version    = 'Unknown';

		// Get the latest version if the package is in the repository list.
		if ( isset( $repositories[ $package ] ) ) {
			$latest_version = get_latest_package_version( $repositories[ $package ] );
		}

		$version_status = ( $installed_version === $latest_version ) ? 'Up-to-date' : 'Outdated';
		echo '<li><strong>' . esc_html( $package ) . '</strong> - Installed Version: ' . esc_html( $installed_version ) . ' - Latest Version: ' . esc_html( $latest_version ) . ' (' . esc_html( $version_status ) . ')</li>';
	}

	echo '</ul>';
	if ( ! $found_packages ) {
		echo '<p style="color: orange;">No packages found in the Krokedil directory.</p>';
	}
	echo '</div>';
}
add_action( 'admin_notices', 'display_krokedil_packages_notice' );
