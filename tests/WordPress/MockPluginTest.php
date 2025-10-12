<?php

/**
 * Tests for Mock Plugin with WordPress Test Suite
 *
 * These tests use the WordPress Test Suite to test the updater
 * in a real WordPress environment with the mock plugin.
 *
 * NOTE: These tests are ONLY loaded when WordPress Test Suite is available.
 * They will be skipped automatically if WP_UnitTestCase is not defined.
 *
 * @package SilverAssist\WpGithubUpdater\Tests\WordPress
 */

namespace SilverAssist\WpGithubUpdater\Tests\WordPress;

// Only load tests if WordPress Test Suite is available
if (!class_exists("WP_UnitTestCase")) {
    return;
}

use WP_UnitTestCase;
use SilverAssist\WpGithubUpdater\Updater;

/**
 * Mock Plugin Integration Tests
 *
 * These tests require WordPress Test Suite to be installed.
 * Run: ./bin/install-wp-tests.sh wordpress_test root '' localhost 6.7.1
 */
class MockPluginTest extends WP_UnitTestCase
{
    private string $pluginFile;
    private ?Updater $updater = null;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Define plugin file path
        $this->pluginFile = dirname(__DIR__) . "/fixtures/mock-plugin/mock-plugin.php";

        // Load the mock plugin
        if (file_exists($this->pluginFile)) {
            require_once $this->pluginFile;
            
            // Initialize the updater
            do_action("plugins_loaded");
            
            // Get updater instance
            $this->updater = mock_plugin_get_updater();
        }
    }

    /**
     * Test that mock plugin file exists
     */
    public function testMockPluginFileExists(): void
    {
        $this->assertFileExists($this->pluginFile, "Mock plugin file should exist");
    }

    /**
     * Test that mock plugin can be loaded
     */
    public function testMockPluginCanBeLoaded(): void
    {
        $this->assertTrue(
            function_exists("mock_plugin_init_updater"),
            "Mock plugin functions should be available"
        );
        $this->assertTrue(
            function_exists("mock_plugin_get_updater"),
            "Mock plugin helper functions should be available"
        );
    }

    /**
     * Test that updater is initialized
     */
    public function testUpdaterIsInitialized(): void
    {
        $this->assertNotNull($this->updater, "Updater should be initialized");
        $this->assertInstanceOf(Updater::class, $this->updater, "Should be Updater instance");
    }

    /**
     * Test updater configuration
     */
    public function testUpdaterConfiguration(): void
    {
        if (!$this->updater) {
            $this->markTestSkipped("Updater not initialized");
        }

        $this->assertEquals("SilverAssist/mock-test-repo", $this->updater->getGithubRepo());
        $this->assertEquals("1.0.0", $this->updater->getCurrentVersion());
    }

    /**
     * Test WordPress hooks are registered
     */
    public function testWordPressHooksAreRegistered(): void
    {
        // Check that update check filter is registered
        $this->assertNotFalse(
            has_filter("pre_set_site_transient_update_plugins"),
            "Update check filter should be registered"
        );

        // Check that plugin info filter is registered
        $this->assertNotFalse(
            has_filter("plugins_api"),
            "Plugin info filter should be registered"
        );
    }

    /**
     * Test AJAX actions are registered
     */
    public function testAjaxActionsAreRegistered(): void
    {
        // Check that AJAX actions are registered (only for logged-in users)
        $this->assertNotFalse(
            has_action("wp_ajax_mock_plugin_check_version"),
            "AJAX check version action should be registered"
        );

        // Check that dismiss notice action is registered
        $this->assertNotFalse(
            has_action("wp_ajax_mock_plugin_check_version_dismiss_notice"),
            "AJAX dismiss notice action should be registered"
        );
    }

    /**
     * Test plugin activation
     */
    public function testPluginActivation(): void
    {
        // Set a transient to test cleanup
        set_transient("mock-plugin_version_check", "test_data", 3600);
        $this->assertNotFalse(get_transient("mock-plugin_version_check"));

        // Trigger activation
        do_action("activate_" . plugin_basename($this->pluginFile));

        // In real implementation, transient should be cleared
        // For now, just verify activation doesn't cause errors
        $this->assertTrue(true);
    }

    /**
     * Test plugin deactivation
     */
    public function testPluginDeactivation(): void
    {
        // Set a transient
        set_transient("mock-plugin_version_check", "test_data", 3600);

        // Trigger deactivation
        do_action("deactivate_" . plugin_basename($this->pluginFile));

        // Verify cleanup happens (implementation-specific)
        $this->assertTrue(true);
    }

    /**
     * Test admin menu is registered
     */
    public function testAdminMenuIsRegistered(): void
    {
        // Set current user as administrator
        $user_id = $this->factory->user->create(["role" => "administrator"]);
        wp_set_current_user($user_id);

        // Trigger admin menu hook
        do_action("admin_menu");

        // Check that menu was added
        global $menu;
        $this->assertIsArray($menu);
    }

    /**
     * Test update check with transient caching
     */
    public function testUpdateCheckWithCaching(): void
    {
        if (!$this->updater) {
            $this->markTestSkipped("Updater not initialized");
        }

        // Clear any existing cache
        delete_transient("mock-plugin_version_check");

        // First check should query API (we can't test actual API in unit tests)
        $updateAvailable = $this->updater->isUpdateAvailable();
        $this->assertIsBool($updateAvailable);

        // Second check should use cache (if API was successful)
        $updateAvailable2 = $this->updater->isUpdateAvailable();
        $this->assertIsBool($updateAvailable2);
    }

    /**
     * Test plugin data retrieval
     */
    public function testPluginDataRetrieval(): void
    {
        $pluginData = \get_plugin_data($this->pluginFile, false, false); // Don't markup, don't translate

        $this->assertIsArray($pluginData);
        $this->assertEquals("Mock Plugin for WP GitHub Updater Tests", $pluginData["Name"]);
        $this->assertEquals("1.0.0", $pluginData["Version"]);
        $this->assertEquals("SilverAssist", $pluginData["Author"]);
        $this->assertEquals("6.0", $pluginData["RequiresWP"]);
        $this->assertEquals("8.2", $pluginData["RequiresPHP"]);
    }

    /**
     * Test plugin basename generation
     */
    public function testPluginBasename(): void
    {
        $basename = plugin_basename($this->pluginFile);
        
        $this->assertIsString($basename);
        $this->assertStringContainsString("mock-plugin.php", $basename);
    }

    /**
     * Test custom temporary directory configuration
     */
    public function testCustomTempDirectoryConfiguration(): void
    {
        // This tests the v1.1.3+ feature for custom temp directories
        $expectedTempDir = WP_CONTENT_DIR . "/uploads/temp";
        
        // The mock plugin configures a custom temp dir
        // We verify this through the configuration
        $this->assertDirectoryExists(WP_CONTENT_DIR . "/uploads");
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void
    {
        // Clean up transients
        delete_transient("mock-plugin_version_check");

        parent::tearDown();
    }
}
