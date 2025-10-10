<?php

/**
 * WordPress function mocks for testing
 *
 * This file provides implementations for WordPress functions that are only
 * defined (but not implemented) in wordpress-stubs.
 *
 * @package SilverAssist\WpGithubUpdater\Tests
 */

// Translation functions
if (!function_exists("__")) {
    /**
     * Mock __ function for tests
     *
     * @param string $text   Text to translate
     * @param string $domain Text domain
     * @return string Translated text (returns original in tests)
     */
    function __(string $text, string $domain = "default"): string
    {
        return $text;
    }
}

if (!function_exists("esc_html__")) {
    /**
     * Mock esc_html__ function for tests
     *
     * @param string $text   Text to translate
     * @param string $domain Text domain
     * @return string Escaped and translated text
     */
    function esc_html__(string $text, string $domain = "default"): string
    {
        return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    }
}

// WordPress hooks and filters
if (!function_exists("add_filter")) {
    /**
     * Mock add_filter function for tests
     *
     * @param string   $hook_name Hook name
     * @param callable $callback  Callback function
     * @param int      $priority  Priority
     * @param int      $accepted_args Accepted arguments
     * @return bool Always returns true
     */
    function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists("add_action")) {
    /**
     * Mock add_action function for tests
     *
     * @param string   $hook_name Hook name
     * @param callable $callback  Callback function
     * @param int      $priority  Priority
     * @param int      $accepted_args Accepted arguments
     * @return bool Always returns true
     */
    function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

// Plugin functions
if (!function_exists("plugin_basename")) {
    /**
     * Mock plugin_basename function for tests
     *
     * @param string $file Plugin file path
     * @return string Plugin basename
     */
    function plugin_basename(string $file): string
    {
        $file = str_replace("\\", "/", $file);
        $file = preg_replace("|/+|", "/", $file);
        
        // If WP_PLUGIN_DIR is defined, make path relative to it
        if (defined("WP_PLUGIN_DIR")) {
            $plugin_dir = str_replace("\\", "/", WP_PLUGIN_DIR);
            $plugin_dir = preg_replace("|/+|", "/", $plugin_dir);
            $file = preg_replace("#^" . preg_quote($plugin_dir, "#") . "/#", "", $file);
        }
        
        // Otherwise just return folder/file.php format
        $file = trim($file, "/");
        $parts = explode("/", $file);
        
        if (count($parts) >= 2) {
            // Return last two parts: folder/file.php
            return $parts[count($parts) - 2] . "/" . $parts[count($parts) - 1];
        }
        
        return basename($file);
    }
}

if (!function_exists("get_plugin_data")) {
    /**
     * Mock get_plugin_data function for tests
     *
     * @param string $plugin_file Path to the plugin file
     * @param bool   $markup      Whether to apply markup
     * @param bool   $translate   Whether to translate
     * @return array Plugin data array
     */
    function get_plugin_data(string $plugin_file, bool $markup = true, bool $translate = true): array
    {
        if (!file_exists($plugin_file)) {
            return [
                "Name" => "Test Plugin",
                "Version" => "1.0.0",
                "Description" => "Test plugin description",
                "Author" => "Test Author",
                "PluginURI" => "",
                "AuthorURI" => "",
                "TextDomain" => "test-plugin",
                "DomainPath" => "",
                "Network" => false,
                "RequiresWP" => "",
                "RequiresPHP" => "",
            ];
        }

        $content = file_get_contents($plugin_file);
        $headers = [
            "Name" => "Plugin Name",
            "PluginURI" => "Plugin URI",
            "Version" => "Version",
            "Description" => "Description",
            "Author" => "Author",
            "AuthorURI" => "Author URI",
            "TextDomain" => "Text Domain",
            "DomainPath" => "Domain Path",
            "Network" => "Network",
            "RequiresWP" => "Requires at least",
            "RequiresPHP" => "Requires PHP",
        ];

        $data = [];
        foreach ($headers as $key => $header) {
            if (preg_match("/^[ \t\/*#@]*" . preg_quote($header, "/") . ":(.*)$/mi", $content, $matches)) {
                $data[$key] = trim($matches[1]);
            } else {
                $data[$key] = "";
            }
        }

        // Convert Network to boolean
        $data["Network"] = strtolower($data["Network"]) === "true";

        return $data;
    }
}
