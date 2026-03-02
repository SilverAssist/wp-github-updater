<?php

namespace SilverAssist\WpGithubUpdater\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

/**
 * Test the enqueueCheckUpdatesScript functionality
 *
 * @package SilverAssist\WpGithubUpdater\Tests\Unit
 * @since 1.3.0
 */
class UpdaterEnqueueScriptTest extends TestCase
{
    private static string $testPluginFile;

    public static function setUpBeforeClass(): void
    {
        self::$testPluginFile = dirname(__DIR__) . "/fixtures/test-plugin.php";
    }

    public function testEnqueueCheckUpdatesScriptReturnsValidJavaScript(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
            "plugin_name" => "Test Plugin",
            "text_domain" => "test-plugin",
        ]);

        $updater = new Updater($config);
        $result = $updater->enqueueCheckUpdatesScript();

        // Should return a string
        $this->assertIsString($result);

        // Should contain the function call
        $this->assertStringContainsString("wpGithubUpdaterCheckUpdates", $result);

        // Should contain return false
        $this->assertStringContainsString("return false", $result);

        // Should contain a valid JS variable name (sanitized plugin basename)
        $this->assertMatchesRegularExpression("/wpGithubUpdaterCheckUpdates\('[a-zA-Z0-9_$]+\'\)/", $result);
    }

    public function testEnqueueCheckUpdatesScriptWithExtraStrings(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
            "plugin_name" => "Test Plugin",
            "text_domain" => "test-plugin",
        ]);

        $updater = new Updater($config);
        $extraStrings = [
            "checking" => "Custom checking message...",
            "upToDate" => "Custom up to date message!",
        ];

        $result = $updater->enqueueCheckUpdatesScript($extraStrings);

        // Should still return valid JavaScript
        $this->assertIsString($result);
        $this->assertStringContainsString("wpGithubUpdaterCheckUpdates", $result);
    }

    public function testEnqueueCheckUpdatesScriptUsesPluginBasename(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
            "plugin_name" => "Test Plugin",
        ]);

        $updater = new Updater($config);
        $result = $updater->enqueueCheckUpdatesScript();

        // The result should reference the plugin (test-plugin is the basename)
        // Note: The exact value depends on plugin basename extraction from test-plugin.php
        $this->assertStringContainsString("wpGithubUpdaterCheckUpdates", $result);

        // Should not contain any invalid JavaScript characters in the variable name
        $this->assertDoesNotMatchRegularExpression("/wpGithubUpdaterCheckUpdates\('[^a-zA-Z0-9_$']+\'\)/", $result);
    }

    /**
     * Test that the method can be called multiple times without errors
     */
    public function testEnqueueCheckUpdatesScriptCanBeCalledMultipleTimes(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo");
        $updater = new Updater($config);

        $result1 = $updater->enqueueCheckUpdatesScript();
        $result2 = $updater->enqueueCheckUpdatesScript();

        // Both calls should return valid results
        $this->assertIsString($result1);
        $this->assertIsString($result2);

        // Results should be identical
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test asset URL resolution with standard vendor directory structure
     *
     * Simulates a plugin with the package installed via Composer in vendor/silverassist/wp-github-updater
     * This tests the primary path resolution logic for multi-plugin scenarios.
     *
     * @since 1.3.1
     */
    public function testAssetUrlResolutionWithStandardVendorStructure(): void
    {
        // Create a temporary plugin structure that mimics a real Composer installation
        $tempDir = sys_get_temp_dir() . "/wp-github-updater-test-" . uniqid("", true);
        $pluginDir = $tempDir . "/my-plugin";
        $vendorDir = $pluginDir . "/vendor/silverassist/wp-github-updater";
        $assetsDir = $vendorDir . "/assets/js";

        // Create the directory structure
        mkdir($assetsDir, 0755, true);

        // Create a mock plugin file
        $pluginFile = $pluginDir . "/my-plugin.php";
        file_put_contents($pluginFile, "<?php // Mock plugin file");

        // Create the actual asset file
        $assetFile = $assetsDir . "/check-updates.js";
        file_put_contents($assetFile, "// Mock JavaScript file");

        try {
            // Clear enqueued scripts (works with both mock and real WordPress)
            $this->clearEnqueuedScripts();

            $config = new UpdaterConfig($pluginFile, "owner/repo", [
                "plugin_name" => "My Plugin",
            ]);

            $updater = new Updater($config);

            // Call the public API method which internally uses getPackageAssetUrl()
            $updater->enqueueCheckUpdatesScript();

            // Check that wp_enqueue_script was called with the correct URL
            $enqueuedSrc = $this->getEnqueuedScriptSrc("wp-github-updater-check");
            $this->assertNotNull($enqueuedSrc, "Script 'wp-github-updater-check' should be enqueued");

            // The URL should contain the vendor path
            $this->assertStringContainsString("vendor/silverassist/wp-github-updater", $enqueuedSrc);
            $this->assertStringContainsString("assets/js/check-updates.js", $enqueuedSrc);
            $this->assertStringContainsString("my-plugin", $enqueuedSrc);
        } finally {
            // Cleanup
            $this->clearEnqueuedScripts();
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
        }
    }

    /**
     * Test asset URL resolution fallback for non-standard installations
     *
     * When the standard vendor path doesn't exist, the method should fall back
     * to __DIR__-based resolution for development or non-Composer installations.
     *
     * @since 1.3.1
     */
    public function testAssetUrlResolutionFallbackForNonStandardInstallation(): void
    {
        // Create a temporary plugin structure WITHOUT vendor directory
        $tempDir = sys_get_temp_dir() . "/wp-github-updater-test-" . uniqid("", true);
        $pluginDir = $tempDir . "/my-plugin";

        // Create the directory structure (no vendor dir)
        mkdir($pluginDir, 0755, true);

        // Create a mock plugin file
        $pluginFile = $pluginDir . "/my-plugin.php";
        file_put_contents($pluginFile, "<?php // Mock plugin file");

        try {
            // Clear enqueued scripts (works with both mock and real WordPress)
            $this->clearEnqueuedScripts();

            $config = new UpdaterConfig($pluginFile, "owner/repo", [
                "plugin_name" => "My Plugin",
            ]);

            $updater = new Updater($config);

            // Call the public API method which internally uses getPackageAssetUrl()
            $updater->enqueueCheckUpdatesScript();

            // Check that wp_enqueue_script was called
            $enqueuedSrc = $this->getEnqueuedScriptSrc("wp-github-updater-check");
            $this->assertNotNull($enqueuedSrc, "Script 'wp-github-updater-check' should be enqueued");

            // The URL should contain the asset path (fallback behavior)
            $this->assertStringContainsString("assets/js/check-updates.js", $enqueuedSrc);
            $this->assertIsString($enqueuedSrc);
        } finally {
            // Cleanup
            $this->clearEnqueuedScripts();
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
        }
    }

    /**
     * Test multi-plugin scenario where different plugins use the same package
     *
     * This is the critical test for the bug fix. When multiple plugins use this package,
     * each plugin instance should resolve assets from its own vendor directory, not from
     * the first loaded instance's directory.
     *
     * @since 1.3.1
     */
    public function testAssetUrlResolutionWithMultiplePlugins(): void
    {
        // Create two plugin directories with identical vendor structure
        $tempDir = sys_get_temp_dir() . "/wp-github-updater-test-" . uniqid("", true);

        $plugin1Dir = $tempDir . "/plugin-one";
        $vendor1Dir = $plugin1Dir . "/vendor/silverassist/wp-github-updater";
        mkdir($vendor1Dir . "/assets/js", 0755, true);

        $plugin2Dir = $tempDir . "/plugin-two";
        $vendor2Dir = $plugin2Dir . "/vendor/silverassist/wp-github-updater";
        mkdir($vendor2Dir . "/assets/js", 0755, true);

        $plugin1File = $plugin1Dir . "/plugin-one.php";
        $plugin2File = $plugin2Dir . "/plugin-two.php";
        file_put_contents($plugin1File, "<?php // Plugin One");
        file_put_contents($plugin2File, "<?php // Plugin Two");

        // Create the actual asset files in both plugins
        $asset1File = $vendor1Dir . "/assets/js/check-updates.js";
        $asset2File = $vendor2Dir . "/assets/js/check-updates.js";
        file_put_contents($asset1File, "// Plugin One JavaScript");
        file_put_contents($asset2File, "// Plugin Two JavaScript");

        try {
            // Create instances for both plugins
            $config1 = new UpdaterConfig($plugin1File, "owner/repo1", [
                "plugin_name" => "Plugin One",
            ]);
            $updater1 = new Updater($config1);

            $config2 = new UpdaterConfig($plugin2File, "owner/repo2", [
                "plugin_name" => "Plugin Two",
            ]);
            $updater2 = new Updater($config2);

            // Clear enqueued scripts (works with both mock and real WordPress)
            $this->clearEnqueuedScripts();

            // Call enqueueCheckUpdatesScript for first plugin
            $updater1->enqueueCheckUpdatesScript();
            $assetUrl1 = $this->getEnqueuedScriptSrc("wp-github-updater-check");
            $this->assertNotNull($assetUrl1, "Script 'wp-github-updater-check' should be enqueued for plugin 1");

            // Clear and test second plugin
            $this->clearEnqueuedScripts();
            $updater2->enqueueCheckUpdatesScript();
            $assetUrl2 = $this->getEnqueuedScriptSrc("wp-github-updater-check");
            $this->assertNotNull($assetUrl2, "Script 'wp-github-updater-check' should be enqueued for plugin 2");

            // Each plugin should resolve to its own vendor directory
            $this->assertStringContainsString("plugin-one", $assetUrl1);
            $this->assertStringNotContainsString("plugin-two", $assetUrl1);

            $this->assertStringContainsString("plugin-two", $assetUrl2);
            $this->assertStringNotContainsString("plugin-one", $assetUrl2);

            // Both should contain the vendor path and asset path
            $this->assertStringContainsString("vendor/silverassist/wp-github-updater", $assetUrl1);
            $this->assertStringContainsString("assets/js/check-updates.js", $assetUrl1);

            $this->assertStringContainsString("vendor/silverassist/wp-github-updater", $assetUrl2);
            $this->assertStringContainsString("assets/js/check-updates.js", $assetUrl2);
        } finally {
            // Cleanup
            $this->clearEnqueuedScripts();
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
        }
    }

    /**
     * Get the src URL of an enqueued script, compatible with both mock and real WordPress
     *
     * @param string $handle Script handle
     * @return string|null The script source URL, or null if not found
     */
    private function getEnqueuedScriptSrc(string $handle): ?string
    {
        // Check mock global first (used in non-WordPress test environment)
        global $wp_enqueued_scripts;
        if (isset($wp_enqueued_scripts[$handle])) {
            return $wp_enqueued_scripts[$handle]["src"];
        }

        // Check WordPress's real registered scripts (WordPress integration test environment)
        if (function_exists("wp_scripts")) {
            $scripts = \wp_scripts();
            if (isset($scripts->registered[$handle])) {
                return $scripts->registered[$handle]->src;
            }
        }

        return null;
    }

    /**
     * Clear enqueued scripts state, compatible with both mock and real WordPress
     *
     * @return void
     */
    private function clearEnqueuedScripts(): void
    {
        global $wp_enqueued_scripts;
        $wp_enqueued_scripts = [];

        // In real WordPress environment, deregister the script so it can be re-registered
        if (function_exists("wp_deregister_script")) {
            \wp_deregister_script("wp-github-updater-check");
        }
    }

    /**
     * Recursively remove a directory and its contents
     *
     * @param string $dir Directory path to remove
     * @return void
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), [".", ".."]);
        foreach ($files as $file) {
            $path = $dir . "/" . $file;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
