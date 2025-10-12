<?php
/**
 * Plugin Name: Mock Plugin for WP GitHub Updater Tests
 * Plugin URI: https://github.com/SilverAssist/wp-github-updater
 * Description: A mock WordPress plugin used for testing the WP GitHub Updater package
 * Version: 1.0.0
 * Author: SilverAssist
 * Author URI: https://github.com/SilverAssist
 * License: MIT
 * Text Domain: mock-plugin
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * This is a test fixture plugin that demonstrates integration with
 * the SilverAssist/WpGithubUpdater package for automated testing.
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit;
}

// Autoload Composer dependencies
if (file_exists(__DIR__ . "/../../../vendor/autoload.php")) {
    require_once __DIR__ . "/../../../vendor/autoload.php";
}

use SilverAssist\WpGithubUpdater\UpdaterConfig;
use SilverAssist\WpGithubUpdater\Updater;

/**
 * Initialize the GitHub Updater for this mock plugin
 *
 * This function demonstrates the recommended integration pattern
 * for the WP GitHub Updater package.
 */
function mock_plugin_init_updater(): void
{
    // Create updater configuration
    $config = new UpdaterConfig(
        __FILE__,                           // Plugin main file
        "SilverAssist/mock-test-repo",      // GitHub repository
        [
            // Optional: Override plugin metadata
            "plugin_name" => "Mock Plugin for Testing",
            "plugin_description" => "A mock plugin for WP GitHub Updater tests",
            "plugin_author" => "SilverAssist",
            
            // Optional: Custom cache duration (default: 12 hours)
            "cache_duration" => 300, // 5 minutes for testing
            
            // Optional: Custom text domain for translations
            "text_domain" => "mock-plugin",
            
            // Optional: Custom temporary directory for downloads
            "custom_temp_dir" => WP_CONTENT_DIR . "/uploads/temp",
            
            // Optional: Custom AJAX action names
            "ajax_action" => "mock_plugin_check_version",
            "ajax_nonce" => "mock_plugin_nonce",
            
            // Optional: Custom asset pattern for GitHub releases
            "asset_pattern" => "mock-plugin-{version}.zip",
            
            // Optional: WordPress and PHP requirements
            "requires_wp" => "6.0",
            "requires_php" => "8.2",
            "last_updated" => \gmdate("Y-m-d H:i:s"),
        ]
    );
    
    // Initialize the updater
    $updater = new Updater($config);
    
    // Store in global scope for testing access
    $GLOBALS["mock_plugin_updater"] = $updater;
}

// Initialize on plugins_loaded hook
add_action("plugins_loaded", "mock_plugin_init_updater");

/**
 * Add admin menu for testing
 */
function mock_plugin_admin_menu(): void
{
    add_menu_page(
        "Mock Plugin",
        "Mock Plugin",
        "manage_options",
        "mock-plugin",
        "mock_plugin_admin_page",
        "dashicons-admin-plugins",
        100
    );
}
add_action("admin_menu", "mock_plugin_admin_menu");

/**
 * Admin page for testing
 */
function mock_plugin_admin_page(): void
{
    if (!current_user_can("manage_options")) {
        return;
    }
    
    $updater = $GLOBALS["mock_plugin_updater"] ?? null;
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="card">
            <h2>Plugin Information</h2>
            <table class="form-table">
                <tr>
                    <th>Current Version:</th>
                    <td><?php echo esc_html($updater ? $updater->getCurrentVersion() : "N/A"); ?></td>
                </tr>
                <tr>
                    <th>GitHub Repository:</th>
                    <td><?php echo esc_html($updater ? $updater->getGithubRepo() : "N/A"); ?></td>
                </tr>
                <tr>
                    <th>Update Available:</th>
                    <td><?php echo $updater && $updater->isUpdateAvailable() ? "✅ Yes" : "❌ No"; ?></td>
                </tr>
                <?php if ($updater && $updater->isUpdateAvailable()): ?>
                <tr>
                    <th>Latest Version:</th>
                    <td><?php echo esc_html($updater->getLatestVersion()); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="card">
            <h2>Manual Update Check</h2>
            <p>Click the button below to manually check for updates:</p>
            <button type="button" class="button button-primary" id="mock-plugin-check-update">
                Check for Updates
            </button>
            <div id="mock-plugin-result" style="margin-top: 10px;"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $("#mock-plugin-check-update").on("click", function() {
            var button = $(this);
            var result = $("#mock-plugin-result");
            
            button.prop("disabled", true).text("Checking...");
            result.html("");
            
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mock_plugin_check_version",
                    nonce: "<?php echo wp_create_nonce("mock_plugin_nonce"); ?>"
                },
                success: function(response) {
                    if (response.success) {
                        result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    result.html('<div class="notice notice-error"><p>Error checking for updates</p></div>');
                },
                complete: function() {
                    button.prop("disabled", false).text("Check for Updates");
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Activation hook
 */
function mock_plugin_activate(): void
{
    // Clear any existing update caches
    delete_transient(basename(dirname(__FILE__)) . "_version_check");
}
register_activation_hook(__FILE__, "mock_plugin_activate");

/**
 * Deactivation hook
 */
function mock_plugin_deactivate(): void
{
    // Clean up transients
    delete_transient(basename(dirname(__FILE__)) . "_version_check");
}
register_deactivation_hook(__FILE__, "mock_plugin_deactivate");

/**
 * Helper function to get the updater instance (for testing)
 *
 * @return Updater|null
 */
function mock_plugin_get_updater(): ?Updater
{
    return $GLOBALS["mock_plugin_updater"] ?? null;
}
