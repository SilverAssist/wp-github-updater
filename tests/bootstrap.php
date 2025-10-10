<?php

/**
 * PHPUnit bootstrap file
 *
 * This bootstrap automatically detects the test environment:
 * - If WordPress Test Suite is available, loads it for WordPress integration tests
 * - Otherwise, uses mocked WordPress functions for unit/integration tests
 *
 * @package SilverAssist\WpGithubUpdater\Tests
 */

// Determine if we should load WordPress Test Suite
$_tests_dir = getenv("WP_TESTS_DIR");
$_skip_wp_tests = filter_var(getenv("SKIP_WP_TESTS_IF_MISSING"), FILTER_VALIDATE_BOOLEAN);

// If not set via environment, try common locations
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), "/\\") . "/wordpress-tests-lib";
}

// Check if WordPress Test Suite is available
$_wp_tests_available = file_exists($_tests_dir . "/includes/functions.php");

// Load Composer autoloader
require_once __DIR__ . "/../vendor/autoload.php";

// Load Yoast PHPUnit Polyfills for PHPUnit 9.x compatibility
if (file_exists(__DIR__ . "/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php")) {
    require_once __DIR__ . "/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php";
}

// Decide which environment to load
if ($_wp_tests_available) {
    // WordPress Test Suite is available - load it
    echo "\n";
    echo "====================================\n";
    echo "WP GitHub Updater Test Suite\n";
    echo "====================================\n";
    echo "Mode: WordPress Integration Tests\n";
    echo "WP Tests Dir: $_tests_dir\n";
    echo "====================================\n\n";

    // Load WordPress test suite
    require_once $_tests_dir . "/includes/functions.php";

    /**
     * Manually load the mock plugin for testing
     *
     * This loads our mock plugin that integrates the WP GitHub Updater package
     * into a WordPress environment for real integration testing.
     */
    function _manually_load_plugin()
    {
        // Load the mock plugin that uses the WP GitHub Updater package
        $mock_plugin_file = __DIR__ . "/fixtures/mock-plugin/mock-plugin.php";
        
        if (file_exists($mock_plugin_file)) {
            require_once $mock_plugin_file;
            echo "✓ Mock plugin loaded: {$mock_plugin_file}\n";
        } else {
            echo "⚠️  Mock plugin not found at: {$mock_plugin_file}\n";
        }
    }

    tests_add_filter("muplugins_loaded", "_manually_load_plugin");

    // Start up the WP testing environment
    require $_tests_dir . "/includes/bootstrap.php";

} else {
    // WordPress Test Suite not available - use mocks
    
    // Define WordPress constants for mock environment
    if (!defined("ABSPATH")) {
        define("ABSPATH", __DIR__ . "/../");
    }

    if (!defined("WP_CONTENT_DIR")) {
        define("WP_CONTENT_DIR", ABSPATH . "wp-content");
    }

    if (!defined("WP_PLUGIN_DIR")) {
        define("WP_PLUGIN_DIR", WP_CONTENT_DIR . "/plugins");
    }
    
    // Load WordPress function mocks for non-WP-Test-Suite environment
    require_once __DIR__ . "/wordpress-mocks.php";
    
    if (!$_skip_wp_tests) {
        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "⚠️  WordPress Test Suite not found\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\n";
        echo "Running tests with mocked WordPress functions.\n";
        echo "For full WordPress integration tests, install WordPress Test Suite:\n";
        echo "  ./bin/install-wp-tests.sh wordpress_test root '' localhost 6.7.1\n";
        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\n";
    }

    // Note: We don't load wordpress-stubs here because they conflict with our mocks
    // wordpress-stubs are only used for static analysis (PHPStan)
    // Our WordPress function mocks are loaded via Composer's autoload-dev files
    // See composer.json autoload-dev.files section

    // Display test suite information
    echo "\n";
    echo "====================================\n";
    echo "WP GitHub Updater Test Suite\n";
    echo "====================================\n";
    echo "Mode: Unit/Integration Tests (Mocked)\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "PHPUnit Version: " . PHPUnit\Runner\Version::id() . "\n";
    echo "====================================\n\n";
}
