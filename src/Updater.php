<?php

/**
 * WordPress GitHub Updater
 *
 * A reusable WordPress plugin updater that handles automatic updates from public GitHub releases.
 *
 * @package SilverAssist\WpGithubUpdater
 * @author Silver Assist
 * @version 1.1.3
 * @license PolyForm-Noncommercial-1.0.0
 */

namespace SilverAssist\WpGithubUpdater;

/**
 * Main updater class that handles plugin updates from GitHub releases
 *
 * This class integrates with WordPress update system to provide automatic
 * updates from GitHub releases. It handles version checking, plugin information
 * display, and the actual update process.
 *
 * @package SilverAssist\WpGithubUpdater
 * @since   1.0.0
 */
class Updater
{
    /**
     * Updater configuration
     *
     * @var UpdaterConfig Configuration object with all updater settings
     * @since 1.0.0
     */
    private UpdaterConfig $config;

    /**
     * Plugin slug (folder/file.php)
     *
     * @var string WordPress plugin slug identifier
     * @since 1.0.0
     */
    private string $pluginSlug;

    /**
     * Plugin basename (folder name only)
     *
     * @var string Plugin directory name without file extension
     * @since 1.0.0
     */
    private string $pluginBasename;

    /**
     * Current plugin version
     *
     * @var string Current version of the plugin being updated
     * @since 1.0.0
     */
    private string $currentVersion;

    /**
     * Plugin data from header
     *
     * @var array Plugin metadata extracted from plugin file header
     * @since 1.0.0
     */
    private array $pluginData;

    /**
     * Transient name for version cache
     *
     * @var string WordPress transient key for caching version information
     * @since 1.0.0
     */
    private string $versionTransient;

    /**
     * Initialize the updater
     *
     * Sets up plugin identification, version information and WordPress hooks.
     *
     * @param UpdaterConfig $config Updater configuration object
     *
     * @since 1.0.0
     */
    public function __construct(UpdaterConfig $config)
    {
        $this->config = $config;
        $this->pluginSlug = \plugin_basename($config->pluginFile);
        $this->pluginBasename = dirname($this->pluginSlug);
        $this->versionTransient = "{$this->pluginBasename}_version_check";

        // Get plugin data
        $this->pluginData = $this->getPluginData();
        $this->currentVersion = $this->pluginData["Version"] ?? "1.0.0";

        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * Sets up filters and actions needed for WordPress update system integration.
     *
     *
     * @since 1.0.0
     */
    private function initHooks(): void
    {
        \add_filter("pre_set_site_transient_update_plugins", [$this, "checkForUpdate"]);
        \add_filter("plugins_api", [$this, "pluginInfo"], 20, 3);
        \add_action("upgrader_process_complete", [$this, "clearVersionCache"], 10, 2);

        // Improve download reliability
        \add_filter("upgrader_pre_download", [$this, "maybeFixDownload"], 10, 4);

        // Add AJAX action for manual version check
        \add_action("wp_ajax_{$this->config->ajaxAction}", [$this, "manualVersionCheck"]);
    }

    /**
     * Check for plugin updates
     *
     * Compares the current plugin version with the latest GitHub release
     * and adds update information to the WordPress update transient if needed.
     *
     * @param mixed $transient The update_plugins transient containing current plugin versions
     * @return mixed The modified transient with update information added if available
     *
     * @since 1.0.0
     */
    public function checkForUpdate($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $latestVersion = $this->getLatestVersion();

        if ($latestVersion && version_compare($this->currentVersion, $latestVersion, "<")) {
            $transient->response[$this->pluginSlug] = (object) [
                "slug" => $this->pluginBasename,
                "plugin" => $this->pluginSlug,
                "new_version" => $latestVersion,
                "url" => $this->config->pluginHomepage,
                "package" => $this->getDownloadUrl($latestVersion),
                "tested" => \get_bloginfo("version"),
                "requires_php" => $this->config->requiresPHP,
                "compatibility" => new \stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Get plugin information for the update API
     *
     * Provides detailed plugin information when WordPress requests it,
     * including version, changelog, and download information.
     *
     * @param false|object|array $result The result object or array
     * @param string             $action The type of information being requested
     * @param object             $args   Plugin API arguments
     * @return false|object|array Plugin information object or original result
     *
     * @since 1.0.0
     */
    public function pluginInfo(false|object|array $result, string $action, object $args): false|object|array
    {
        if ($action !== "plugin_information" || $args->slug !== $this->pluginBasename) {
            return $result;
        }

        $latestVersion = $this->getLatestVersion();
        $changelog = $this->getChangelog();

        return (object) [
            "slug" => $this->pluginBasename,
            "plugin" => $this->pluginSlug,
            "version" => $latestVersion ?: $this->currentVersion,
            "author" => $this->config->pluginAuthor,
            "author_profile" => $this->config->pluginHomepage,
            "requires" => $this->config->requiresWordPress,
            "tested" => \get_bloginfo("version"),
            "requires_php" => $this->config->requiresPHP,
            "name" => $this->config->pluginName,
            "homepage" => $this->config->pluginHomepage,
            "sections" => [
                "description" => $this->config->pluginDescription,
                "changelog" => $changelog,
            ],
            "download_link" => $this->getDownloadUrl($latestVersion),
            "last_updated" => $this->getLastUpdated(),
        ];
    }

    /**
     * Get latest version from GitHub
     *
     * Fetches the latest release version from GitHub API with caching support.
     *
     * @return string|false Latest version string or false if failed
     *
     * @since 1.0.0
     */
    public function getLatestVersion(): string|false
    {
        // Check cache first
        $cachedVersion = \get_transient($this->versionTransient);
        if ($cachedVersion !== false) {
            return $cachedVersion;
        }

        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases/latest";
        $response = \wp_remote_get($apiUrl, [
            "timeout" => 15,
            "headers" => $this->getApiHeaders(),
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            error_log("WP GitHub Updater: Failed to fetch latest version for {$this->config->githubRepo}");
            return false;
        }

        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data["tag_name"])) {
            return false;
        }

        $version = ltrim($data["tag_name"], "v");

        // Cache the version
        \set_transient($this->versionTransient, $version, $this->config->cacheDuration);

        return $version;
    }

    /**
     * Get download URL for a specific version
     *
     * @param string $version The version to download
     *
     * @since 1.0.0
     */
    private function getDownloadUrl(string $version): string
    {
        // First try to get the actual download URL from the release assets
        $downloadUrl = $this->getAssetDownloadUrl($version);
        if ($downloadUrl) {
            return $downloadUrl;
        }

        // Fallback to constructed URL
        $pattern = $this->config->assetPattern;
        $filename = str_replace(
            ["{slug}", "{version}"],
            [$this->pluginBasename, $version],
            $pattern
        );

        return "https://github.com/{$this->config->githubRepo}/releases/download/v{$version}/{$filename}";
    }

    /**
     * Get actual asset download URL from GitHub API
     *
     * @param string $version The version to get asset URL for
     * @return string|null Asset download URL or null if not found
     *
     * @since 1.1.0
     */
    private function getAssetDownloadUrl(string $version): ?string
    {
        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases/tags/v{$version}";

        $response = \wp_remote_get($apiUrl, [
            "timeout" => 10,
            "headers" => $this->getApiHeaders(),
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            return null;
        }

        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data["assets"]) || empty($data["assets"])) {
            return null;
        }

        // Look for the ZIP asset
        foreach ($data["assets"] as $asset) {
            if (str_ends_with($asset["name"], ".zip")) {
                return $asset["browser_download_url"];
            }
        }

        return null;
    }

    /**
     * Get changelog from GitHub releases
     *
     * Fetches release notes from GitHub API and formats them as HTML.
     *
     * @return string Formatted changelog HTML
     *
     * @since 1.0.0
     */
    private function getChangelog(): string
    {
        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases";
        $response = \wp_remote_get($apiUrl, [
            "timeout" => 15,
            "headers" => $this->getApiHeaders(),
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            $github_link = "<a href=\"https://github.com/{$this->config->githubRepo}/releases\">"
                . $this->config->__("GitHub releases page") . "</a>";
            return sprintf(
                $this->config->__("Unable to fetch changelog. Visit the %s for updates."),
                $github_link
            );
        }

        $body = \wp_remote_retrieve_body($response);
        $releases = json_decode($body, true);

        if (!is_array($releases)) {
            return $this->config->__("Unable to parse changelog.");
        }

        $changelog = "";
        foreach (array_slice($releases, 0, 5) as $release) { // Show last 5 releases
            $version = ltrim($release["tag_name"], "v");
            $date = date("Y-m-d", strtotime($release["published_at"]));
            $body = $release["body"] ?: $this->config->__("No release notes provided.");

            $changelog .= sprintf(
                "<h4>%s</h4>\n",
                sprintf($this->config->__("Version %1\$s (%2\$s)"), $version, $date)
            );
            $changelog .= "<div>" . \wp_kses_post($this->parseMarkdownToHtml($body)) . "</div>\n\n";
        }

        return $changelog ?: $this->config->__("No changelog available.");
    }

    /**
     * Get last updated date
     *
     * Fetches the publication date of the latest release from GitHub API.
     *
     * @return string Last updated date in Y-m-d format
     *
     * @since 1.0.0
     */
    private function getLastUpdated(): string
    {
        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases/latest";
        $response = \wp_remote_get($apiUrl, [
            "timeout" => 15,
            "headers" => $this->getApiHeaders(),
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            return date("Y-m-d");
        }

        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data["published_at"])) {
            return date("Y-m-d");
        }

        return date("Y-m-d", strtotime($data["published_at"]));
    }

    /**
     * Clear version cache after update
     *
     * @param \WP_Upgrader $upgrader WP_Upgrader instance
     * @param array        $data     Array of update data
     */
    public function clearVersionCache(\WP_Upgrader $upgrader, array $data): void
    {
        if ($data["action"] === "update" && $data["type"] === "plugin") {
            if (isset($data["plugins"]) && in_array($this->pluginSlug, $data["plugins"])) {
                \delete_transient($this->versionTransient);
            }
        }
    }

    /**
     * Manual version check via AJAX
     */
    public function manualVersionCheck(): void
    {
        // Verify nonce
        $nonce = \sanitize_text_field(\wp_unslash($_POST["nonce"] ?? ""));
        if (!\wp_verify_nonce($nonce, $this->config->ajaxNonce)) {
            \wp_send_json_error([
                "message" => $this->config->__("Security check failed"),
                "code" => "invalid_nonce"
            ]);
        }

        if (!\current_user_can("update_plugins")) {
            \wp_send_json_error([
                "message" => $this->config->__("Insufficient permissions"),
                "code" => "insufficient_permissions"
            ]);
        }

        try {
            \delete_transient($this->versionTransient);
            $latestVersion = $this->getLatestVersion();

            \wp_send_json_success([
                "current_version" => $this->currentVersion,
                "latest_version" => $latestVersion ?: $this->config->__("Unknown"),
                "update_available" => $latestVersion && version_compare($this->currentVersion, $latestVersion, "<"),
                "github_repo" => $this->config->githubRepo,
            ]);
        } catch (\Exception $e) {
            \wp_send_json_error([
                "message" => sprintf($this->config->__("Error checking for updates: %s"), $e->getMessage()),
                "code" => "version_check_failed"
            ]);
        }
    }

    /**
     * Get plugin data from file
     */
    private function getPluginData(): array
    {
        if (!\function_exists("get_plugin_data")) {
            require_once ABSPATH . "wp-admin/includes/plugin.php";
        }

        return \get_plugin_data($this->config->pluginFile);
    }

    /**
     * Get current version
     *
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    /**
     * Get GitHub repository
     *
     */
    public function getGithubRepo(): string
    {
        return $this->config->githubRepo;
    }

    /**
     * Check if update is available
     *
     */
    public function isUpdateAvailable(): bool
    {
        $latestVersion = $this->getLatestVersion();
        return $latestVersion && version_compare($this->currentVersion, $latestVersion, "<");
    }

    /**
     * Parse Markdown to HTML
     *
     * Converts basic Markdown syntax to HTML for better changelog display.
     * Supports headers, bold text, italic text, inline code, lists, and links.
     *
     * @param string $markdown Markdown content to convert
     * @return string HTML formatted content
     *
     * @since 1.0.1
     */
    private function parseMarkdownToHtml(string $markdown): string
    {
        // Basic markdown to HTML conversion
        $html = $markdown;

        // Headers (# -> h2, ## -> h3, ### -> h4, #### -> h5)
        $html = preg_replace("/^#### (.*$)/m", "<h5>$1</h5>", $html);
        $html = preg_replace("/^### (.*$)/m", "<h4>$1</h4>", $html);
        $html = preg_replace("/^## (.*$)/m", "<h3>$1</h3>", $html);
        $html = preg_replace("/^# (.*$)/m", "<h2>$1</h2>", $html);

        // Bold text (**text** -> <strong>text</strong>)
        $html = preg_replace("/\*\*(.*?)\*\*/", "<strong>$1</strong>", $html);

        // Italic text (*text* -> <em>text</em>)
        $html = preg_replace("/(?<!\*)\*([^*]+)\*(?!\*)/", "<em>$1</em>", $html);

        // Code blocks (`code` -> <code>code</code>)
        $html = preg_replace("/`([^`]+)`/", "<code>$1</code>", $html);

        // Unordered lists (- item -> <ul><li>item</li></ul>)
        $html = preg_replace_callback("/(?:^- (.+)(?:\n|$))+/m", function ($matches) {
            $items = preg_split("/\n- /", trim($matches[0]));
            $items[0] = ltrim($items[0], "- ");
            $liItems = array_map(fn($item) => "<li>" . trim($item) . "</li>", array_filter($items));
            return "<ul>" . implode("", $liItems) . "</ul>";
        }, $html);

        // Links ([text](url) -> <a href="url">text</a>)
        $html = preg_replace("/\[([^\]]+)\]\(([^)]+)\)/", "<a href=\"$2\">$1</a>", $html);

        // Line breaks (double newline -> <br>)
        $html = preg_replace("/\n\s*\n/", "<br>", $html);
        $html = preg_replace("/\n/", "<br>", $html);

        // Clean up extra line breaks and spaces
        $html = preg_replace("/(<br>\s*){3,}/", "<br>", $html);
        $html = trim($html);

        return $html;
    }

    /**
     * Maybe fix download issues by providing better HTTP args
     *
     * @return boolean|\WP_Error $result
     *
     * @since 1.1.0
     */
    public function maybeFixDownload(
        bool|\WP_Error $result,
        string $package,
        object $upgrader,
        array $hook_extra
    ): bool|\WP_Error {
        // Only handle GitHub downloads for our plugin
        if (!str_contains($package, "github.com") || !str_contains($package, $this->config->githubRepo)) {
            return $result;
        }

        // Use wp_remote_get with better parameters
        $args = [
            "timeout" => 300, // 5 minutes
            "headers" => $this->getDownloadHeaders(),
            "sslverify" => true,
            "stream" => false,
            "filename" => null,
        ];

        $response = \wp_remote_get($package, $args);

        if (\is_wp_error($response)) {
            return $response;
        }

        if (200 !== \wp_remote_retrieve_response_code($response)) {
            return new \WP_Error("http_404", $this->config->__("Package not found"));
        }

        // Try multiple approaches for creating temporary file to avoid PCLZIP errors
        $temp_file = $this->createSecureTempFile($package);

        if (\is_wp_error($temp_file)) {
            return $temp_file;
        }

        $file_handle = @fopen($temp_file, "wb");
        if (!$file_handle) {
            return new \WP_Error("file_open_failed", $this->config->__("Could not open file for writing"));
        }

        $body = \wp_remote_retrieve_body($response);
        $bytes_written = fwrite($file_handle, $body);
        fclose($file_handle);

        // Verify file was written correctly
        if ($bytes_written === false || $bytes_written !== strlen($body)) {
            @unlink($temp_file);
            return new \WP_Error("file_write_failed", $this->config->__("Could not write to temporary file"));
        }

        // Verify file exists and is readable
        if (!file_exists($temp_file) || !is_readable($temp_file)) {
            return new \WP_Error("file_verification_failed", $this->config->__("Temporary file verification failed"));
        }

        return $temp_file;
    }

    /**
     * Create a secure temporary file with multiple fallback strategies
     *
     * Attempts different approaches to create a temporary file to avoid PCLZIP errors
     * that can occur with restrictive /tmp directory permissions.
     *
     * @param string $package The package URL being downloaded
     * @return string|\WP_Error Path to temporary file or WP_Error on failure
     *
     * @since 1.1.3
     */
    private function createSecureTempFile(string $package): string|\WP_Error
    {
        $filename = basename(parse_url($package, PHP_URL_PATH)) ?: "github-package.zip";

        // Strategy 1: Use custom temporary directory if specified
        if (!empty($this->config->customTempDir)) {
            if (!is_dir($this->config->customTempDir)) {
                @wp_mkdir_p($this->config->customTempDir);
            }

            if (is_dir($this->config->customTempDir) && is_writable($this->config->customTempDir)) {
                $temp_file = \wp_tempnam($filename, $this->config->customTempDir . "/");
                if ($temp_file) {
                    return $temp_file;
                }
            }
        }

        // Strategy 2: Use WordPress uploads directory
        $upload_dir = \wp_upload_dir();
        if (!empty($upload_dir["basedir"]) && is_writable($upload_dir["basedir"])) {
            $temp_file = \wp_tempnam($filename, $upload_dir["basedir"] . "/");
            if ($temp_file) {
                return $temp_file;
            }
        }

        // Strategy 3: Use WP_CONTENT_DIR/temp if it exists or can be created
        $wp_content_temp = WP_CONTENT_DIR . "/temp";
        if (!is_dir($wp_content_temp)) {
            @wp_mkdir_p($wp_content_temp);
        }

        if (is_dir($wp_content_temp) && is_writable($wp_content_temp)) {
            $temp_file = \wp_tempnam($filename, $wp_content_temp . "/");
            if ($temp_file) {
                return $temp_file;
            }
        }

        // Strategy 4: Use WordPress temporary directory (if defined)
        if (defined("WP_TEMP_DIR") && is_dir(WP_TEMP_DIR) && is_writable(WP_TEMP_DIR)) {
            $temp_file = \wp_tempnam($filename, WP_TEMP_DIR . "/");
            if ($temp_file) {
                return $temp_file;
            }
        }

        // Strategy 5: Try system temp directory as last resort
        $temp_file = \wp_tempnam($filename);
        if ($temp_file) {
            return $temp_file;
        }

        // Strategy 6: Manual temp file creation in uploads dir
        if (!empty($upload_dir["basedir"])) {
            $manual_temp = $upload_dir["basedir"] . "/" . uniqid("wp_github_updater_", true) . ".tmp";
            $handle = @fopen($manual_temp, "w");
            if ($handle) {
                fclose($handle);
                return $manual_temp;
            }
        }

        return new \WP_Error(
            "temp_file_creation_failed",
            $this->config->__("Could not create temporary file. Please check directory permissions " .
                "or define WP_TEMP_DIR in wp-config.php")
        );
    }

    /**
     * Get headers for GitHub API requests
     *
     * Returns standard headers for GitHub API communication including
     * User-Agent and Accept headers for optimal API interaction.
     *
     * @return array<string, string> Array of HTTP headers
     *
     * @since 1.1.0
     */
    private function getApiHeaders(): array
    {
        return [
            "User-Agent" => "WP-GitHub-Updater/{$this->currentVersion}",
            "Accept" => "application/vnd.github.v3+json",
        ];
    }

    /**
     * Get headers for GitHub asset downloads
     *
     * Returns headers optimized for downloading GitHub release assets
     * including compression support and extended timeouts.
     *
     * @return array<string, string> Array of HTTP headers
     *
     * @since 1.1.0
     */
    private function getDownloadHeaders(): array
    {
        return [
            "User-Agent" => "WP-GitHub-Updater/{$this->currentVersion}",
            "Accept" => "application/octet-stream",
            "Accept-Encoding" => "gzip, deflate",
        ];
    }
}
