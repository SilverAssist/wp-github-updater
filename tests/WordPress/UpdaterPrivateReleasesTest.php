<?php

/**
 * Tests for the updater against a private GitHub repository
 *
 * These tests run on the real WordPress Test Suite. Nothing from WordPress core is mocked:
 * outgoing requests are intercepted with WordPress' own `pre_http_request` filter, which is
 * the supported way to script the HTTP API.
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
use WP_Error;
use WP_UnitTestCase;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

/**
 * Behaviour of the updater against a private repository
 *
 * Covers the token header, the asset API URL, the two-step download that keeps the token away
 * from the storage host, and the log messages that say why a site stopped updating.
 */
class UpdaterPrivateReleasesTest extends WP_UnitTestCase
{
    private const TOKEN_NAME = "WPGU_TEST_PRIVATE_TOKEN";
    private const TOKEN = "test-secret-token-value";
    private const REPO = "owner/private-repo";
    private const ASSET_API_URL = "https://api.github.com/repos/owner/private-repo/releases/assets/123";
    private const ASSET_BROWSER_URL = "https://github.com/owner/private-repo/releases/download/v2.0.0/plugin.zip";
    private const SIGNED_URL = "https://objects.githubusercontent.com/signed/abc?sig=xyz";

    private string $pluginFile;
    private string $tempDir;
    private string $logFile;
    private string|false $previousErrorLog = false;

    /**
     * Requests the HTTP API was asked to make, in order.
     *
     * @var array<int, array{url: string, args: array<string, mixed>}>
     */
    private array $requests = [];

    /**
     * Responses handed out by the `pre_http_request` filter, first in first out.
     *
     * @var array<int, array<string, mixed>|WP_Error>
     */
    private array $queue = [];

    /**
     * Create a plugin fixture, a temp directory and a log file, and intercept the HTTP API.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . "/wpgu-private-" . uniqid();
        mkdir($this->tempDir . "/plugin-folder", 0777, true);
        $this->pluginFile = $this->tempDir . "/plugin-folder/plugin.php";
        file_put_contents($this->pluginFile, "<?php\n/*\nPlugin Name: Test Plugin\nVersion: 1.0.0\n*/");

        $this->logFile = $this->tempDir . "/php-error.log";
        $this->previousErrorLog = ini_set("error_log", $this->logFile);

        putenv(self::TOKEN_NAME);
        $this->requests = [];
        $this->queue = [];

        add_filter("pre_http_request", [$this, "interceptRequest"], 10, 3);
    }

    /**
     * Remove the token, restore the log target and delete the temp files.
     *
     * @return void
     */
    public function tearDown(): void
    {
        remove_filter("pre_http_request", [$this, "interceptRequest"], 10);
        putenv(self::TOKEN_NAME);
        if ($this->previousErrorLog !== false) {
            ini_set("error_log", $this->previousErrorLog);
        }
        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    /**
     * Record the request and answer it from the queue, without touching the network.
     *
     * @param false|array<string, mixed>|WP_Error $preempt    Response so far, false to let the request go out.
     * @param array<string, mixed>                $parsedArgs Request arguments as the HTTP API parsed them.
     * @param string                              $url        URL requested.
     * @return array<string, mixed>|WP_Error The next queued response
     */
    public function interceptRequest($preempt, array $parsedArgs, string $url)
    {
        $this->requests[] = ["url" => $url, "args" => $parsedArgs];

        return array_shift($this->queue) ?? new WP_Error("no_response", "No response was queued");
    }

    /**
     * Build an updater for the private repository.
     *
     * @param boolean $withToken Whether the token is present in the environment.
     * @return Updater
     */
    private function makeUpdater(bool $withToken): Updater
    {
        if ($withToken) {
            putenv(self::TOKEN_NAME . "=" . self::TOKEN);
        }

        $config = new UpdaterConfig($this->pluginFile, self::REPO, [
            "plugin_name" => "Test Plugin",
            "token_constant" => self::TOKEN_NAME,
            "custom_temp_dir" => $this->tempDir . "/tmp",
        ]);

        return new Updater($config);
    }

    /**
     * Queue a response shaped like the one the HTTP API returns.
     *
     * @param integer               $code    HTTP status code.
     * @param string                $body    Response body.
     * @param array<string, string> $headers Response headers.
     * @return void
     */
    private function queueResponse(int $code, string $body = "", array $headers = []): void
    {
        $this->queue[] = [
            "headers" => new CaseInsensitiveDictionary($headers),
            "body" => $body,
            "response" => ["code" => $code, "message" => ""],
            "cookies" => [],
            "filename" => null,
        ];
    }

    /**
     * JSON body of a release with one ZIP asset.
     *
     * @return string
     */
    private function releaseBody(): string
    {
        return (string) json_encode([
            "tag_name" => "v2.0.0",
            "assets" => [[
                "name" => "plugin.zip",
                "url" => self::ASSET_API_URL,
                "browser_download_url" => self::ASSET_BROWSER_URL,
            ]],
        ]);
    }

    /**
     * Read a header of a recorded request, whatever its case.
     *
     * @param integer $index  Request position, starting at 0.
     * @param string  $header Header name.
     * @return string|null The header value, or null when the request did not send it
     */
    private function headerOf(int $index, string $header): ?string
    {
        foreach ($this->requests[$index]["args"]["headers"] ?? [] as $name => $value) {
            if (strtolower((string) $name) === strtolower($header)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Read what PHP wrote to the error log.
     *
     * @return string
     */
    private function loggedErrors(): string
    {
        return file_exists($this->logFile) ? (string) file_get_contents($this->logFile) : "";
    }

    /**
     * The `plugin` value WordPress passes to the download filter for this plugin.
     *
     * @return array<string, string>
     */
    private function hookExtra(): array
    {
        return ["plugin" => plugin_basename($this->pluginFile)];
    }

    /**
     * Delete a directory tree.
     *
     * @param string $dir Directory to delete.
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff((array) scandir($dir), [".", ".."]) as $entry) {
            $path = $dir . "/" . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Without a token the API requests stay anonymous, as before.
     *
     * @return void
     */
    public function testApiRequestsAreAnonymousWithoutAToken(): void
    {
        $updater = $this->makeUpdater(false);
        $this->queueResponse(200, $this->releaseBody());

        $this->assertSame("2.0.0", $updater->getLatestVersion());
        $this->assertNull($this->headerOf(0, "Authorization"));
    }

    /**
     * With a token the API requests carry it as a Bearer credential.
     *
     * @return void
     */
    public function testApiRequestsCarryTheTokenWhenConfigured(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(200, $this->releaseBody());

        $updater->getLatestVersion();

        $this->assertSame("Bearer " . self::TOKEN, $this->headerOf(0, "Authorization"));
        $this->assertSame(
            "https://api.github.com/repos/" . self::REPO . "/releases/latest",
            $this->requests[0]["url"]
        );
    }

    /**
     * Anonymous access keeps using the browser download URL.
     *
     * @return void
     */
    public function testPackageUsesTheBrowserUrlWithoutAToken(): void
    {
        $updater = $this->makeUpdater(false);
        $this->queueResponse(200, $this->releaseBody());
        $this->queueResponse(200, $this->releaseBody());

        $transient = $updater->checkForUpdate((object) ["checked" => [plugin_basename($this->pluginFile) => "1.0.0"]]);

        $this->assertSame(self::ASSET_BROWSER_URL, $transient->response[plugin_basename($this->pluginFile)]->package);
    }

    /**
     * With a token the package is the API asset URL, the only one a private repository serves.
     *
     * @return void
     */
    public function testPackageUsesTheAssetApiUrlWithAToken(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(200, $this->releaseBody());
        $this->queueResponse(200, $this->releaseBody());

        $transient = $updater->checkForUpdate((object) ["checked" => [plugin_basename($this->pluginFile) => "1.0.0"]]);

        $this->assertSame(self::ASSET_API_URL, $transient->response[plugin_basename($this->pluginFile)]->package);
    }

    /**
     * The token goes to the API only. The signed storage URL it redirects to never receives it.
     *
     * @return void
     */
    public function testDownloadFollowsTheRedirectWithoutSendingTheToken(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(302, "", ["location" => self::SIGNED_URL]);
        $this->queueResponse(200, str_repeat("PK-zip-bytes", 20));

        $result = $updater->maybeFixDownload(false, self::ASSET_API_URL, new \stdClass(), $this->hookExtra());

        $this->assertIsString($result);
        $this->assertSame(str_repeat("PK-zip-bytes", 20), file_get_contents($result));

        $this->assertCount(2, $this->requests);
        $this->assertSame(self::ASSET_API_URL, $this->requests[0]["url"]);
        $this->assertSame("Bearer " . self::TOKEN, $this->headerOf(0, "Authorization"));
        $this->assertSame(0, $this->requests[0]["args"]["redirection"]);
        $this->assertSame("application/octet-stream", $this->headerOf(0, "Accept"));

        $this->assertSame(self::SIGNED_URL, $this->requests[1]["url"]);
        $this->assertNull($this->headerOf(1, "Authorization"));
        $this->assertNotSame(0, $this->requests[1]["args"]["redirection"]);
    }

    /**
     * The token is never attached to a browser URL, even when one is configured.
     *
     * @return void
     */
    public function testTokenIsNotSentToTheBrowserDownloadUrl(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(200, str_repeat("PK-zip-bytes", 20));

        $updater->maybeFixDownload(false, self::ASSET_BROWSER_URL, new \stdClass(), $this->hookExtra());

        $this->assertCount(1, $this->requests);
        $this->assertNull($this->headerOf(0, "Authorization"));
        $this->assertNotSame(0, $this->requests[0]["args"]["redirection"]);
    }

    /**
     * A redirect that is not https is refused and never requested.
     *
     * @return void
     */
    public function testInsecureRedirectIsRefused(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(302, "", ["location" => "http://example.test/asset.zip"]);

        $result = $updater->maybeFixDownload(false, self::ASSET_API_URL, new \stdClass(), $this->hookExtra());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame("invalid_redirect", $result->get_error_code());
        $this->assertCount(1, $this->requests);
    }

    /**
     * A 404 with a token points at repository access, and nothing written contains the token.
     *
     * @return void
     */
    public function testNotFoundWithATokenPointsAtRepositoryAccess(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(404);

        $result = $updater->maybeFixDownload(false, self::ASSET_API_URL, new \stdClass(), $this->hookExtra());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame("http_error", $result->get_error_code());
        $this->assertStringContainsString("HTTP code 404", $result->get_error_message());
        $this->assertStringContainsString("may not have access", $result->get_error_message());
        $this->assertStringContainsString("token has no access", $this->loggedErrors());
        $this->assertStringNotContainsString(self::TOKEN, $this->loggedErrors());
        $this->assertStringNotContainsString(self::TOKEN, $result->get_error_message());
    }

    /**
     * A 401 says the token was rejected and names the setting to check.
     *
     * @return void
     */
    public function testRejectedTokenIsNamedInTheError(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(401);

        $result = $updater->maybeFixDownload(false, self::ASSET_API_URL, new \stdClass(), $this->hookExtra());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertStringContainsString("rejected the token", $result->get_error_message());
        $this->assertStringContainsString(self::TOKEN_NAME, $result->get_error_message());
        $this->assertStringContainsString("rejected the token", $this->loggedErrors());
    }

    /**
     * A 404 with no token hints that a private repository needs one.
     *
     * @return void
     */
    public function testNotFoundWithoutATokenHintsAtPrivateRepositories(): void
    {
        $updater = $this->makeUpdater(false);
        $this->queueResponse(404);

        $this->assertFalse($updater->getLatestVersion());

        $log = $this->loggedErrors();
        $this->assertStringContainsString(self::REPO, $log);
        $this->assertStringContainsString("If the repository is private", $log);
        $this->assertStringContainsString(self::TOKEN_NAME, $log);
    }

    /**
     * A failed version lookup is not cached, so the next check asks again.
     *
     * @return void
     */
    public function testFailedLookupIsNotCached(): void
    {
        $updater = $this->makeUpdater(true);
        $this->queueResponse(401);
        $this->queueResponse(200, $this->releaseBody());

        $this->assertFalse($updater->getLatestVersion());
        $this->assertSame("2.0.0", $updater->getLatestVersion());
        $this->assertCount(2, $this->requests);
    }

    /**
     * A transient that is not an object is passed through untouched.
     *
     * @return void
     */
    public function testCheckForUpdateLeavesANonObjectTransientAlone(): void
    {
        $updater = $this->makeUpdater(false);

        $this->assertFalse($updater->checkForUpdate(false));
        $this->assertCount(0, $this->requests);
    }
}
