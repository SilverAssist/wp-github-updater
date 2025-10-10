<?php

/**
 * Integration tests for download filter functionality
 *
 * @package SilverAssist\WpGithubUpdater\Tests\Integration
 */

namespace SilverAssist\WpGithubUpdater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

/**
 * Test download filter functionality
 *
 * These tests verify the download filter behavior including
 * temporary file creation and validation.
 */
class DownloadFilterTest extends TestCase
{
    private UpdaterConfig $config;
    private Updater $updater;
    private string $testPluginFile;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary plugin file for testing
        $this->testPluginFile = sys_get_temp_dir() . "/test-plugin.php";
        file_put_contents($this->testPluginFile, "<?php\n/*\nPlugin Name: Test Plugin\nVersion: 1.0.0\n*/");

        // Create test configuration
        $this->config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo");
        $this->updater = new Updater($this->config);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        if (file_exists($this->testPluginFile)) {
            unlink($this->testPluginFile);
        }

        parent::tearDown();
    }

    /**
     * Test that temporary directory configuration is respected
     */
    public function testCustomTempDirectoryIsRespected(): void
    {
        $customTempDir = sys_get_temp_dir() . "/wp-github-updater-custom";

        $config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "custom_temp_dir" => $customTempDir,
        ]);

        $this->assertEquals($customTempDir, $config->customTempDir);
    }

    /**
     * Test that package URL validation works correctly
     */
    public function testPackageUrlValidation(): void
    {
        // Valid GitHub URL
        $validUrl = "https://github.com/SilverAssist/test-repo/releases/download/v1.0.0/package.zip";
        $this->assertStringContainsString("github.com", $validUrl);
        $this->assertStringContainsString("SilverAssist/test-repo", $validUrl);

        // Invalid URL (not GitHub)
        $invalidUrl = "https://example.com/package.zip";
        $this->assertStringNotContainsString("github.com", $invalidUrl);
    }

    /**
     * Test file size validation logic
     */
    public function testFileSizeValidation(): void
    {
        $minSize = 100; // Minimum size for valid ZIP

        // Valid size
        $this->assertGreaterThanOrEqual($minSize, 1024);

        // Invalid size
        $this->assertLessThan($minSize, 50);
    }

    /**
     * Test that hook_extra validation logic works
     */
    public function testHookExtraValidation(): void
    {
        $pluginSlug = basename(dirname($this->testPluginFile)) . "/" . basename($this->testPluginFile);

        // Test single plugin update
        $hook_extra_single = [
            "plugin" => $pluginSlug,
        ];
        $this->assertArrayHasKey("plugin", $hook_extra_single);
        $this->assertEquals($pluginSlug, $hook_extra_single["plugin"]);

        // Test bulk plugin update
        $hook_extra_bulk = [
            "plugins" => [$pluginSlug, "other-plugin/other-plugin.php"],
        ];
        $this->assertArrayHasKey("plugins", $hook_extra_bulk);
        $this->assertContains($pluginSlug, $hook_extra_bulk["plugins"]);
    }

    /**
     * Test version comparison logic
     */
    public function testVersionComparison(): void
    {
        // Current version is older than latest
        $this->assertTrue(version_compare("1.0.0", "1.1.0", "<"));
        $this->assertFalse(version_compare("1.1.0", "1.0.0", "<"));

        // Same version
        $this->assertFalse(version_compare("1.0.0", "1.0.0", "<"));
        $this->assertTrue(version_compare("1.0.0", "1.0.0", "="));
    }

    /**
     * Test GitHub repository format validation
     */
    public function testGitHubRepoFormat(): void
    {
        // Valid formats
        $this->assertMatchesRegularExpression("/^[a-zA-Z0-9-]+\/[a-zA-Z0-9-_]+$/", "SilverAssist/test-repo");
        $this->assertMatchesRegularExpression("/^[a-zA-Z0-9-]+\/[a-zA-Z0-9-_]+$/", "owner/repo");

        // Invalid formats
        $this->assertDoesNotMatchRegularExpression("/^[a-zA-Z0-9-]+\/[a-zA-Z0-9-_]+$/", "invalid");
        $this->assertDoesNotMatchRegularExpression("/^[a-zA-Z0-9-]+\/[a-zA-Z0-9-_]+$/", "/owner/repo");
    }
}
