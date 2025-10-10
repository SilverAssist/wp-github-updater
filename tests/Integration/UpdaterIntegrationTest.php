<?php

/**
 * Integration tests for Updater class
 *
 * @package SilverAssist\WpGithubUpdater\Tests\Integration
 */

namespace SilverAssist\WpGithubUpdater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

/**
 * Test Updater integration with GitHub API
 *
 * These tests verify the integration between the Updater class
 * and external dependencies like GitHub API (mocked).
 */
class UpdaterIntegrationTest extends TestCase
{
    private UpdaterConfig $config;
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
        $this->config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "plugin_name" => "Test Plugin",
            "plugin_description" => "Test plugin description",
            "cache_duration" => 60, // Short cache for testing
        ]);
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
     * Test that Updater can be instantiated with valid configuration
     */
    public function testUpdaterInstantiation(): void
    {
        $updater = new Updater($this->config);

        $this->assertInstanceOf(Updater::class, $updater);
        $this->assertEquals("1.0.0", $updater->getCurrentVersion());
        $this->assertEquals("SilverAssist/test-repo", $updater->getGithubRepo());
    }

    /**
     * Test configuration validation
     */
    public function testConfigurationValidation(): void
    {
        $this->assertEquals("Test Plugin", $this->config->pluginName);
        $this->assertEquals("Test plugin description", $this->config->pluginDescription);
        $this->assertEquals("SilverAssist/test-repo", $this->config->githubRepo);
        $this->assertEquals(60, $this->config->cacheDuration);
    }

    /**
     * Test custom temporary directory configuration
     */
    public function testCustomTempDirConfiguration(): void
    {
        $customTempDir = sys_get_temp_dir() . "/wp-github-updater-test";

        $config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "custom_temp_dir" => $customTempDir,
        ]);

        $this->assertEquals($customTempDir, $config->customTempDir);
    }

    /**
     * Test text domain configuration
     */
    public function testTextDomainConfiguration(): void
    {
        $config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "text_domain" => "custom-domain",
        ]);

        $this->assertEquals("custom-domain", $config->textDomain);
    }

    /**
     * Test AJAX configuration
     */
    public function testAjaxConfiguration(): void
    {
        $this->assertNotEmpty($this->config->ajaxAction);
        $this->assertNotEmpty($this->config->ajaxNonce);
        $this->assertEquals("check_plugin_version", $this->config->ajaxAction);
        $this->assertEquals("plugin_version_check", $this->config->ajaxNonce);
    }

    /**
     * Test asset pattern configuration
     */
    public function testAssetPatternConfiguration(): void
    {
        $config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "asset_pattern" => "custom-{slug}-{version}.zip",
        ]);

        $this->assertEquals("custom-{slug}-{version}.zip", $config->assetPattern);
    }

    /**
     * Test WordPress requirements configuration
     */
    public function testWordPressRequirementsConfiguration(): void
    {
        $config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "requires_wordpress" => "6.2",
            "requires_php" => "8.1",
        ]);

        $this->assertEquals("6.2", $config->requiresWordPress);
        $this->assertEquals("8.1", $config->requiresPHP);
    }
}
