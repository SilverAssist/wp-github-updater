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
add_action('init', function() {
    $config = new UpdaterConfig(
        __FILE__, // Path to your main plugin file
        'SilverAssist/silver-assist-security', // Your GitHub repo
        [
            'asset_pattern' => 'silver-assist-security-v{version}.zip',
            'ajax_action' => 'silver_assist_security_check_version',
            'ajax_nonce' => 'silver_assist_security_ajax'
        ]
    );
    
    new Updater($config);
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

add_action('init', function() {
    $config = new UpdaterConfig(
        __FILE__,
        'your-username/leadgen-app-form', // Your GitHub repo
        [
            'asset_pattern' => 'leadgen-app-form-v{version}.zip',
            'ajax_action' => 'leadgen_check_version',
            'ajax_nonce' => 'leadgen_version_check'
        ]
    );
    
    new Updater($config);
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
add_action('init', function() {
    $config = new UpdaterConfig(
        __FILE__,
        'your-username/my-new-plugin', // GitHub repository
        [
            // Optional customizations
            'asset_pattern' => 'my-plugin-{version}.zip',
            'requires_php' => '8.1',
            'requires_wordpress' => '6.2',
            'ajax_action' => 'my_plugin_version_check',
            'cache_duration' => 6 * 3600 // 6 hours
        ]
    );
    
    new Updater($config);
});

// Your plugin code here...
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
