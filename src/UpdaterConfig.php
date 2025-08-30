<?php

/**
 * WordPress GitHub Updater
 *
 * A reusable WordPress plugin updater that handles automatic updates from public GitHub releases.
 *
 * @package SilverAssist\WpGithubUpdater
 * @author Silver Assist
 * @version 1.1.4
 * @license PolyForm-Noncommercial-1.0.0
 */

namespace SilverAssist\WpGithubUpdater;

/**
 * Configuration class for the GitHub updater
 *
 * This class holds all the configuration settings needed for the updater to work.
 * It provides a structured way to pass plugin information and updater settings
 * to the main Updater class.
 *
 * @package SilverAssist\WpGithubUpdater
 * @since   1.0.0
 */
class UpdaterConfig
{
    /**
     * Plugin file path
     *
     * @var string Path to the main plugin file
     * @since 1.0.0
     */
    public string $pluginFile;

    /**
     * GitHub repository (owner/repo)
     *
     * @var string GitHub repository in the format "owner/repo"
     * @since 1.0.0
     */
    public string $githubRepo;

    /**
     * Plugin name
     *
     * @var string Display name of the plugin
     * @since 1.0.0
     */
    public string $pluginName;

    /**
     * Plugin description
     *
     * @var string Brief description of the plugin
     * @since 1.0.0
     */
    public string $pluginDescription;

    /**
     * Plugin author
     *
     * @var string Author name or company
     * @since 1.0.0
     */
    public string $pluginAuthor;

    /**
     * Plugin homepage
     *
     * @var string URL to the plugin's homepage
     * @since 1.0.0
     */
    public string $pluginHomepage;

    /**
     * Minimum WordPress version
     *
     * @var string Minimum required WordPress version
     * @since 1.0.0
     */
    public string $requiresWordPress;

    /**
     * Minimum PHP version
     *
     * @var string Minimum required PHP version
     * @since 1.0.0
     */
    public string $requiresPHP;

    /**
     * Release asset filename pattern
     * Use {version} as placeholder for version number
     *
     * @var string Pattern for GitHub release asset filename
     * @since 1.0.0
     */
    public string $assetPattern;

    /**
     * Cache duration in seconds
     *
     * @var int How long to cache version information
     * @since 1.0.0
     */
    public int $cacheDuration;

    /**
     * AJAX action name for manual version check
     *
     * @var string WordPress AJAX action name
     * @since 1.0.0
     */
    public string $ajaxAction;

    /**
     * AJAX nonce name
     *
     * @var string WordPress nonce name for AJAX requests
     * @since 1.0.0
     */
    public string $ajaxNonce;

    /**
     * Text domain for translations
     *
     * @var string WordPress text domain for i18n functions
     * @since 1.1.0
     */
    public string $textDomain;

    /**
     * Custom temporary directory path
     *
     * @var string|null Custom path for temporary files during downloads, null for auto-detection
     * @since 1.1.3
     */
    public ?string $customTempDir;

    /**
     * Create updater configuration
     *
     * Initializes the updater configuration with plugin metadata and settings.
     * Accepts text domain from the consuming plugin for proper i18n support.
     *
     * @param string $pluginFile Main plugin file path
     * @param string $githubRepo GitHub repository (owner/repo)
     * @param array  $options    Additional configuration options including text_domain
     *
     * @since 1.0.0
     */
    public function __construct(string $pluginFile, string $githubRepo, array $options = [])
    {
        $this->pluginFile = $pluginFile;
        $this->githubRepo = $githubRepo;

        // Get plugin data
        $pluginData = $this->getPluginData($pluginFile);

        // Set defaults from plugin data or options
        $this->pluginName = $options["plugin_name"] ?? $pluginData["Name"] ?? "";
        $this->pluginDescription = $options["plugin_description"] ?? $pluginData["Description"] ?? "";
        $this->pluginAuthor = $options["plugin_author"] ?? $pluginData["Author"] ?? "";
        $this->pluginHomepage = $options["plugin_homepage"] ?? "https://github.com/{$githubRepo}";
        $this->requiresWordPress = $options["requires_wordpress"] ?? "6.0";
        $this->requiresPHP = $options["requires_php"] ?? "8.0";
        $this->assetPattern = $options["asset_pattern"] ?? "{slug}-v{version}.zip";
        $this->cacheDuration = $options["cache_duration"] ?? (12 * 3600); // 12 hours
        $this->ajaxAction = $options["ajax_action"] ?? "check_plugin_version";
        $this->ajaxNonce = $options["ajax_nonce"] ?? "plugin_version_check";
        $this->textDomain = $options["text_domain"] ?? "wp-github-updater";
        $this->customTempDir = $options["custom_temp_dir"] ?? null;
    }

    /**
     * Get plugin data from file
     *
     * Retrieves plugin metadata from the plugin file header.
     * Falls back to empty array when WordPress functions aren't available.
     *
     * @param string $pluginFile Path to the plugin file
     * @return array Plugin data array
     *
     * @since 1.0.0
     */
    private function getPluginData(string $pluginFile): array
    {
        if (function_exists("get_plugin_data")) {
            return \get_plugin_data($pluginFile);
        }

        // Fallback for when WordPress functions aren't available
        return [];
    }

    /**
     * Translation wrapper for the package
     *
     * @param string $text Text to translate
     * @return string Translated text
     *
     * @since 1.1.0
     */
    public function __(string $text): string
    {
        return \__($text, $this->textDomain);
    }

    /**
     * Escaped translation wrapper for the package
     *
     * @param string $text Text to translate and escape
     * @return string Translated and escaped text
     *
     * @since 1.1.0
     */
    public function esc_html__(string $text): string
    {
        return \esc_html__($text, $this->textDomain);
    }
}
