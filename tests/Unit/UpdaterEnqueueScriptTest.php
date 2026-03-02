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
     * Test getPackageAssetUrl() with standard vendor directory structure
     *
     * Simulates a plugin with the package installed via Composer in vendor/silverassist/wp-github-updater
     * This tests the primary path resolution logic for multi-plugin scenarios.
     *
     * @since 1.3.1
     */
    public function testGetPackageAssetUrlWithStandardVendorStructure(): void
    {
        // Create a temporary plugin structure that mimics a real Composer installation
        $tempDir = sys_get_temp_dir() . "/wp-github-updater-test-" . uniqid();
        $pluginDir = $tempDir . "/my-plugin";
        $vendorDir = $pluginDir . "/vendor/silverassist/wp-github-updater";
        $assetsDir = $vendorDir . "/assets/js";

        // Create the directory structure
        mkdir($assetsDir, 0755, true);

        // Create a mock plugin file
        $pluginFile = $pluginDir . "/my-plugin.php";
        file_put_contents($pluginFile, "<?php // Mock plugin file");

        try {
            $config = new UpdaterConfig($pluginFile, "owner/repo", [
                "plugin_name" => "My Plugin",
            ]);

            $updater = new Updater($config);

            // Use reflection to access the private method
            $reflection = new \ReflectionClass($updater);
            $method = $reflection->getMethod("getPackageAssetUrl");
            $method->setAccessible(true);

            // Test asset URL generation
            $assetUrl = $method->invoke($updater, "assets/js/check-updates.js");

            // The URL should contain the vendor path
            $this->assertStringContainsString("vendor/silverassist/wp-github-updater", $assetUrl);
            $this->assertStringContainsString("assets/js/check-updates.js", $assetUrl);

            // Test with leading slash
            $assetUrl2 = $method->invoke($updater, "/assets/js/check-updates.js");
            $this->assertStringContainsString("assets/js/check-updates.js", $assetUrl2);
            $this->assertEquals($assetUrl, $assetUrl2);
        } finally {
            // Cleanup
            if (file_exists($pluginFile)) {
                unlink($pluginFile);
            }
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
        }
    }

    /**
     * Test getPackageAssetUrl() fallback for non-standard installations
     *
     * When the standard vendor path doesn't exist, the method should fall back
     * to __DIR__-based resolution for development or non-Composer installations.
     *
     * @since 1.3.1
     */
    public function testGetPackageAssetUrlFallbackForNonStandardInstallation(): void
    {
        // Create a temporary plugin structure WITHOUT vendor directory
        $tempDir = sys_get_temp_dir() . "/wp-github-updater-test-" . uniqid();
        $pluginDir = $tempDir . "/my-plugin";

        // Create the directory structure (no vendor dir)
        mkdir($pluginDir, 0755, true);

        // Create a mock plugin file
        $pluginFile = $pluginDir . "/my-plugin.php";
        file_put_contents($pluginFile, "<?php // Mock plugin file");

        try {
            $config = new UpdaterConfig($pluginFile, "owner/repo", [
                "plugin_name" => "My Plugin",
            ]);

            $updater = new Updater($config);

            // Use reflection to access the private method
            $reflection = new \ReflectionClass($updater);
            $method = $reflection->getMethod("getPackageAssetUrl");
            $method->setAccessible(true);

            // Test asset URL generation (should fall back to __DIR__ logic)
            $assetUrl = $method->invoke($updater, "assets/js/check-updates.js");

            // The URL should contain the asset path
            $this->assertStringContainsString("assets/js/check-updates.js", $assetUrl);
            $this->assertIsString($assetUrl);
        } finally {
            // Cleanup
            if (file_exists($pluginFile)) {
                unlink($pluginFile);
            }
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
    public function testGetPackageAssetUrlWithMultiplePlugins(): void
    {
        // Create two plugin directories with identical vendor structure
        $tempDir = sys_get_temp_dir() . "/wp-github-updater-test-" . uniqid();

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

            // Use reflection to access the private method
            $reflection1 = new \ReflectionClass($updater1);
            $method1 = $reflection1->getMethod("getPackageAssetUrl");
            $method1->setAccessible(true);

            $reflection2 = new \ReflectionClass($updater2);
            $method2 = $reflection2->getMethod("getPackageAssetUrl");
            $method2->setAccessible(true);

            // Get asset URLs for both plugins
            $assetUrl1 = $method1->invoke($updater1, "assets/js/check-updates.js");
            $assetUrl2 = $method2->invoke($updater2, "assets/js/check-updates.js");

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
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }
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
