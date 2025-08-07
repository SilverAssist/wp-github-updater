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
 * Configuration class for the GitHub updater
 */
class UpdaterConfig
{
    /**
     * Plugin file path
     */
    public string $pluginFile;

    /**
     * GitHub repository (owner/repo)
     */
    public string $githubRepo;

    /**
     * Plugin name
     */
    public string $pluginName;

    /**
     * Plugin description
     */
    public string $pluginDescription;

    /**
     * Plugin author
     */
    public string $pluginAuthor;

    /**
     * Plugin homepage
     */
    public string $pluginHomepage;

    /**
     * Minimum WordPress version
     */
    public string $requiresWordPress;

    /**
     * Minimum PHP version
     */
    public string $requiresPHP;

    /**
     * Release asset filename pattern
     * Use {version} as placeholder for version number
     */
    public string $assetPattern;

    /**
     * Cache duration in seconds
     */
    public int $cacheDuration;

    /**
     * AJAX action name for manual version check
     */
    public string $ajaxAction;

    /**
     * AJAX nonce name
     */
    public string $ajaxNonce;

    /**
     * Create updater configuration
     *
     * @param string $pluginFile Main plugin file path
     * @param string $githubRepo GitHub repository (owner/repo)
     * @param array $options Additional configuration options
     */
    public function __construct(string $pluginFile, string $githubRepo, array $options = [])
    {
        $this->pluginFile = $pluginFile;
        $this->githubRepo = $githubRepo;

        // Get plugin data
        $pluginData = $this->getPluginData($pluginFile);

        // Set defaults from plugin data or options
        $this->pluginName = $options['plugin_name'] ?? $pluginData['Name'] ?? '';
        $this->pluginDescription = $options['plugin_description'] ?? $pluginData['Description'] ?? '';
        $this->pluginAuthor = $options['plugin_author'] ?? $pluginData['Author'] ?? '';
        $this->pluginHomepage = $options['plugin_homepage'] ?? "https://github.com/{$githubRepo}";
        $this->requiresWordPress = $options['requires_wordpress'] ?? '6.0';
        $this->requiresPHP = $options['requires_php'] ?? '8.0';
        $this->assetPattern = $options['asset_pattern'] ?? '{slug}-v{version}.zip';
        $this->cacheDuration = $options['cache_duration'] ?? (12 * 3600); // 12 hours
        $this->ajaxAction = $options['ajax_action'] ?? 'check_plugin_version';
        $this->ajaxNonce = $options['ajax_nonce'] ?? 'plugin_version_check';
    }

    /**
     * Get plugin data from file
     */
    private function getPluginData(string $pluginFile): array
    {
        if (function_exists('get_plugin_data')) {
            return get_plugin_data($pluginFile);
        }

        // Fallback for when WordPress functions aren't available
        return [];
    }
}
