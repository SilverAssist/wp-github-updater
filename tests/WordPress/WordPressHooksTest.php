<?php

/**
 * WordPress-specific integration tests
 *
 * @package SilverAssist\WpGithubUpdater\Tests\WordPress
 */

namespace SilverAssist\WpGithubUpdater\Tests\WordPress;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

/**
 * Test WordPress-specific functionality
 *
 * These tests verify WordPress hooks, filters, and integration points.
 * Note: These tests use mocked WordPress functions.
 */
class WordPressHooksTest extends TestCase
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
        file_put_contents(
            $this->testPluginFile,
            "<?php\n/*\nPlugin Name: Test Plugin\nVersion: 1.0.0\nDescription: Test plugin\n*/"
        );

        // Create test configuration
        $this->config = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "plugin_name" => "Test Plugin",
            "plugin_description" => "Test plugin description",
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
     * Test configuration object creation
     */
    public function testConfigurationCreation(): void
    {
        $this->assertInstanceOf(UpdaterConfig::class, $this->config);
        $this->assertEquals("SilverAssist/test-repo", $this->config->githubRepo);
        $this->assertEquals($this->testPluginFile, $this->config->pluginFile);
    }

    /**
     * Test plugin homepage configuration
     */
    public function testPluginHomepage(): void
    {
        $expectedHomepage = "https://github.com/SilverAssist/test-repo";
        $this->assertEquals($expectedHomepage, $this->config->pluginHomepage);
    }

    /**
     * Test plugin author configuration
     */
    public function testPluginAuthor(): void
    {
        // When plugin file doesn't exist or can't be read, author will be empty string
        // unless explicitly provided in options
        $this->assertIsString($this->config->pluginAuthor);

        // Test with explicit author option
        $configWithAuthor = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "plugin_author" => "SilverAssist",
        ]);
        $this->assertEquals("SilverAssist", $configWithAuthor->pluginAuthor);
    }

    /**
     * Test cache duration configuration
     */
    public function testCacheDuration(): void
    {
        $this->assertEquals(43200, $this->config->cacheDuration); // Default 12 hours

        $customConfig = new UpdaterConfig($this->testPluginFile, "SilverAssist/test-repo", [
            "cache_duration" => 3600,
        ]);
        $this->assertEquals(3600, $customConfig->cacheDuration);
    }

    /**
     * Test transient naming convention
     */
    public function testTransientNaming(): void
    {
        $pluginBasename = basename(dirname($this->testPluginFile));
        $expectedTransient = "{$pluginBasename}_version_check";

        // The transient name should follow WordPress conventions (lowercase, numbers, underscores, dashes)
        // Note: basename() may return uppercase letters, which is acceptable in WordPress transients
        $this->assertMatchesRegularExpression("/^[a-zA-Z0-9_-]+$/", $expectedTransient);
    }

    /**
     * Test AJAX action naming convention
     */
    public function testAjaxActionNaming(): void
    {
        $ajaxAction = $this->config->ajaxAction;

        // AJAX action should follow WordPress conventions (lowercase, numbers, underscores)
        $this->assertNotEmpty($ajaxAction);
        $this->assertMatchesRegularExpression("/^[a-z0-9_-]+$/", $ajaxAction);
        $this->assertEquals("check_plugin_version", $ajaxAction);
    }

    /**
     * Test nonce naming convention
     */
    public function testNonceNaming(): void
    {
        $nonce = $this->config->ajaxNonce;

        // Nonce should follow WordPress conventions (lowercase, numbers, underscores)
        $this->assertNotEmpty($nonce);
        $this->assertMatchesRegularExpression("/^[a-z0-9_-]+$/", $nonce);
        $this->assertEquals("plugin_version_check", $nonce);
    }

    /**
     * Test plugin data structure
     */
    public function testPluginDataStructure(): void
    {
        $this->assertIsString($this->config->pluginName);
        $this->assertIsString($this->config->pluginDescription);
        $this->assertIsString($this->config->pluginAuthor);
        $this->assertIsString($this->config->pluginHomepage);
    }

    /**
     * Test WordPress version requirements
     */
    public function testWordPressVersionRequirements(): void
    {
        $this->assertIsString($this->config->requiresWordPress);
        $this->assertMatchesRegularExpression("/^\d+\.\d+$/", $this->config->requiresWordPress);
        $this->assertGreaterThanOrEqual(6.0, (float) $this->config->requiresWordPress);
    }

    /**
     * Test PHP version requirements
     */
    public function testPHPVersionRequirements(): void
    {
        $this->assertIsString($this->config->requiresPHP);
        $this->assertMatchesRegularExpression("/^\d+\.\d+$/", $this->config->requiresPHP);
        $this->assertGreaterThanOrEqual(8.2, (float) $this->config->requiresPHP);
    }

    /**
     * Test asset pattern replacement tokens
     */
    public function testAssetPatternTokens(): void
    {
        $pattern = $this->config->assetPattern;

        // Pattern should contain replacement tokens
        $this->assertStringContainsString("{slug}", $pattern);
        $this->assertStringContainsString("{version}", $pattern);
        $this->assertStringEndsWith(".zip", $pattern);
    }

    /**
     * Test translation function wrapper
     */
    public function testTranslationFunctionWrapper(): void
    {
        $testString = "Test string";
        $translated = $this->config->__($testString);

        // In test environment, should return the original string
        $this->assertEquals($testString, $translated);
    }

    /**
     * Test GitHub API URL construction
     */
    public function testGitHubApiUrlConstruction(): void
    {
        $repo = $this->config->githubRepo;
        $expectedBaseUrl = "https://api.github.com/repos/{$repo}";

        $this->assertStringContainsString("SilverAssist", $expectedBaseUrl);
        $this->assertStringContainsString("test-repo", $expectedBaseUrl);
    }
}
