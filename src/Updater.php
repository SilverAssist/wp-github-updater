<?php

/**
 * WordPress GitHub Updater
 * 
 * A reusable WordPress plugin updater that handles automatic updates from public GitHub releases.
 *
 * @package SilverAssist\WpGithubUpdater
 * @author Silver Assist
 * @license GPL-2.0-or-later
 */

namespace SilverAssist\WpGithubUpdater;

/**
 * Main updater class that handles plugin updates from GitHub releases
 */
class Updater
{
    /**
     * Updater configuration
     */
    private UpdaterConfig $config;

    /**
     * Plugin slug (folder/file.php)
     */
    private string $pluginSlug;

    /**
     * Plugin basename (folder name only)
     */
    private string $pluginBasename;

    /**
     * Current plugin version
     */
    private string $currentVersion;

    /**
     * Plugin data from header
     */
    private array $pluginData;

    /**
     * Transient name for version cache
     */
    private string $versionTransient;

    /**
     * Initialize the updater
     *
     * @param UpdaterConfig $config Updater configuration
     */
    public function __construct(UpdaterConfig $config)
    {
        $this->config = $config;
        $this->pluginSlug = \plugin_basename($config->pluginFile);
        $this->pluginBasename = dirname($this->pluginSlug);
        $this->versionTransient = "{$this->pluginBasename}_version_check";

        // Get plugin data
        $this->pluginData = $this->getPluginData();
        $this->currentVersion = $this->pluginData['Version'] ?? '1.0.0';

        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        \add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        \add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
        \add_action('upgrader_process_complete', [$this, 'clearVersionCache'], 10, 2);

        // Add AJAX action for manual version check
        \add_action("wp_ajax_{$this->config->ajaxAction}", [$this, 'manualVersionCheck']);
    }

    /**
     * Check for plugin updates
     *
     * @param mixed $transient The update_plugins transient
     * @return mixed
     */
    public function checkForUpdate($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $latestVersion = $this->getLatestVersion();

        if ($latestVersion && version_compare($this->currentVersion, $latestVersion, '<')) {
            $transient->response[$this->pluginSlug] = (object) [
                'slug' => $this->pluginBasename,
                'plugin' => $this->pluginSlug,
                'new_version' => $latestVersion,
                'url' => $this->config->pluginHomepage,
                'package' => $this->getDownloadUrl($latestVersion),
                'tested' => \get_bloginfo('version'),
                'requires_php' => $this->config->requiresPHP,
                'compatibility' => new \stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Get plugin information for the update API
     *
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return false|object|array
     */
    public function pluginInfo($result, string $action, object $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== $this->pluginBasename) {
            return $result;
        }

        $latestVersion = $this->getLatestVersion();
        $changelog = $this->getChangelog();

        return (object) [
            'slug' => $this->pluginBasename,
            'plugin' => $this->pluginSlug,
            'version' => $latestVersion ?: $this->currentVersion,
            'author' => $this->config->pluginAuthor,
            'author_profile' => $this->config->pluginHomepage,
            'requires' => $this->config->requiresWordPress,
            'tested' => \get_bloginfo('version'),
            'requires_php' => $this->config->requiresPHP,
            'name' => $this->config->pluginName,
            'homepage' => $this->config->pluginHomepage,
            'sections' => [
                'description' => $this->config->pluginDescription,
                'changelog' => $changelog,
            ],
            'download_link' => $this->getDownloadUrl($latestVersion),
            'last_updated' => $this->getLastUpdated(),
        ];
    }

    /**
     * Get the latest version from GitHub releases
     *
     * @return string|false
     */
    public function getLatestVersion()
    {
        // Check cache first
        $cachedVersion = \get_transient($this->versionTransient);
        if ($cachedVersion !== false) {
            return $cachedVersion;
        }

        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases/latest";
        $response = \wp_remote_get($apiUrl, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version'),
            ],
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            error_log("WP GitHub Updater: Failed to fetch latest version for {$this->config->githubRepo}");
            return false;
        }

        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['tag_name'])) {
            return false;
        }

        $version = ltrim($data['tag_name'], 'v');

        // Cache the version
        \set_transient($this->versionTransient, $version, $this->config->cacheDuration);

        return $version;
    }

    /**
     * Get download URL for a specific version
     *
     * @param string $version The version to download
     * @return string
     */
    private function getDownloadUrl(string $version): string
    {
        $pattern = $this->config->assetPattern;
        $filename = str_replace(
            ['{slug}', '{version}'],
            [$this->pluginBasename, $version],
            $pattern
        );

        return "https://github.com/{$this->config->githubRepo}/releases/download/v{$version}/{$filename}";
    }

    /**
     * Get changelog from GitHub releases
     *
     * @return string
     */
    private function getChangelog(): string
    {
        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases";
        $response = \wp_remote_get($apiUrl, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version'),
            ],
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            return "Unable to fetch changelog. Visit the <a href=\"https://github.com/{$this->config->githubRepo}/releases\">GitHub releases page</a> for updates.";
        }

        $body = \wp_remote_retrieve_body($response);
        $releases = json_decode($body, true);

        if (!is_array($releases)) {
            return 'Unable to parse changelog.';
        }

        $changelog = '';
        foreach (array_slice($releases, 0, 5) as $release) { // Show last 5 releases
            $version = ltrim($release['tag_name'], 'v');
            $date = date('Y-m-d', strtotime($release['published_at']));
            $body = $release['body'] ?: 'No release notes provided.';

            $changelog .= "<h4>Version {$version} ({$date})</h4>\n";
            $changelog .= "<div>" . \wp_kses_post($body) . "</div>\n\n";
        }

        return $changelog ?: 'No changelog available.';
    }

    /**
     * Get last updated date
     *
     * @return string
     */
    private function getLastUpdated(): string
    {
        $apiUrl = "https://api.github.com/repos/{$this->config->githubRepo}/releases/latest";
        $response = \wp_remote_get($apiUrl, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version'),
            ],
        ]);

        if (\is_wp_error($response) || 200 !== \wp_remote_retrieve_response_code($response)) {
            return date('Y-m-d');
        }

        $body = \wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['published_at'])) {
            return date('Y-m-d');
        }

        return date('Y-m-d', strtotime($data['published_at']));
    }

    /**
     * Clear version cache after update
     *
     * @param \WP_Upgrader $upgrader WP_Upgrader instance
     * @param array $data Array of update data
     */
    public function clearVersionCache(\WP_Upgrader $upgrader, array $data): void
    {
        if ($data['action'] === 'update' && $data['type'] === 'plugin') {
            if (isset($data['plugins']) && in_array($this->pluginSlug, $data['plugins'])) {
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
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', $this->config->ajaxNonce)) {
            \wp_send_json_error([
                'message' => 'Security check failed',
                'code' => 'invalid_nonce'
            ]);
        }
        
        if (!\current_user_can('update_plugins')) {
            \wp_send_json_error([
                'message' => 'Insufficient permissions',
                'code' => 'insufficient_permissions'
            ]);
        }

        try {
            \delete_transient($this->versionTransient);
            $latestVersion = $this->getLatestVersion();

            \wp_send_json_success([
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion ?: 'Unknown',
                'update_available' => $latestVersion && version_compare($this->currentVersion, $latestVersion, '<'),
                'github_repo' => $this->config->githubRepo,
            ]);
        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => "Error checking for updates: {$e->getMessage()}",
                'code' => 'version_check_failed'
            ]);
        }
    }

    /**
     * Get plugin data from file
     */
    private function getPluginData(): array
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
     * @return bool
     */
    public function isUpdateAvailable(): bool
    {
        $latestVersion = $this->getLatestVersion();
        return $latestVersion && version_compare($this->currentVersion, $latestVersion, '<');
    }
}
