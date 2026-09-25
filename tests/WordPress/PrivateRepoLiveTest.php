<?php

/**
 * Live test of the updater against a real private GitHub repository
 *
 * Opt-in: the tests are skipped unless the environment names a private repository that has a
 * release with a ZIP asset, and provides a token that can read it. The `external-http` group
 * is also excluded by the WordPress Test Suite unless it is asked for explicitly.
 *
 *   WPGU_LIVE_REPO=owner/private-repo WPGU_LIVE_VERSION=1.2.3 SILVER_GITHUB_TOKEN=... \
 *     vendor/bin/phpunit --testsuite wordpress --group external-http
 *
 * NOTE: These tests are ONLY loaded when WordPress Test Suite is available.
 *
 * @package SilverAssist\WpGithubUpdater\Tests\WordPress
 */

namespace SilverAssist\WpGithubUpdater\Tests\WordPress;

// Only load tests if WordPress Test Suite is available
if (!class_exists("WP_UnitTestCase")) {
    return;
}

use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Private repository tests against the real GitHub API
 *
 * These check what a test double cannot: that GitHub really serves a private release to the
 * token, and that the signed storage URL it redirects the asset download to accepts the
 * request the updater sends (it rejects one that still carries the Authorization header).
 *
 * @group external-http
 */
class PrivateRepoLiveTest extends WP_UnitTestCase
{
    private string $repo = "";
    private string $version = "";
    private string $pluginFile;
    private string $tempDir;
    private string|false $previousToken = false;

    /**
     * Skip unless the environment describes a private repository, and prepare a plugin fixture.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->repo = (string) getenv("WPGU_LIVE_REPO");
        $this->version = (string) getenv("WPGU_LIVE_VERSION");
        $this->previousToken = getenv("SILVER_GITHUB_TOKEN");

        if ($this->repo === "" || $this->version === "" || $this->previousToken === false) {
            $this->markTestSkipped("Set WPGU_LIVE_REPO, WPGU_LIVE_VERSION and SILVER_GITHUB_TOKEN to run.");
        }

        $this->tempDir = sys_get_temp_dir() . "/wpgu-live-" . uniqid();
        mkdir($this->tempDir . "/live-plugin", 0777, true);
        $this->pluginFile = $this->tempDir . "/live-plugin/live-plugin.php";
        file_put_contents($this->pluginFile, "<?php\n/*\nPlugin Name: Live Plugin\nVersion: 0.0.1\n*/");
    }

    /**
     * Restore the token and delete the temp files.
     *
     * @return void
     */
    public function tearDown(): void
    {
        if ($this->previousToken !== false) {
            putenv("SILVER_GITHUB_TOKEN=" . $this->previousToken);
        }
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Build an updater for the live repository.
     *
     * @return Updater
     */
    private function makeUpdater(): Updater
    {
        return new Updater(new UpdaterConfig($this->pluginFile, $this->repo, [
            "plugin_name" => "Live Plugin",
            "custom_temp_dir" => $this->tempDir . "/tmp",
        ]));
    }

    /**
     * Delete a directory tree.
     *
     * @param string $dir Directory to delete.
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        foreach (array_diff((array) scandir($dir), [".", ".."]) as $entry) {
            $path = $dir . "/" . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * The token reads the latest release of the private repository.
     *
     * @return void
     */
    public function testLatestVersionIsReadFromThePrivateRepository(): void
    {
        $this->assertSame($this->version, $this->makeUpdater()->getLatestVersion());
    }

    /**
     * The asset is downloaded through the signed redirect and is a real ZIP.
     *
     * @return void
     */
    public function testAssetIsDownloadedThroughTheSignedRedirect(): void
    {
        $updater = $this->makeUpdater();
        $slug = plugin_basename($this->pluginFile);

        $transient = $updater->checkForUpdate((object) ["checked" => [$slug => "0.0.1"]]);
        $package = $transient->response[$slug]->package;
        $this->assertStringStartsWith("https://api.github.com/repos/{$this->repo}/releases/assets/", $package);

        $file = $updater->maybeFixDownload(false, $package, new \stdClass(), ["plugin" => $slug]);

        $this->assertIsString($file, is_wp_error($file) ? $file->get_error_message() : "");
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($file) === true, "The downloaded file is not a valid ZIP");
        $this->assertGreaterThan(0, $zip->numFiles);
        $zip->close();
    }

    /**
     * Without the token the private repository is not reachable, and the log says why.
     *
     * @return void
     */
    public function testWithoutTheTokenThePrivateRepositoryIsNotReachable(): void
    {
        putenv("SILVER_GITHUB_TOKEN");
        $logFile = $this->tempDir . "/php-error.log";
        $previous = ini_set("error_log", $logFile);

        try {
            $this->assertFalse($this->makeUpdater()->getLatestVersion());
            $this->assertStringContainsString("If the repository is private", (string) file_get_contents($logFile));
        } finally {
            if ($previous !== false) {
                ini_set("error_log", $previous);
            }
        }
    }
}
