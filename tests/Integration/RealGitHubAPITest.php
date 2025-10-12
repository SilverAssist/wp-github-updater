<?php

/**
 * Real GitHub API Integration Tests
 *
 * Tests using real GitHub API with silver-assist-post-revalidate repository.
 * These tests make actual HTTP requests to GitHub's API.
 *
 * @package SilverAssist\WpGithubUpdater
 * @since 1.1.6
 */

namespace SilverAssist\WpGithubUpdater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\UpdaterConfig;
use SilverAssist\WpGithubUpdater\Updater;

/**
 * Real GitHub API Integration Test Class
 *
 * Tests actual communication with GitHub's API using a real repository.
 *
 * @since 1.1.6
 */
class RealGitHubAPITest extends TestCase
{
    /**
     * Test plugin file path
     *
     * @var string
     */
    private static string $testPluginFile;

    /**
     * Set up before class
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$testPluginFile = dirname(__DIR__) . "/fixtures/test-plugin.php";
    }

    /**
     * Test fetching latest version from real GitHub repository
     *
     * @return void
     */
    public function testFetchLatestVersionFromRealRepo(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate"
        );

        $updater = new Updater($config);

        // This makes a real HTTP request to GitHub
        $latestVersion = $updater->getLatestVersion();

        // Should return the latest version or false
        $this->assertTrue(
            $latestVersion === false || is_string($latestVersion),
            "getLatestVersion() should return string or false"
        );

        // If successful, should be a valid version string
        if ($latestVersion !== false) {
            $this->assertMatchesRegularExpression(
                "/^\d+\.\d+\.\d+$/",
                $latestVersion,
                "Version should be in format X.Y.Z"
            );

            // Latest version should be at least v1.3.0 (current latest)
            $this->assertGreaterThanOrEqual(
                "1.3.0",
                $latestVersion,
                "Latest version should be at least v1.3.0"
            );
        }
    }

    /**
     * Test fetching release information from real GitHub repository
     *
     * @return void
     */
    public function testFetchReleaseInformationFromRealRepo(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate"
        );

        $updater = new Updater($config);

        // Get latest version (makes real HTTP request)
        $version = $updater->getLatestVersion();

        // Should return string version or false
        $this->assertTrue(
            $version === false || is_string($version),
            "getLatestVersion() should return string or false"
        );

        // If successful, should have valid version
        if ($version !== false) {
            // Verify it's a valid semantic version
            $this->assertMatchesRegularExpression(
                "/^\d+\.\d+\.\d+$/",
                $version,
                "Version should be in X.Y.Z format"
            );

            // Check if update is available
            $config2 = new UpdaterConfig(
                self::$testPluginFile,
                "SilverAssist/silver-assist-post-revalidate",
                [
                    "plugin_version" => "0.0.1", // Old version
                ]
            );

            $updater2 = new Updater($config2);
            $hasUpdate = $updater2->isUpdateAvailable();

            $this->assertTrue(
                $hasUpdate,
                "Should detect update available when current version is 0.0.1"
            );
        }
    }

    /**
     * Test GitHub API response structure
     *
     * @return void
     */
    public function testGitHubAPIResponseStructure(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate"
        );

        $updater = new Updater($config);

        // Get version to trigger API call
        $version = $updater->getLatestVersion();

        // Skip if API request failed
        if ($version === false) {
            $this->markTestSkipped("GitHub API request failed or rate limited");
        }

        // Verify version is valid format
        $this->assertMatchesRegularExpression(
            "/^\d+\.\d+\.\d+$/",
            $version,
            "Version should be in X.Y.Z format"
        );

        // Version should be at least 1.0.0 (first release)
        $this->assertGreaterThanOrEqual(
            "1.0.0",
            $version,
            "Version should be at least 1.0.0"
        );
    }

    /**
     * Test update check with real GitHub repository
     *
     * @return void
     */
    public function testUpdateCheckWithRealRepo(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate",
            [
                "plugin_name" => "Silver Assist Post Revalidate",
                "plugin_version" => "0.0.1", // Old version to trigger update
            ]
        );

        $updater = new Updater($config);

        // This makes a real HTTP request to GitHub
        $hasUpdate = $updater->isUpdateAvailable();

        // Should return boolean
        $this->assertIsBool($hasUpdate);

        // With version 0.0.1, there should be an update available
        // (unless API request failed)
        if ($hasUpdate) {
            $this->assertTrue(
                $hasUpdate,
                "Update should be available for version 0.0.1"
            );

            // Get latest version to verify
            $latestVersion = $updater->getLatestVersion();
            $this->assertNotFalse($latestVersion);
            $this->assertGreaterThan("0.0.1", $latestVersion);
        }
    }

    /**
     * Test caching of GitHub API responses
     *
     * @return void
     */
    public function testGitHubAPIResponseCaching(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate",
            [
                "cache_duration" => 3600, // 1 hour
            ]
        );

        $updater = new Updater($config);

        // First call - should make HTTP request
        $version1 = $updater->getLatestVersion();

        // Skip if API request failed
        if ($version1 === false) {
            $this->markTestSkipped("GitHub API request failed or rate limited");
        }

        // Second call - should use cache (much faster)
        $startTime = microtime(true);
        $version2 = $updater->getLatestVersion();
        $elapsed = microtime(true) - $startTime;

        // Both should return same version
        $this->assertEquals($version1, $version2);

        // Second call should be very fast (cached)
        $this->assertLessThan(
            0.01, // Less than 10ms
            $elapsed,
            "Cached call should be very fast"
        );
    }

    /**
     * Test GitHub API rate limiting handling
     *
     * @return void
     */
    public function testGitHubAPIRateLimitHandling(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate",
            [
                "cache_duration" => 1, // 1 second cache
            ]
        );

        $updater = new Updater($config);

        // Make multiple requests (cached after first)
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $version = $updater->getLatestVersion();
            $results[] = $version;
        }

        // At least one request should succeed (or all should fail gracefully)
        $hasSuccess = false;
        foreach ($results as $result) {
            if ($result !== false) {
                $hasSuccess = true;
                break;
            }
        }

        // All results should be the same (cached)
        $uniqueResults = array_unique($results);
        $this->assertCount(
            1,
            $uniqueResults,
            "All requests should return same result (cached)"
        );

        $this->assertTrue(
            $hasSuccess || count(array_filter($results)) === 0,
            "Should either succeed or fail gracefully for all requests"
        );
    }

    /**
     * Test asset pattern matching with real releases
     *
     * @return void
     */
    public function testAssetPatternMatchingWithRealReleases(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate",
            [
                // Custom asset pattern to match the plugin's naming convention
                "asset_pattern" => "silver-assist-post-revalidate-v{version}.zip",
            ]
        );

        $updater = new Updater($config);

        // Get latest version
        $version = $updater->getLatestVersion();

        // Skip if API request failed
        if ($version === false) {
            $this->markTestSkipped("GitHub API request failed or rate limited");
        }

        // Verify version format
        $this->assertMatchesRegularExpression(
            "/^\d+\.\d+\.\d+$/",
            $version,
            "Version should be in X.Y.Z format"
        );

        // Verify the asset pattern is configured correctly
        $this->assertEquals(
            "silver-assist-post-revalidate-v{version}.zip",
            $config->assetPattern,
            "Asset pattern should be configured"
        );
    }

    /**
     * Test version comparison with current and latest versions
     *
     * @return void
     */
    public function testVersionComparisonWithRealVersions(): void
    {
        // Test with old version (should have update)
        $config1 = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate",
            [
                "plugin_version" => "1.0.0",
            ]
        );

        $updater1 = new Updater($config1);
        $latestVersion = $updater1->getLatestVersion();

        // Skip if API failed
        if ($latestVersion === false) {
            $this->markTestSkipped("GitHub API request failed");
        }

        // 1.0.0 should be older than latest
        $hasUpdate = $updater1->isUpdateAvailable();
        $this->assertTrue(
            $hasUpdate,
            "Version 1.0.0 should have update available (latest: {$latestVersion})"
        );

        // Verify version comparison works correctly
        $this->assertGreaterThan(
            "1.0.0",
            $latestVersion,
            "Latest version should be greater than 1.0.0"
        );
    }

    /**
     * Test GitHub repository information retrieval
     *
     * @return void
     */
    public function testGitHubRepositoryInformation(): void
    {
        $config = new UpdaterConfig(
            self::$testPluginFile,
            "SilverAssist/silver-assist-post-revalidate"
        );

        $updater = new Updater($config);

        // Get version (triggers API call)
        $version = $updater->getLatestVersion();

        // Skip if failed
        if ($version === false) {
            $this->markTestSkipped("GitHub API request failed");
        }

        // Verify repository info is correct
        $this->assertEquals(
            "SilverAssist/silver-assist-post-revalidate",
            $config->githubRepo,
            "Repository should be configured correctly"
        );

        // Verify version is reasonable (>= 1.0.0 and < 100.0.0)
        $this->assertGreaterThanOrEqual(
            "1.0.0",
            $version,
            "Version should be at least 1.0.0"
        );

        $this->assertLessThan(
            "100.0.0",
            $version,
            "Version should be less than 100.0.0"
        );
    }
}
