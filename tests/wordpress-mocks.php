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

// Admin functions
if (!function_exists("admin_url")) {
    /**
     * Mock admin_url function for tests
     *
     * @param string $path   Path relative to admin URL
     * @param string $scheme URL scheme
     * @return string Admin URL
     */
    function admin_url(string $path = "", string $scheme = "admin"): string
    {
        return "http://example.com/wp-admin/" . ltrim($path, "/");
    }
}

if (!function_exists("site_url")) {
    /**
     * Mock site_url function for tests
     *
     * @param string      $path   Path relative to site URL
     * @param string|null $scheme URL scheme
     * @return string Site URL
     */
    function site_url(string $path = "", ?string $scheme = null): string
    {
        return "http://example.com/" . ltrim($path, "/");
    }
}

if (!function_exists("wp_normalize_path")) {
    /**
     * Mock wp_normalize_path function for tests
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    function wp_normalize_path(string $path): string
    {
        $path = str_replace("\\", "/", $path);
        $path = preg_replace("|(?<=.)/+|", "/", $path);
        if (":" === substr($path, 1, 1)) {
            $path = ucfirst($path);
        }
        return $path;
    }
}

// Script enqueue functions
if (!function_exists("wp_enqueue_script")) {
    /**
     * Mock wp_enqueue_script function for tests
     *
     * @param string           $handle    Script handle
     * @param string           $src       Script source URL
     * @param array            $deps      Dependencies
     * @param string|bool|null $ver       Version
     * @param bool             $in_footer Load in footer
     * @return void
     */
    function wp_enqueue_script(
        string $handle,
        string $src = "",
        array $deps = [],
        $ver = false,
        bool $in_footer = false
    ): void {
        // Store enqueued scripts in global for testing
        global $wp_enqueued_scripts;
        if (!isset($wp_enqueued_scripts)) {
            $wp_enqueued_scripts = [];
        }
        $wp_enqueued_scripts[$handle] = [
            "src" => $src,
            "deps" => $deps,
            "ver" => $ver,
            "in_footer" => $in_footer,
        ];
    }
}

if (!function_exists("wp_localize_script")) {
    /**
     * Mock wp_localize_script function for tests
     *
     * @param string $handle      Script handle
     * @param string $object_name JavaScript object name
     * @param array  $l10n        Localization data
     * @return bool Always returns true
     */
    function wp_localize_script(string $handle, string $object_name, array $l10n): bool
    {
        // Mock - store in global for testing if needed
        global $wp_localized_scripts;
        if (!isset($wp_localized_scripts)) {
            $wp_localized_scripts = [];
        }
        $wp_localized_scripts[$handle][$object_name] = $l10n;
        return true;
    }
}

if (!function_exists("wp_create_nonce")) {
    /**
     * Mock wp_create_nonce function for tests
     *
     * @param string|int $action Action name
     * @return string Nonce token
     */
    function wp_create_nonce($action = -1): string
    {
        return "test_nonce_" . md5((string) $action);
    }
}

if (!function_exists("plugin_dir_url")) {
    /**
     * Mock plugin_dir_url function for tests
     *
     * @param string $file Plugin file path
     * @return string Plugin directory URL with trailing slash
     */
    function plugin_dir_url(string $file): string
    {
        $pluginDir = basename(dirname($file));
        return "http://example.com/wp-content/plugins/" . $pluginDir . "/";
    }
}

// WP_Error class mock
if (!class_exists("WP_Error")) {
    /**
     * Mock WP_Error class for tests
     *
     * Provides a minimal implementation of WordPress WP_Error class
     * so that is_wp_error() checks and error handling work correctly.
     */
    // phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps
    class WP_Error
    {
        /**
         * Error codes and messages
         *
         * @var array<string, array<string>>
         */
        public array $errors = [];

        /**
         * Error data
         *
         * @var array<string, mixed>
         */
        public array $error_data = [];

        /**
         * Constructor
         *
         * @param string $code    Error code
         * @param string $message Error message
         * @param mixed  $data    Error data
         */
        public function __construct(string $code = "", string $message = "", $data = "")
        {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        /**
         * Get error message
         *
         * @param string $code Error code
         * @return string Error message
         */
        public function get_error_message(string $code = ""): string
        {
            if (empty($code)) {
                $code = array_key_first($this->errors) ?? "";
            }
            return $this->errors[$code][0] ?? "";
        }

        /**
         * Get error code
         *
         * @return string Error code
         */
        public function get_error_code(): string
        {
            return array_key_first($this->errors) ?? "";
        }
    }
}

if (!function_exists("is_wp_error")) {
    /**
     * Mock is_wp_error function for tests
     *
     * @param mixed $thing Value to check
     * @return bool True if WP_Error instance
     */
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

// Transient functions (in-memory storage for tests)
if (!function_exists("get_transient")) {
    /** @var array<string, mixed> Global transient storage for tests */
    global $wp_mock_transients;
    $wp_mock_transients = [];

    /**
     * Mock get_transient function for tests
     *
     * Uses in-memory storage to simulate WordPress transient behaviour.
     *
     * @param string $transient Transient name
     * @return mixed Transient value or false if not set/expired
     */
    function get_transient(string $transient)
    {
        global $wp_mock_transients;
        if (isset($wp_mock_transients[$transient])) {
            $entry = $wp_mock_transients[$transient];
            // Check expiration (0 = no expiration)
            if ($entry["expiration"] === 0 || $entry["expiration"] > time()) {
                return $entry["value"];
            }
            unset($wp_mock_transients[$transient]);
        }
        return false;
    }
}

if (!function_exists("set_transient")) {
    /**
     * Mock set_transient function for tests
     *
     * @param string $transient  Transient name
     * @param mixed  $value      Transient value
     * @param int    $expiration Expiration in seconds (0 = no expiration)
     * @return bool Always returns true
     */
    function set_transient(string $transient, $value, int $expiration = 0): bool
    {
        global $wp_mock_transients;
        $wp_mock_transients[$transient] = [
            "value" => $value,
            "expiration" => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (!function_exists("delete_transient")) {
    /**
     * Mock delete_transient function for tests
     *
     * @param string $transient Transient name
     * @return bool Always returns true
     */
    function delete_transient(string $transient): bool
    {
        global $wp_mock_transients;
        unset($wp_mock_transients[$transient]);
        return true;
    }
}

// HTTP API functions (functional implementations using PHP streams)
if (!function_exists("wp_remote_get")) {
    /**
     * Mock wp_remote_get function for tests
     *
     * Uses PHP file_get_contents with stream context to make real HTTP
     * requests, mimicking WordPress wp_remote_get() response format.
     *
     * @param string               $url  URL to fetch
     * @param array<string, mixed> $args Request arguments
     * @return array<string, mixed>|WP_Error Response array or WP_Error on failure
     */
    function wp_remote_get(string $url, array $args = [])
    {
        $timeout = $args["timeout"] ?? 10;
        $headers = $args["headers"] ?? [];

        $httpHeaders = [];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = "{$name}: {$value}";
        }
        // Add a default User-Agent if not provided
        $hasUserAgent = false;
        foreach ($httpHeaders as $h) {
            if (stripos($h, "User-Agent:") === 0) {
                $hasUserAgent = true;
                break;
            }
        }
        if (!$hasUserAgent) {
            $httpHeaders[] = "User-Agent: WP-GitHub-Updater-Tests/1.0";
        }

        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => implode("\r\n", $httpHeaders),
                "timeout" => $timeout,
                "ignore_errors" => true,
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return new WP_Error(
                "http_request_failed",
                "Failed to fetch URL: {$url}"
            );
        }

        // Parse response headers from $http_response_header
        $statusCode = 200;
        $responseHeaders = [];
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match("#^HTTP/\d+\.?\d*\s+(\d+)#", $header, $matches)) {
                    $statusCode = (int) $matches[1];
                } elseif (strpos($header, ":") !== false) {
                    [$name, $value] = explode(":", $header, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
            }
        }

        return [
            "headers" => $responseHeaders,
            "body" => $body,
            "response" => [
                "code" => $statusCode,
                "message" => "",
            ],
            "cookies" => [],
        ];
    }
}

if (!function_exists("wp_remote_retrieve_response_code")) {
    /**
     * Mock wp_remote_retrieve_response_code function for tests
     *
     * @param array<string, mixed>|WP_Error $response HTTP response array
     * @return int|string Response code or empty string on failure
     */
    function wp_remote_retrieve_response_code($response)
    {
        if (is_wp_error($response) || !is_array($response)) {
            return "";
        }
        return $response["response"]["code"] ?? "";
    }
}

if (!function_exists("wp_remote_retrieve_body")) {
    /**
     * Mock wp_remote_retrieve_body function for tests
     *
     * @param array<string, mixed>|WP_Error $response HTTP response array
     * @return string Response body or empty string on failure
     */
    function wp_remote_retrieve_body($response): string
    {
        if (is_wp_error($response) || !is_array($response)) {
            return "";
        }
        return $response["body"] ?? "";
    }
}

if (!function_exists("wp_remote_retrieve_headers")) {
    /**
     * Mock wp_remote_retrieve_headers function for tests
     *
     * @param array<string, mixed>|WP_Error $response HTTP response array
     * @return array<string, string> Response headers or empty array on failure
     */
    function wp_remote_retrieve_headers($response): array
    {
        if (is_wp_error($response) || !is_array($response)) {
            return [];
        }
        return $response["headers"] ?? [];
    }
}
