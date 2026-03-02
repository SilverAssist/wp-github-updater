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
        $this->assertRegExp("/wpGithubUpdaterCheckUpdates\('[a-zA-Z0-9_$]+'\)/", $result);
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
        // Use assertNotRegExp for PHPUnit 8.x compatibility
        $this->assertNotRegExp("/wpGithubUpdaterCheckUpdates\('[^a-zA-Z0-9_$']+'\)/", $result);
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
}
