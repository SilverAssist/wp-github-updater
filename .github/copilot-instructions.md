# Copilot Instructions for WP GitHub Updater Package

## 📋 Documentation Policy

**CRITICAL: ALL documentation must be consolidated in these three files ONLY:**

1. **README.md** - User-facing documentation, installation, usage examples, API reference
2. **CHANGELOG.md** - Version history, changes, bug fixes, new features
3. **.github/copilot-instructions.md** - This file, development guidelines and architecture

**🚫 NEVER create separate documentation files:**
- ❌ Do NOT create files like `docs/GUIDE.md`, `docs/TESTING.md`, `TROUBLESHOOTING.md`, etc.
- ❌ Do NOT create a `docs/` directory
- ❌ Do NOT split documentation into multiple MD files

**✅ Instead:**
- Add user documentation to appropriate sections in README.md
- Add development/architecture notes to this copilot-instructions.md file
- Add version changes to CHANGELOG.md
- Use inline code comments for complex logic explanation

**Rationale:** Centralized documentation is easier to maintain, search, and keep in sync with code changes.

---

## Architecture Overview

This is a **reusable Composer package** that provides WordPress plugin update functionality from GitHub releases. The package is designed to be integrated into various WordPress plugins, not as a standalone plugin.

## Core Technologies

- **PHP 8.3+**: Modern PHP with strict typing, union types, and nullable parameters
- **WordPress 6.0+**: WordPress update system integration via hooks and filters
- **PSR-4 Autoloading**: Namespace-based class loading for better organization
- **GitHub API v3**: REST API integration for release management
- **Composer Package**: Distributed via Packagist as `silverassist/wp-github-updater`

## Project Structure

```
wp-github-updater/
├── .github/
│   └── copilot-instructions.md
├── src/
│   ├── Updater.php              # Main updater class
│   └── UpdaterConfig.php        # Configuration management
├── tests/
│   └── UpdaterConfigTest.php    # Unit tests
├── examples/
│   └── integration-guide.php    # Integration examples
├── composer.json                # Package definition
├── README.md                    # Documentation
├── CHANGELOG.md                 # Version history
├── LICENSE.md                   # PolyForm Noncommercial 1.0.0 license
├── phpcs.xml                    # Code standards
├── phpstan.neon                 # Static analysis
└── phpunit.xml                  # Test configuration
```

## Component Architecture

### UpdaterConfig Class
**Purpose**: Configuration management for the updater functionality
**Key Responsibilities**:
- Store plugin metadata (name, version, file path, etc.)
- GitHub repository configuration
- Cache duration and WordPress requirements
- AJAX endpoints and nonce configuration
- Plugin data parsing from WordPress headers
- **Custom temporary directory configuration** (v1.1.3+)

### Updater Class
**Purpose**: Core WordPress integration and GitHub API communication
**Key Responsibilities**:
- WordPress update system hooks integration
- GitHub API communication with caching
- Plugin update checking and information display
- Download error handling and recovery
- AJAX endpoints for manual update checks and notice dismissal
- Markdown-to-HTML conversion for changelogs
- **Enhanced temporary file management with PCLZIP error resolution** (v1.1.3+)
- **WordPress admin notices for manual update checks** (v1.1.3+)
- **Dismissible admin notices with AJAX handling** (v1.1.4+)

### Security
- **Nonce verification** for AJAX requests
- **Capability checks** for update operations
- **Input sanitization** using WordPress functions
- **Output escaping** for all displayed content
- **WordPress transients** for secure caching

## Configuration & Settings

### Internationalization Strategy
Since this is a **reusable Composer package**, internationalization should be handled by the **consuming plugin**, not the package itself. The package should:

1. **NO hardcoded text domain** - Allow consuming plugin to pass text domain
2. **Accept text domain parameter** in UpdaterConfig constructor
3. **Pass text domain to all i18n functions** from consuming plugin
4. **Provide translation function wrappers** that use the passed text domain

```php
// ✅ CORRECT - Package approach
class UpdaterConfig {
    private string $textDomain;
    
    public function __construct(array $options) {
        $this->textDomain = $options["text_domain"] ?? "wp-github-updater";
    }
    
    private function translate(string $text): string {
        return \__($text, $this->textDomain);
    }
}

// ❌ INCORRECT - Fixed text domain in package
\__("Update available", "wp-github-updater");
```

### Temporary File Management Strategy
**New in v1.1.3**: Enhanced handling to resolve `PCLZIP_ERR_MISSING_FILE (-4)` errors

#### Multi-Tier Fallback System
The package implements a robust 6-tier fallback system for temporary file creation:

1. **Custom temporary directory** - User-specified via `custom_temp_dir` option
2. **WordPress uploads directory** - Most reliable, within web root
3. **WP_CONTENT_DIR/temp** - Auto-created if needed
4. **WP_TEMP_DIR** - If defined in wp-config.php
5. **System temporary directory** - OS default /tmp or equivalent
6. **Manual file creation** - Last resort fallback

#### Configuration Options
```php
$config = new UpdaterConfig($pluginFile, $githubRepo, [
    "custom_temp_dir" => WP_CONTENT_DIR . "/temp",        // Custom directory
    // or
    "custom_temp_dir" => wp_upload_dir()["basedir"] . "/temp", // Uploads subdirectory
]);
```

#### WordPress Configuration Alternative
```php
// In wp-config.php
define('WP_TEMP_DIR', ABSPATH . 'wp-content/temp');
```

### Coding Standards

- **WordPress Coding Standards**: Full compliance with WordPress PHP standards
- **PSR-4 Autoloading**: Proper namespace organization (`SilverAssist\WpGithubUpdater`)
- **Type Declarations**: Use PHP 8+ strict typing everywhere
- **Error Handling**: Comprehensive error handling with WordPress WP_Error
- **Security First**: Nonce verification and capability checks

### WordPress Integration

- **Hooks & Filters**: Use appropriate WordPress hooks for update functionality
- **Transient Caching**: Leverage WordPress transients for GitHub API caching
- **Plugin API**: Integrate with WordPress plugin update system
- **HTTP API**: Use wp_remote_get() for GitHub API communication
- **File System**: Use WordPress file system functions for downloads

### Security Best Practices

- **Input Validation**: Sanitize all user inputs using WordPress functions
- **Output Escaping**: Escape all outputs using appropriate WordPress functions
- **Nonce Verification**: Use WordPress nonces for AJAX security
- **Capability Checks**: Verify user permissions before update operations
- **HTTP Security**: Proper User-Agent headers and timeout handling

### Release Process

- **Version Bumping**: Update version in composer.json and CHANGELOG.md
- **Git Tagging**: Create semantic version tags (v1.0.0, v1.1.0, etc.)
- **Documentation**: Update README and integration examples
- **Packagist**: Automatic distribution via Packagist on tag push

### Core Structure

```php
namespace SilverAssist\WpGithubUpdater;

class Updater {
    private UpdaterConfig $config;
    private string $pluginSlug;
    private string $currentVersion;
    // ... other properties

    public function __construct(UpdaterConfig $config) {
        // Initialize updater with configuration
    }

    public function checkForUpdate($transient) {
        // WordPress update check integration using isUpdateAvailable() method
    }

    public function pluginInfo($result, string $action, object $args) {
        // Plugin information display
    }

    public function manualVersionCheck(): void {
        // AJAX handler for manual version checks with admin notice creation
    }

    public function dismissUpdateNotice(): void {
        // AJAX handler for dismissing admin notices (v1.1.4+)
    }

    public function showUpdateNotice(): void {
        // WordPress admin notice display for available updates (v1.1.4+)
    }

    public function maybeFixDownload($result, string $package, object $upgrader, array $hook_extra) {
        // Enhanced download handling with PCLZIP error resolution (v1.1.3+)
    }

    private function createSecureTempFile(string $package) {
        // Multi-tier temporary file creation with fallback strategies (v1.1.3+)
    }

    public function isUpdateAvailable(): bool {
        // Centralized update availability check (refactored in v1.1.4+)
    }

    public function getLatestVersion(): string|false {
        // Public API method for external access (v1.1.2+)
    }

    // ... other methods
}
```

## 🚨 CRITICAL CODING STANDARDS - MANDATORY COMPLIANCE

### String Quotation Standards
- **MANDATORY**: ALL strings in PHP MUST use double quotes: `"string"`
- **i18n Functions**: ALL WordPress i18n functions MUST use double quotes: `__("Text", $textDomain)`
- **FORBIDDEN**: Single quotes for strings: `'string'` or `__('text', 'domain')`
- **Exception**: Only use single quotes inside double-quoted strings when necessary
- **SQL Queries**: Use double quotes for string literals in SQL: `WHERE option_value = "1"`

### Documentation Requirements
- **PHP**: Complete PHPDoc documentation for ALL classes, methods, and properties
- **@since tags**: Required for all public APIs with version numbers
- **English only**: All documentation must be in English for international collaboration
- **Package context**: Document as library package, not standalone plugin

### WordPress i18n Standards for Packages
- **NO fixed text domain**: Accept text domain from consuming plugin
- **Translation wrapper methods**: Provide internal translation methods
- **Consuming plugin responsibility**: Let the consuming plugin handle actual translations
- **Flexible configuration**: Allow text domain to be passed in configuration

#### Text Domain Configuration Example
```php
class UpdaterConfig {
    private string $textDomain;
    
    public function __construct(array $options) {
        $this->textDomain = $options["text_domain"] ?? "wp-github-updater";
    }
    
    private function __($text): string {
        return \__($text, $this->textDomain);
    }
    
    private function esc_html__($text): string {
        return \esc_html__($text, $this->textDomain);
    }
}
```

## Modern PHP 8+ Conventions

### Type Declarations
- **Strict typing**: All methods use parameter and return type declarations
- **Nullable types**: Use `?Type` for optional returns (e.g., `?string`)
- **Property types**: All class properties have explicit types
- **Union types**: Use `string|false` for methods that can return string or false

### PHP Coding Standards
- **Double quotes for all strings**: `"string"` not `'string'` - MANDATORY
- **String interpolation**: Use `"prefix_{$variable}"` instead of concatenation
- **Short array syntax**: `[]` not `array()`
- **Namespaces**: Use descriptive namespace `SilverAssist\WpGithubUpdater`
- **WordPress hooks**: `\add_action("init", [$this, "method"])` with array callbacks
- **PHP 8+ Features**: Match expressions, array spread operator, typed properties

### Function Prefix Usage - MANDATORY COMPLIANCE

**🚨 CRITICAL RULE: Use `\` prefix for ALL WordPress functions in namespaced context, but NOT for PHP native functions**

```php
// ✅ CORRECT - WordPress functions REQUIRE \ prefix in namespaced context
\add_action("init", [$this, "method"]);
\add_filter("pre_set_site_transient_update_plugins", [$this, "checkForUpdate"]);
\get_option("option_name", "default");
\wp_remote_get($url, $args);
\get_transient($key);
\set_transient($key, $value, $expiration);
\plugin_basename($file);
\get_plugin_data($file);

// ✅ CORRECT - WordPress i18n functions REQUIRE \ prefix
\__("Text to translate", $this->textDomain);
\esc_html__("Text to translate", $this->textDomain);
\wp_kses_post($content);

// ✅ CORRECT - PHP native functions do NOT need \ prefix
array_key_exists($key, $array);
json_decode($response, true);
version_compare($version1, $version2, "<");
preg_replace("/pattern/", "replacement", $string);
basename($path);
fwrite($handle, $data);

// ❌ INCORRECT - Missing \ prefix for WordPress functions
add_action("init", [$this, "method"]);
get_transient($key);
wp_remote_get($url);

// ❌ INCORRECT - Don't use \ with PHP native functions
\json_decode($response);
\version_compare($v1, $v2);
\basename($path);
```

### Package-Specific Function Categories

#### **WordPress Functions (ALL need `\` prefix):**
- **Update System**: `\add_filter("pre_set_site_transient_update_plugins")`, `\add_filter("plugins_api")`
- **Plugin Functions**: `\plugin_basename()`, `\get_plugin_data()`, `\plugin_dir_path()`
- **HTTP API**: `\wp_remote_get()`, `\wp_remote_retrieve_body()`, `\is_wp_error()`
- **Transients**: `\get_transient()`, `\set_transient()`, `\delete_transient()`
- **Security**: `\wp_verify_nonce()`, `\current_user_can()`, `\wp_kses_post()`
- **File System**: `\wp_upload_dir()`, `\wp_tempnam()`, `\wp_filesystem()`

#### **PHP Native Functions (NO `\` prefix needed):**
- **Array Functions**: `array_key_exists()`, `array_merge()`, `count()`
- **String Functions**: `basename()`, `dirname()`, `pathinfo()`, `trim()`
- **JSON Functions**: `json_decode()`, `json_encode()`
- **Version Functions**: `version_compare()`
- **File Functions**: `fopen()`, `fwrite()`, `fclose()`
- **Regex Functions**: `preg_replace()`, `preg_match()`

## Development Workflow

### Integration Pattern
The package is designed to be integrated into WordPress plugins like this:

```php
// In the consuming plugin
use SilverAssist\WpGithubUpdater\UpdaterConfig;
use SilverAssist\WpGithubUpdater\Updater;

$config = new UpdaterConfig([
    "plugin_file" => __FILE__,
    "plugin_slug" => "my-plugin/my-plugin.php",
    "github_username" => "username",
    "github_repo" => "repository",
    "text_domain" => "my-plugin-domain", // Consumer's text domain
    "custom_temp_dir" => WP_CONTENT_DIR . "/temp", // Optional: Custom temp directory (v1.1.3+)
    // ... other options
]);

$updater = new Updater($config);
```

### Error Handling Strategy
- Use WordPress `WP_Error` class for error management
- Provide fallback download mechanisms for GitHub API failures
- Cache GitHub API responses to avoid rate limiting
- Handle PCLZIP errors with alternative download methods
- **PCLZIP_ERR_MISSING_FILE Resolution** (v1.1.3+): Multi-tier temporary file creation to avoid /tmp permission issues
- Cache GitHub API responses to avoid rate limiting
- Handle PCLZIP errors with alternative download methods

### Version Management
- Follow semantic versioning (MAJOR.MINOR.PATCH)
- Update composer.json version field
- Create git tags for each release
- Update CHANGELOG.md with detailed release notes
- Maintain backward compatibility within major versions

## Translation Support Strategy

Since this is a **reusable package**, translation should be handled by the **consuming plugin**:

1. **Package provides**: English text strings and translation function wrappers
2. **Consuming plugin provides**: Text domain and handles actual translation loading
3. **Configuration based**: Text domain passed via UpdaterConfig constructor
4. **Fallback domain**: Default to "wp-github-updater" if no text domain provided

This approach ensures the package can be integrated into any plugin while respecting the consuming plugin's translation strategy and text domain conventions.
