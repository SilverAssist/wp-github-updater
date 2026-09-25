<?php

/**
 * WordPress GitHub Updater
 *
 * A reusable WordPress plugin updater that handles automatic updates from GitHub releases,
 * public or private.
 *
 * @package SilverAssist\WpGithubUpdater
 * @author Silver Assist
 * @version 1.4.0
 * @license PolyForm-Noncommercial-1.0.0
 */

namespace SilverAssist\WpGithubUpdater;

use WP_Error;
use WP_Upgrader;

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
     * @var array<string, mixed> Plugin metadata extracted from plugin file header
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
     * @param UpdaterConfig $config Updater configuration object.
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
     * @return void
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

        // Add AJAX action for dismissing update notices
        \add_action("wp_ajax_{$this->config->ajaxAction}_dismiss_notice", [$this, "dismissUpdateNotice"]);

        // Add admin notice for manual version checks
        \add_action("admin_notices", [$this, "showUpdateNotice"]);
    }

    /**
     * Check for plugin updates
     *
     * Compares the current plugin version with the latest GitHub release
     * and adds update information to the WordPress update transient if needed.
     *
     * @param mixed $transient The update_plugins transient containing current plugin versions.
     * @return mixed The modified transient with update information added if available
     *
     * @since 1.0.0
     */
    public function checkForUpdate(mixed $transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        /** @var \stdClass $transient */
        $latestVersion = $this->getLatestVersion();

        if ($latestVersion !== false && $this->isUpdateAvailable()) {
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
     * @param false|object|array<string, mixed> $result The result object or array.
     * @param string                            $action The type of information being requested.
     * @param object                            $args   Plugin API arguments.
     * @return false|object|array<string, mixed> Plugin information object or original result
     *
     * @since 1.0.0
     */
    public function pluginInfo(false|object|array $result, string $action, object $args): false|object|array
    {
        if ($action !== "plugin_information" || !isset($args->slug) || $args->slug !== $this->pluginBasename) {
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
            "download_link" => $latestVersion !== false ? $this->getDownloadUrl($latestVersion) : "",
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
            $this->logRequestFailure("fetching the latest release", $response);
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
     * @param string $version The version to download.
     *
     * @since 1.0.0
     * @return string
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
     * @param string $version The version to get asset URL for.
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
            $this->logRequestFailure("looking up the release assets for v{$version}", $response);
            return null;
        }

        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data["assets"]) || empty($data["assets"])) {
            return null;
        }

        // Look for the ZIP asset
        foreach ($data["assets"] as $asset) {
            if (!str_ends_with($asset["name"], ".zip")) {
                continue;
            }

            // A private repository only serves the asset through the API URL (with the token).
            // The browser download URL cannot be authenticated, so keep it for anonymous access.
            if ($this->getToken() !== null && !empty($asset["url"])) {
                return $asset["url"];
            }

            return $asset["browser_download_url"];
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
     * @param WP_Upgrader          $upgrader WP_Upgrader instance.
     * @param array<string, mixed> $data     Array of update data.
     * @return void
     */
    public function clearVersionCache(WP_Upgrader $upgrader, array $data): void
    {
        if ($data["action"] === "update" && $data["type"] === "plugin") {
            if (isset($data["plugins"]) && in_array($this->pluginSlug, $data["plugins"])) {
                \delete_transient($this->versionTransient);
            }
        }
    }

    /**
     * Manual version check via AJAX
     * @return void
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
            // Clear our version cache
            \delete_transient($this->versionTransient);

            // Clear WordPress update cache to force refresh
            \delete_site_transient("update_plugins");

            $latestVersion = $this->getLatestVersion();
            $updateAvailable = $this->isUpdateAvailable();

            // If update is available, set a transient to show admin notice
            if ($updateAvailable) {
                \set_transient("wp_github_updater_notice_{$this->pluginBasename}", [
                    "plugin_name" => $this->config->pluginName,
                    "current_version" => $this->currentVersion,
                    "latest_version" => $latestVersion,
                    "github_repo" => $this->config->githubRepo,
                ], $this->config->cacheDuration); // Use same duration as version cache
            }

            \wp_send_json_success([
                "current_version" => $this->currentVersion,
                "latest_version" => $latestVersion ?: $this->config->__("Unknown"),
                "update_available" => $updateAvailable,
                "github_repo" => $this->config->githubRepo,
                "notice_set" => $updateAvailable, // Indicate if notice was set
            ]);
        } catch (\Exception $e) {
            \wp_send_json_error([
                "message" => sprintf($this->config->__("Error checking for updates: %s"), $e->getMessage()),
                "code" => "version_check_failed"
            ]);
        }
    }

    /**
     * Handle dismissal of update notices via AJAX
     *
     * @since 1.1.4
     * @return void
     */
    public function dismissUpdateNotice(): void
    {
        // Check nonce
        $nonce = \sanitize_text_field(\wp_unslash($_POST["nonce"] ?? ""));
        if (!\wp_verify_nonce($nonce, $this->config->ajaxNonce)) {
            \wp_die("Security verification failed", "Error", ["response" => 403]);
        }

        // Check capabilities
        if (!\current_user_can("update_plugins")) {
            \wp_die("Insufficient permissions", "Error", ["response" => 403]);
        }

        // Delete the notice transient
        $noticeKey = "wp_github_updater_notice_{$this->pluginBasename}";
        \delete_transient($noticeKey);

        \wp_send_json_success(["message" => "Notice dismissed successfully"]);
    }

    /**
     * Show admin notice for available updates
     *
     * Displays a WordPress admin notice when an update is available
     * after a manual version check.
     *
     * @since 1.1.4
     * @return void
     */
    public function showUpdateNotice(): void
    {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Check if we have a notice to show
        $notice_data = \get_transient("wp_github_updater_notice_{$this->pluginBasename}");
        if (!$notice_data) {
            return;
        }

        // Only show to users who can update plugins
        if (!\current_user_can("update_plugins")) {
            return;
        }

        $plugin_name = $notice_data["plugin_name"] ?? $this->config->pluginName;
        $current_version = $notice_data["current_version"] ?? $this->currentVersion;
        $latest_version = $notice_data["latest_version"] ?? "Unknown";
        $github_repo = $notice_data["github_repo"] ?? $this->config->githubRepo;

        $updates_url = \admin_url("plugins.php?plugin_status=upgrade");
        $github_url = "https://github.com/{$github_repo}/releases/latest";

        echo '<div class="notice notice-warning is-dismissible" data-notice="wp-github-updater-' .
            \esc_attr($this->pluginBasename) . '">';
        echo '<p>';
        echo '<strong>' . \esc_html($plugin_name) . '</strong> ';
        echo sprintf(
            \esc_html($this->config->__("has a new version available: %1\$s (you have %2\$s).")),
            '<strong>' . \esc_html($latest_version) . '</strong>',
            \esc_html($current_version)
        );
        echo '</p>';
        echo '<p>';
        echo '<a href="' . \esc_url($updates_url) . '" class="button button-primary">' .
            \esc_html($this->config->__("View Updates")) . '</a> ';
        echo '<a href="' . \esc_url($github_url) . '" class="button" target="_blank">' .
            \esc_html($this->config->__("View Release Notes")) . '</a>';
        echo '</p>';
        echo '</div>';

        // Add JavaScript to handle dismissal
        echo '<script>
        jQuery(document).ready(function($) {
            $(document).on("click", "[data-notice=\"wp-github-updater-' .
            \esc_js($this->pluginBasename) . '\"] .notice-dismiss", function() {
                $.post(ajaxurl, {
                    action: "' . \esc_js($this->config->ajaxAction) . '_dismiss_notice",
                    nonce: "' . \esc_js(\wp_create_nonce($this->config->ajaxNonce)) . '",
                    plugin: "' . \esc_js($this->pluginBasename) . '"
                });
            });
        });
        </script>';
    }

    /**
     * Get plugin data from file
     * @return array<string, mixed>
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
     * @return string
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    /**
     * Get GitHub repository
     *
     * @return string
     */
    public function getGithubRepo(): string
    {
        return $this->config->githubRepo;
    }

    /**
     * Check if update is available
     *
     * @return boolean
     */
    public function isUpdateAvailable(): bool
    {
        $latestVersion = $this->getLatestVersion();
        return $latestVersion && version_compare($this->currentVersion, $latestVersion, "<");
    }

    /**
     * Enqueue the built-in "Check Updates" JavaScript and return the
     * inline JS call for the Settings Hub action button.
     *
     * This method provides a centralized update check script that eliminates
     * the need for consuming plugins to maintain their own JavaScript files.
     * The script is loaded once and works for multiple plugins on the same page.
     *
     * @param array<string, string> $extraStrings Optional extra i18n string overrides.
     * @return string Inline JS to echo (e.g. "wpGithubUpdaterCheckUpdates('myData'); return false;")
     *
     * @since 1.3.0
     */
    public function enqueueCheckUpdatesScript(array $extraStrings = []): string
    {
        $dataKey = $this->sanitizeJsVarName("wpGithubUpdater_{$this->pluginBasename}");

        // Enqueue the shared JS (only loaded once even if multiple plugins call this)
        \wp_enqueue_script(
            "wp-github-updater-check",
            $this->getPackageAssetUrl("assets/js/check-updates.js"),
            ["jquery"],
            "1.4.0",
            true
        );

        // Localize per-plugin data under a unique global key
        \wp_localize_script("wp-github-updater-check", $dataKey, [
            "ajaxurl"    => \admin_url("admin-ajax.php"),
            "nonce"      => \wp_create_nonce($this->config->ajaxNonce),
            "action"     => $this->config->ajaxAction,
            "updateUrl"  => \admin_url("update-core.php"),
            "pluginName" => $this->config->pluginName,
            "strings"    => array_merge([
                "checking"        => $this->config->__("Checking for updates..."),
                "updateAvailable" => $this->config->__("Update available: v%s! Redirecting..."),
                "upToDate"        => $this->config->__("You're up to date!"),
                "checkError"      => $this->config->__("Error checking updates. Please try again."),
                "connectError"    => $this->config->__("Error connecting to update server."),
                "configError"     => $this->config->__("Update check configuration error."),
                "dismissNotice"   => $this->config->__("Dismiss this notice."),
            ], $extraStrings),
        ]);

        return "wpGithubUpdaterCheckUpdates('{$dataKey}'); return false;";
    }

    /**
     * Get the URL to a package asset file
     *
     * Resolves the URL to assets within the vendor/silverassist/wp-github-updater directory.
     * This handles the package being installed via Composer in the vendor directory.
     * Uses plugin_dir_url() for compatibility with subdirectory WordPress installations.
     *
     * MULTI-PLUGIN SUPPORT: Uses $this->config->pluginFile instead of __DIR__ to ensure
     * correct path resolution when multiple plugins share the same autoloaded class.
     * PHP's autoloader loads the class from the first registered vendor directory, but
     * each plugin instance needs to load assets from its own vendor directory.
     *
     * @param string $assetPath Relative path to asset (e.g., 'assets/js/check-updates.js').
     * @return string Full URL to the asset file
     *
     * @since 1.3.0
     * @internal Not part of the public API - used internally by enqueueCheckUpdatesScript()
     */
    private function getPackageAssetUrl(string $assetPath): string
    {
        // Get the plugin directory path from the configured plugin file
        $pluginDir = \wp_normalize_path(dirname($this->config->pluginFile));
        $baseUrl = rtrim(\plugin_dir_url($this->config->pluginFile), "/");

        // Standard Composer vendor path for this package
        $vendorRelativePath = "vendor/silverassist/wp-github-updater";
        $fullPackagePath = $pluginDir . "/" . $vendorRelativePath;

        // Verify the vendor path exists and the asset file is present
        $fullAssetPath = $fullPackagePath . "/" . ltrim($assetPath, "/");
        if (is_file($fullAssetPath)) {
            return $baseUrl . "/" . $vendorRelativePath . "/" . ltrim($assetPath, "/");
        }

        // Fallback: use __DIR__ resolution (for non-standard installations or development)
        $packageDir = \wp_normalize_path(dirname(__DIR__));
        $relativePath = "";
        if (str_starts_with($packageDir, $pluginDir)) {
            $relativePath = trim(substr($packageDir, strlen($pluginDir)), "/");
        }

        if ($relativePath !== "") {
            $baseUrl .= "/" . $relativePath;
        }

        return $baseUrl . "/" . ltrim($assetPath, "/");
    }

    /**
     * Sanitize a string to create a valid JavaScript variable name
     *
     * Removes or replaces characters that are not valid in JavaScript identifiers.
     * Used to generate unique global variable names for wp_localize_script.
     *
     * @param string $name Raw name to sanitize.
     * @return string Valid JavaScript variable name
     *
     * @since 1.3.0
     * @internal Not part of the public API - used internally by enqueueCheckUpdatesScript()
     */
    private function sanitizeJsVarName(string $name): string
    {
        // Replace invalid characters with underscores
        $sanitized = preg_replace("/[^a-zA-Z0-9_$]/", "_", $name);

        // Ensure it doesn't start with a number
        if ($sanitized && is_numeric($sanitized[0])) {
            $sanitized = "_" . $sanitized;
        }

        return $sanitized ?: "wpGithubUpdater_default";
    }

    /**
     * Replace with a regular expression, keeping the input when the pattern fails
     *
     * preg_replace() returns null on an engine error (for example a backtrack limit).
     * Keeping the original text is safer than turning the whole changelog into an empty string.
     *
     * @param string $pattern     Regular expression.
     * @param string $replacement Replacement text.
     * @param string $subject     Text to search.
     * @return string The replaced text, or the original subject on failure
     *
     * @since 1.4.0
     */
    private function regexReplace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }

    /**
     * Replace with a regular expression callback, keeping the input when the pattern fails
     *
     * @param string                                      $pattern  Regular expression.
     * @param callable(array<int|string, string>): string $callback Callback that builds each replacement.
     * @param string                                      $subject  Text to search.
     * @return string The replaced text, or the original subject on failure
     *
     * @since 1.4.0
     */
    private function regexReplaceCallback(string $pattern, callable $callback, string $subject): string
    {
        return preg_replace_callback($pattern, $callback, $subject) ?? $subject;
    }

    /**
     * Parse Markdown to HTML
     *
     * Converts basic Markdown syntax to HTML for better changelog display.
     * Supports headers, bold text, italic text, inline code, lists, and links.
     *
     * @param string $markdown Markdown content to convert.
     * @return string HTML formatted content
     *
     * @since 1.0.1
     */
    private function parseMarkdownToHtml(string $markdown): string
    {
        // Basic markdown to HTML conversion
        $html = $markdown;

        // Headers (# -> h2, ## -> h3, ### -> h4, #### -> h5)
        $html = $this->regexReplace("/^#### (.*$)/m", "<h5>$1</h5>", $html);
        $html = $this->regexReplace("/^### (.*$)/m", "<h4>$1</h4>", $html);
        $html = $this->regexReplace("/^## (.*$)/m", "<h3>$1</h3>", $html);
        $html = $this->regexReplace("/^# (.*$)/m", "<h2>$1</h2>", $html);

        // Bold text (**text** -> <strong>text</strong>)
        $html = $this->regexReplace("/\*\*(.*?)\*\*/", "<strong>$1</strong>", $html);

        // Italic text (*text* -> <em>text</em>)
        $html = $this->regexReplace("/(?<!\*)\*([^*]+)\*(?!\*)/", "<em>$1</em>", $html);

        // Code blocks (`code` -> <code>code</code>)
        $html = $this->regexReplace("/`([^`]+)`/", "<code>$1</code>", $html);

        // Unordered lists (- item -> <ul><li>item</li></ul>)
        $html = $this->regexReplaceCallback("/(?:^- (.+)(?:\n|$))+/m", function ($matches) {
            $items = preg_split("/\n- /", trim($matches[0])) ?: [trim($matches[0])];
            $items[0] = ltrim($items[0], "- ");
            $liItems = array_map(fn($item) => "<li>" . trim($item) . "</li>", array_filter($items));
            return "<ul>" . implode("", $liItems) . "</ul>";
        }, $html);

        // Links ([text](url) -> <a href="url">text</a>)
        $html = $this->regexReplace("/\[([^\]]+)\]\(([^)]+)\)/", "<a href=\"$2\">$1</a>", $html);

        // Line breaks (double newline -> <br>)
        $html = $this->regexReplace("/\n\s*\n/", "<br>", $html);
        $html = $this->regexReplace("/\n/", "<br>", $html);

        // Clean up extra line breaks and spaces
        $html = $this->regexReplace("/(<br>\s*){3,}/", "<br>", $html);
        $html = trim($html);

        return $html;
    }

    /**
     * Maybe fix download issues by providing better HTTP args
     *
     * This filter intercepts the download process for our GitHub releases
     * to ensure proper temporary file handling and avoid PCLZIP errors.
     *
     * CRITICAL: This filter MUST return one of:
     * - false (or WP_Error): Let WordPress handle the download normally
     * - string: Path to an already-downloaded file for WordPress to use
     * - NEVER return true or any other type!
     *
     * @param boolean|WP_Error     $result     The result from previous filters.
     * @param string               $package    The package URL being downloaded.
     * @param object               $upgrader   The WP_Upgrader instance.
     * @param array<string, mixed> $hook_extra Extra hook data.
     * @return string|WP_Error|false Path to downloaded file, WP_Error on failure, or false to continue
     *
     * @since 1.1.0
     */
    public function maybeFixDownload(
        bool|WP_Error $result,
        string $package,
        object $upgrader,
        array $hook_extra
    ): string|WP_Error|false {
        // If a previous filter already handled this, respect that decision
        if (\is_wp_error($result)) {
            return $result;
        }

        // Only handle GitHub downloads for our specific repository
        if (
            empty($package) ||
            !str_contains($package, "github.com") ||
            !str_contains($package, $this->config->githubRepo)
        ) {
            return false; // Let WordPress handle it normally
        }

        // Additional safety check: ensure this is actually for our plugin
        $is_our_plugin = false;
        if (isset($hook_extra["plugin"]) && $hook_extra["plugin"] === $this->pluginSlug) {
            $is_our_plugin = true;
        } elseif (
            isset($hook_extra["plugins"]) &&
            is_array($hook_extra["plugins"]) &&
            in_array($this->pluginSlug, $hook_extra["plugins"])
        ) {
            $is_our_plugin = true;
        }

        if (!$is_our_plugin) {
            return false; // Not our plugin, let WordPress handle it
        }

        // Download the package (two steps for a private repository, see fetchPackage())
        $body = $this->fetchPackage($package);
        if (\is_wp_error($body)) {
            return $body;
        }

        // Create temporary file with our multi-tier fallback system
        $temp_file = $this->createSecureTempFile($package);

        if (\is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Write the downloaded content to the temporary file
        $file_handle = @fopen($temp_file, "wb");
        if (!$file_handle) {
            @unlink($temp_file); // Clean up if file was created but can't be opened
            return new WP_Error(
                "file_open_failed",
                sprintf(
                    $this->config->__("Could not open temporary file for writing: %s"),
                    $temp_file
                )
            );
        }

        $bytes_written = fwrite($file_handle, $body);
        fclose($file_handle);

        // Verify write operation succeeded
        if ($bytes_written === false || $bytes_written !== strlen($body)) {
            @unlink($temp_file);
            return new WP_Error(
                "file_write_failed",
                $this->config->__("Failed to write complete package to temporary file")
            );
        }

        // Final verification: ensure file exists and is readable
        if (!file_exists($temp_file)) {
            return new WP_Error(
                "file_missing",
                $this->config->__("Temporary file disappeared after creation")
            );
        }

        if (!is_readable($temp_file)) {
            @unlink($temp_file);
            return new WP_Error(
                "file_not_readable",
                $this->config->__("Temporary file is not readable")
            );
        }

        // Verify it's actually a zip file
        $file_size = filesize($temp_file);
        if ($file_size < 100) { // Minimum size for a valid zip
            @unlink($temp_file);
            return new WP_Error(
                "invalid_package",
                sprintf(
                    $this->config->__("Downloaded file is too small (%d bytes) to be a valid package"),
                    $file_size
                )
            );
        }

        // SUCCESS: Return the path to the downloaded file
        // WordPress will use this file for extraction
        return $temp_file;
    }

    /**
     * Create a secure temporary file with multiple fallback strategies
     *
     * Attempts different approaches to create a temporary file to avoid PCLZIP errors
     * that can occur with restrictive /tmp directory permissions.
     *
     * @param string $package The package URL being downloaded.
     * @return string|WP_Error Path to temporary file or WP_Error on failure
     *
     * @since 1.1.4
     */
    private function createSecureTempFile(string $package): string|WP_Error
    {
        $path = parse_url($package, PHP_URL_PATH);
        $filename = is_string($path) ? basename($path) : "";
        if ($filename === "") {
            $filename = "github-package.zip";
        }

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

        return new WP_Error(
            "temp_file_creation_failed",
            $this->config->__("Could not create temporary file. Please check directory permissions " .
                "or define WP_TEMP_DIR in wp-config.php")
        );
    }

    /**
     * Get the GitHub token, if one is configured
     *
     * @return string|null The token, or null when requests should stay anonymous
     *
     * @since 1.4.0
     */
    private function getToken(): ?string
    {
        return $this->config->getGithubToken();
    }

    /**
     * Check whether a URL points at the GitHub API
     *
     * @param string $url URL to check.
     * @return boolean True for an https URL on api.github.com
     *
     * @since 1.4.0
     */
    private function isGithubApiUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts["scheme"] ?? "") === "https"
            && strtolower($parts["host"] ?? "") === "api.github.com";
    }

    /**
     * Get the Authorization header for a request, when it should carry one
     *
     * The token is only ever sent to api.github.com. It is never attached to any other host,
     * including the signed storage URL GitHub redirects a private asset download to.
     *
     * @param string $url URL the request is going to.
     * @return array<string, string> The header, or an empty array
     *
     * @since 1.4.0
     */
    private function getAuthorizationHeader(string $url): array
    {
        $token = $this->getToken();
        if ($token === null || !$this->isGithubApiUrl($url)) {
            return [];
        }

        return ["Authorization" => "Bearer {$token}"];
    }

    /**
     * Get headers for GitHub API requests
     *
     * Returns standard headers for GitHub API communication including
     * User-Agent and Accept headers, plus the token when one is configured.
     *
     * @return array<string, string> Array of HTTP headers
     *
     * @since 1.1.0
     */
    private function getApiHeaders(): array
    {
        return array_merge([
            "User-Agent" => "WP-GitHub-Updater/{$this->currentVersion}",
            "Accept" => "application/vnd.github.v3+json",
        ], $this->getAuthorizationHeader("https://api.github.com/"));
    }

    /**
     * Get headers for GitHub asset downloads
     *
     * Returns headers optimized for downloading GitHub release assets, plus the token when the
     * URL is on the GitHub API and one is configured.
     *
     * @param string $url URL the download is going to.
     * @return array<string, string> Array of HTTP headers
     *
     * @since 1.1.0
     */
    private function getDownloadHeaders(string $url): array
    {
        return array_merge([
            "User-Agent" => "WP-GitHub-Updater/{$this->currentVersion}",
            "Accept" => "application/octet-stream",
            "Accept-Encoding" => "gzip, deflate",
        ], $this->getAuthorizationHeader($url));
    }

    /**
     * Download the release package
     *
     * The asset URL of a private repository is on the GitHub API and needs the token, and GitHub
     * answers it with a redirect to a signed storage URL. That redirect is followed by hand so the
     * token is never sent to the storage host: the first request carries the token and stops at
     * the redirect, the second one goes to the signed URL with no Authorization header.
     *
     * @param string $package The package URL being downloaded.
     * @return string|WP_Error The package contents, or a WP_Error
     *
     * @since 1.4.0
     */
    private function fetchPackage(string $package): string|WP_Error
    {
        $authenticated = $this->getAuthorizationHeader($package) !== [];

        $args = [
            "timeout" => 300, // 5 minutes for large files
            "headers" => $this->getDownloadHeaders($package),
            "sslverify" => true,
        ];
        if ($authenticated) {
            $args["redirection"] = 0;
        }

        $response = \wp_remote_get($package, $args);

        if ($authenticated && !\is_wp_error($response)) {
            $code = (int) \wp_remote_retrieve_response_code($response);
            if (in_array($code, [301, 302, 303, 307, 308], true)) {
                $location = \wp_remote_retrieve_header($response, "location");
                $location = is_string($location) ? $location : "";

                if (!str_starts_with($location, "https://")) {
                    return new WP_Error(
                        "invalid_redirect",
                        $this->config->__("GitHub redirected the download to an address that is not https")
                    );
                }

                $response = \wp_remote_get($location, [
                    "timeout" => 300,
                    "headers" => $this->getDownloadHeaders($location),
                    "sslverify" => true,
                ]);
            }
        }

        if (\is_wp_error($response)) {
            $this->logRequestFailure("downloading the package", $response);

            return new WP_Error(
                "download_failed",
                sprintf(
                    $this->config->__("Failed to download package: %s"),
                    $response->get_error_message()
                )
            );
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            $this->logRequestFailure("downloading the package", $response);

            return new WP_Error(
                "http_error",
                trim(sprintf(
                    $this->config->__("Package download failed with HTTP code %d"),
                    $code
                ) . " " . $this->getFailureHint($code, $authenticated))
            );
        }

        $body = \wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error(
                "empty_response",
                $this->config->__("Downloaded package is empty")
            );
        }

        return $body;
    }

    /**
     * Explain a failed request in words an administrator can act on
     *
     * @param integer $code          HTTP status code.
     * @param boolean $authenticated Whether the request carried the token.
     * @return string A short hint, or an empty string when there is nothing specific to say
     *
     * @since 1.4.0
     */
    private function getFailureHint(int $code, bool $authenticated): string
    {
        $name = $this->config->tokenConstant;

        return match (true) {
            $code === 401 => sprintf($this->config->__("GitHub rejected the token. Check the value of %s."), $name),
            $authenticated && in_array($code, [403, 404], true) =>
                $this->config->__("The token may not have access to this repository."),
            $code === 404 => sprintf(
                $this->config->__("If the repository is private, define %s in wp-config.php or the environment."),
                $name
            ),
            default => "",
        };
    }

    /**
     * Write a failed GitHub request to the PHP error log
     *
     * The message names the likely cause (a rejected token, a repository the token cannot read,
     * a private repository with no token) so a site that silently stops updating can be
     * diagnosed from its log. The token itself is never written.
     *
     * @param string                        $action   What was being attempted, for example "downloading".
     * @param array<string, mixed>|WP_Error $response The failed response.
     * @return void
     *
     * @since 1.4.0
     */
    private function logRequestFailure(string $action, array|WP_Error $response): void
    {
        $prefix = "WP GitHub Updater: Failed {$action} for {$this->config->githubRepo}";

        if (\is_wp_error($response)) {
            error_log("{$prefix}: " . $response->get_error_message());
            return;
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        $hasToken = $this->getToken() !== null;
        $name = $this->config->tokenConstant;

        $detail = match (true) {
            $code === 401 => "GitHub rejected the token (HTTP 401). Check the value of {$name}.",
            $code === 403 && $hasToken => "HTTP 403: the token has no access to the repository, "
                . "or the rate limit was reached.",
            $code === 403 => "HTTP 403: the rate limit was reached or the repository is private. "
                . "Define {$name} to authenticate.",
            $code === 404 && $hasToken => "HTTP 404: the release does not exist, "
                . "or the token has no access to the repository.",
            $code === 404 => "HTTP 404: not found. If the repository is private, "
                . "define {$name} in wp-config.php or the environment.",
            default => "HTTP {$code}",
        };

        error_log("{$prefix}: {$detail}");
    }
}
