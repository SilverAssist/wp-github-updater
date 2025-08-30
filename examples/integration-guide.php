<?php
/**
 * Example implementation for your existing plugins
 * 
 * This file shows how to integrate the wp-github-updater package
 * into your existing WordPress plugins.
 */

// This is how you would modify your existing plugins to use the new package

/**
 * For Silver Assist Security Essentials Plugin
 * Replace the existing Updater.php with this implementation:
 */

/*
// In your main plugin file, replace the existing updater initialization with:

require_once __DIR__ . '/vendor/autoload.php';

use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

// Initialize updater (replaces your existing Updater class instantiation)
add_action("init", function() {
    $config = new UpdaterConfig(
        __FILE__, // Path to your main plugin file
        "SilverAssist/silver-assist-security", // Your GitHub repo
        [
            "asset_pattern" => "silver-assist-security-v{version}.zip",
            "ajax_action" => "silver_assist_security_check_version",
            "ajax_nonce" => "silver_assist_security_ajax",
            "text_domain" => "silver-assist-security", // Your plugin's text domain
            "custom_temp_dir" => WP_CONTENT_DIR . "/temp", // Enhanced error handling (v1.1.3+)
        ]
    );
    
    $updater = new Updater($config);
    
    // Optional: Programmatic version checking (v1.1.2+)
    if ($updater->isUpdateAvailable()) {
        // Handle update availability programmatically
        $latestVersion = $updater->getLatestVersion();
        error_log("Security plugin update available: " . $latestVersion);
    }
});
*/

/**
 * For LeadGen App Form Plugin
 * Replace the existing LeadGenAppFormUpdater.php with this:
 */

/*
// In your main plugin file, replace the existing updater with:

require_once __DIR__ . '/vendor/autoload.php';

use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

add_action("init", function() {
    $config = new UpdaterConfig(
        __FILE__,
        "your-username/leadgen-app-form", // Your GitHub repo
        [
            "asset_pattern" => "leadgen-app-form-v{version}.zip",
            "ajax_action" => "leadgen_check_version",
            "ajax_nonce" => "leadgen_version_check",
            "text_domain" => "leadgen-app-form", // Your plugin's text domain
            "custom_temp_dir" => wp_upload_dir()["basedir"] . "/temp", // Alternative temp dir location
        ]
    );
    
    $updater = new Updater($config);
    
    // Optional: Add manual check button in admin
    add_action("admin_init", function() use ($updater) {
        if (isset($_GET["leadgen_check_update"]) && current_user_can("update_plugins")) {
            $updater->manualVersionCheck();
        }
    });
});
*/

/**
 * For any new plugin, the implementation is simple:
 */

/*
<?php
// Main plugin file header
// Plugin Name: My New Plugin
// Description: My plugin description
// Version: 1.0.0
// etc...

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

// Initialize the updater
add_action("init", function() {
    $config = new UpdaterConfig(
        __FILE__,
        "your-username/my-new-plugin", // GitHub repository
        [
            // Optional customizations
            "asset_pattern" => "my-plugin-{version}.zip",
            "requires_php" => "8.1",
            "requires_wordpress" => "6.2",
            "ajax_action" => "my_plugin_version_check",
            "cache_duration" => 6 * 3600, // 6 hours
            "text_domain" => "my-new-plugin", // Your plugin's text domain
            "custom_temp_dir" => WP_CONTENT_DIR . "/temp", // Improved hosting compatibility
        ]
    );
    
    $updater = new Updater($config);
    
    // Example: Check for updates programmatically
    add_action("admin_notices", function() use ($updater) {
        if (!current_user_can("update_plugins")) return;
        
        if ($updater->isUpdateAvailable()) {
            $currentVersion = $updater->getCurrentVersion();
            $latestVersion = $updater->getLatestVersion();
            
            echo '<div class="notice notice-info">';
            echo '<p>My Plugin: Update available from ' . esc_html($currentVersion) . ' to ' . esc_html($latestVersion) . '</p>';
            echo '</div>';
        }
    });
});

// Your plugin code here...
*/

/**
 * Example for plugins with PCLZIP_ERR_MISSING_FILE issues
 * Use custom temporary directory to avoid /tmp permission problems
 */

/*
// For plugins experiencing PCLZIP_ERR_MISSING_FILE errors:

add_action("init", function() {
    $config = new UpdaterConfig(
        __FILE__,
        "your-username/your-plugin",
        [
            "text_domain" => "your-plugin",
            "custom_temp_dir" => WP_CONTENT_DIR . "/temp", // Custom temp directory
            // or use uploads directory:
            // "custom_temp_dir" => wp_upload_dir()["basedir"] . "/temp",
        ]
    );
    
    new Updater($config);
    
    // Optionally create the directory on plugin activation
    register_activation_hook(__FILE__, function() {
        $temp_dir = WP_CONTENT_DIR . "/temp";
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
    });
});
*/

/**
 * Alternative: WordPress configuration approach
 * Add this to your wp-config.php file (before the line that says 
 * "That's all, stop editing!"):
 */

/*
// In wp-config.php, add this line:
define('WP_TEMP_DIR', ABSPATH . 'wp-content/temp');

// Then create the directory with proper permissions:
// mkdir wp-content/temp
// chmod 755 wp-content/temp
*/

/**
 * Installation steps for existing plugins:
 * 
 * 1. Navigate to your plugin directory
 * 2. Run: composer require silverassist/wp-github-updater
 * 3. Replace your existing updater code with the examples above
 * 4. Remove your old updater class files
 * 5. Test the updates
 */

/**
 * New Features in v1.1.4:
 *
 * - WordPress admin notices for manual version checks
 * - Dismissible admin notices with AJAX functionality
 * - Improved code organization with isUpdateAvailable() method
 *
 * New Features in v1.1.3:
 *
 * - Enhanced temporary file handling to resolve PCLZIP errors
 * - Better error handling and hosting environment compatibility
 *
 * Public API methods (v1.1.2+):
 * - $updater->isUpdateAvailable() - Check if update is available
 * - $updater->getCurrentVersion() - Get current plugin version
 * - $updater->getLatestVersion() - Get latest GitHub version
 * - $updater->getGithubRepo() - Get repository name
 */
