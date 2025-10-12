# WordPress GitHub Updater

[![Latest Version on Packagist](https://img.shields.io/packagist/v/silverassist/wp-github-updater.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-github-updater)
[![Software License](https://img.shields.io/badge/license-PolyForm--Noncommercial--1.0.0-blue.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/silverassist/wp-github-updater.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-github-updater)

A reusable WordPress plugin updater that handles automatic updates from public GitHub releases. Perfect for WordPress plugins distributed outside the official repository.

## Features

- 🔄 **Automatic Updates**: Seamlessly integrates with WordPress update system
- 🎯 **GitHub Integration**: Fetches releases directly from GitHub API
- ⚡ **Caching**: Built-in transient caching for performance
- 🛡️ **Security**: AJAX nonce verification and capability checks
- 🔧 **Configurable**: Flexible configuration options
- 📦 **Easy Integration**: Simple Composer installation
- 📢 **Admin Notices**: WordPress admin notifications for available updates
- 🗂️ **Enhanced File Handling**: Multi-tier temporary file creation to resolve hosting issues
- ✅ **Manual Version Checks**: AJAX-powered manual update checking with immediate admin feedback

## Installation

Install via Composer in your WordPress plugin:

```bash
composer require silverassist/wp-github-updater
```

## Quick Start

### Basic Usage

```php
<?php
// In your main plugin file

use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

// Create configuration
$config = new UpdaterConfig(
    __FILE__,                    // Path to your main plugin file
    'your-username/your-repo'    // GitHub repository (owner/repo)
);

// Initialize updater
$updater = new Updater($config);
```

### Advanced Configuration

```php
<?php
use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

$config = new UpdaterConfig(
    __FILE__,
    'your-username/your-repo',
    [
        'plugin_name' => 'My Awesome Plugin',
        'plugin_description' => 'A description of my plugin',
        'plugin_author' => 'Your Name',
        'plugin_homepage' => 'https://your-website.com',
        'requires_wordpress' => '6.0',
        'requires_php' => '8.3',
        'asset_pattern' => '{slug}-v{version}.zip', // GitHub release asset filename
        'cache_duration' => 12 * 3600, // 12 hours in seconds
        'ajax_action' => 'my_plugin_check_version',
        'ajax_nonce' => 'my_plugin_version_nonce',
        'text_domain' => 'my-plugin-textdomain', // For internationalization
        'custom_temp_dir' => WP_CONTENT_DIR . '/temp', // Custom temporary directory (v1.1.3+)
    ]
);

$updater = new Updater($config);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `plugin_name` | string | From plugin header | Plugin display name |
| `plugin_description` | string | From plugin header | Plugin description |
| `plugin_author` | string | From plugin header | Plugin author name |
| `plugin_homepage` | string | GitHub repo URL | Plugin homepage URL |
| `requires_wordpress` | string | `'6.0'` | Minimum WordPress version |
| `requires_php` | string | `'8.3'` | Minimum PHP version |
| `asset_pattern` | string | `'{slug}-v{version}.zip'` | GitHub release asset filename pattern |
| `cache_duration` | int | `43200` (12 hours) | Cache duration in seconds |
| `ajax_action` | string | `'check_plugin_version'` | AJAX action name for manual checks |
| `ajax_nonce` | string | `'plugin_version_check'` | AJAX nonce name |
| `text_domain` | string | `'wp-github-updater'` | WordPress text domain for i18n **(New in 1.1.0)** |
| `custom_temp_dir` | string\|null | `null` | Custom temporary directory path **(New in 1.1.3)** |

### Internationalization Support (i18n)

Starting with version 1.1.0, you can specify a custom text domain for your plugin's translations:

```php
$config = new UpdaterConfig(__FILE__, "username/repository", [
    "text_domain" => "my-plugin-textdomain" // Use your plugin's text domain
]);
```

**Backward Compatibility**: Existing code without the `text_domain` option will continue to work using the default `wp-github-updater` text domain.

## GitHub Release Requirements

For the updater to work properly, your GitHub releases should:

1. **Use semantic versioning**: `v1.0.0`, `v1.2.3`, etc.
2. **Include release assets**: ZIP files with your plugin code
3. **Follow naming convention**: Use the `asset_pattern` configuration

### Example Release Asset Names

If your plugin slug is `my-awesome-plugin` and version is `1.2.3`:

- Default pattern: `my-awesome-plugin-v1.2.3.zip`
- Custom pattern: `my-plugin-1.2.3.zip` (using `{slug}-{version}.zip`)

## Manual Version Check

The updater provides AJAX endpoints for manual version checking:

```javascript
// Frontend JavaScript example
jQuery.post(ajaxurl, {
    action: 'your_ajax_action', // From configuration
    nonce: 'your_nonce_value',  // Generate with wp_create_nonce()
}, function(response) {
    if (response.success) {
        console.log('Current version:', response.data.current_version);
        console.log('Latest version:', response.data.latest_version);
        console.log('Update available:', response.data.update_available);
    }
});
```

## WordPress Integration

The updater integrates with WordPress's built-in update system:

- **Plugin Updates Page**: Shows available updates
- **Plugin Information Modal**: Displays changelog and plugin details
- **Automatic Updates**: Works with WordPress automatic updates
- **Bulk Updates**: Supports bulk update operations

## Example Implementation

Here's a complete example for a WordPress plugin:

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: An awesome plugin with auto-updates
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 6.0
 * Requires PHP: 8.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use SilverAssist\WpGithubUpdater\Updater;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

// Initialize updater
add_action('init', function() {
    $config = new UpdaterConfig(
        __FILE__,
        'your-username/my-awesome-plugin',
        [
            'asset_pattern' => 'my-awesome-plugin-{version}.zip',
            'ajax_action' => 'my_awesome_plugin_check_version',
            'ajax_nonce' => 'my_awesome_plugin_nonce'
        ]
    );
    
    new Updater($config);
});

// Your plugin code here...
```

## Troubleshooting

### PCLZIP_ERR_MISSING_FILE (-4) Error

If you encounter the error "The package could not be installed. PCLZIP_ERR_MISSING_FILE (-4)", this typically indicates issues with the temporary directory. The updater includes multiple fallback strategies to resolve this.

#### Solution 1: Custom Temporary Directory (Recommended)

```php
$config = new UpdaterConfig(
    __FILE__,
    'your-username/your-repo',
    [
        'custom_temp_dir' => WP_CONTENT_DIR . '/temp',
        // or use uploads directory:
        // 'custom_temp_dir' => wp_upload_dir()['basedir'] . '/temp',
    ]
);
```

#### Solution 2: WordPress Configuration

Add to your `wp-config.php` file (before "That's all, stop editing!"):

```php
/* Set WordPress temporary directory */
define('WP_TEMP_DIR', ABSPATH . 'wp-content/temp');
```

Then create the directory with proper permissions:

```bash
mkdir wp-content/temp
chmod 755 wp-content/temp
```

#### Solution 3: Plugin Activation Hook

Create the temporary directory when your plugin is activated:

```php
register_activation_hook(__FILE__, function() {
    $temp_dir = WP_CONTENT_DIR . '/temp';
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
});
```

### Multi-Tier Fallback System

The updater automatically tries multiple strategies for temporary file creation:

1. **Custom temporary directory** (if configured)
2. **WordPress uploads directory** 
3. **WP_CONTENT_DIR/temp** (auto-created)
4. **WP_TEMP_DIR** (if defined in wp-config.php)
5. **System temporary directory** (/tmp)
6. **Manual file creation** (last resort)

## Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher
- Composer for dependency management
- Public GitHub repository with releases

## Development

### Testing

The package includes comprehensive testing (51 tests, 130 assertions, 100% passing):

**Test Coverage:**
- **Unit Tests** (3 tests): Configuration and core functionality
- **Integration Tests** (22 tests): Updater + Config integration, download filters, **real GitHub API** ⭐
- **WordPress Tests** (26 tests): Hooks, filters, and mock plugin integration

**Running Tests:**

```bash
# Install development dependencies
composer install --dev

# Run all tests
composer test

# Run specific test suites
./scripts/test-runner.sh unit         # Unit tests only
./scripts/test-runner.sh integration  # Integration tests (includes real GitHub API)
./scripts/test-runner.sh wordpress    # WordPress integration tests
./scripts/test-runner.sh all          # All tests

# Run with coverage
vendor/bin/phpunit --coverage-text
```

**Real GitHub API Testing:**

The integration tests include **real HTTP requests** to production GitHub repositories to verify actual API behavior:

- ✅ Validates actual GitHub API response structure
- ✅ Verifies caching performance (< 10ms for cached calls)
- ✅ Tests version comparison with real releases
- ✅ Confirms asset pattern matching with production URLs

**Example: Test with Your Own Repository**

```php
// tests/Integration/MyRealAPITest.php
public function testFetchLatestVersionFromMyRepo(): void {
    $config = new UpdaterConfig([
        "plugin_file" => __FILE__,
        "github_username" => "YourUsername",
        "github_repo" => "your-repository",
    ]);
    
    $updater = new Updater($config);
    $version = $updater->getLatestVersion();
    
    $this->assertNotFalse($version);
    $this->assertMatchesRegularExpression("/^\d+\.\d+\.\d+$/", $version);
}
```

**Test Environment Setup:**

The tests use WordPress Test Suite for authentic WordPress integration:

```bash
# Install WordPress Test Suite (interactive)
./scripts/test-runner.sh install

# Or manual installation
./scripts/install-wp-tests.sh wordpress_test root '' localhost latest
```

**PCLZIP Error Testing:**

For testing plugins that may experience `PCLZIP_ERR_MISSING_FILE (-4)` errors, configure a custom temporary directory:

```php
$config = new UpdaterConfig(
    __FILE__,
    "your-username/your-plugin",
    [
        "custom_temp_dir" => WP_CONTENT_DIR . "/temp",
    ]
);
```

### Code Standards

```bash
composer phpcs  # Check standards
composer phpcbf # Fix standards
```

### Static Analysis

```bash
composer phpstan
```

### All Checks

```bash
composer check
```

## Public API Methods

The updater provides several public methods for programmatic access:

### Version Information

```php
// Check if an update is available
$hasUpdate = $updater->isUpdateAvailable(); // Returns bool

// Get the current plugin version
$currentVersion = $updater->getCurrentVersion(); // Returns string

// Get the latest version from GitHub (with caching)
$latestVersion = $updater->getLatestVersion(); // Returns string|false

// Get the GitHub repository
$repo = $updater->getGithubRepo(); // Returns string
```

### Manual Version Check

You can trigger a manual version check programmatically:

```php
// This will clear caches and check for updates
// If an update is available, it will set an admin notice
$updater->manualVersionCheck();
```

**Note**: The manual version check method is designed for AJAX calls and will send JSON responses. For programmatic use, prefer the individual methods above.

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is licensed under the Polyform Noncommercial License 1.0.0 (PolyForm-Noncommercial-1.0.0). This license allows for non-commercial use, modification, and distribution. Please see [License File](LICENSE.md) for more information.

## Credits

- [Silver Assist](https://github.com/SilverAssist)
- [All Contributors](../../contributors)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security related issues, please report them via [GitHub Issues](https://github.com/SilverAssist/wp-github-updater/issues) with the "security" label.

## Support

- **Documentation**: This README and code comments
- **Issues**: [GitHub Issues](https://github.com/SilverAssist/wp-github-updater/issues)

---

**Made with ❤️ by [Silver Assist](https://silverassist.com)**