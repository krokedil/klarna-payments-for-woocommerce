if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('wp', function () {
    // Kontrollera om query string "show_packages" finns i URL:en och om användaren är administratör
    if (is_user_logged_in() && current_user_can('manage_options') && isset($_GET['show_packages'])) {
        add_action('wp_footer', function () {
            // Hämta och visa paketinformation
            $file_path = plugin_dir_path(__FILE__) . '../composer-dependencies.json';
            if (file_exists($file_path)) {
                $composer_data = json_decode(file_get_contents($file_path), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo '<div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">';
                    echo '<h3>Composer Packages</h3>';
                    echo '<ul>';
                    foreach ($composer_data['require'] as $package => $version) {
                        echo '<li><strong>' . esc_html($package) . ':</strong> ' . esc_html($version) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div style="color: red;">Error: Could not parse composer-dependencies.json.</div>';
                }
            } else {
                echo '<div style="color: red;">Error: composer-dependencies.json not found.</div>';
            }
        });
    }
});