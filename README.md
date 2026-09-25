# WordPress GitHub Updater

[![Latest Version on Packagist](https://img.shields.io/packagist/v/silverassist/wp-github-updater.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-github-updater)
[![Software License](https://img.shields.io/badge/license-PolyForm--Noncommercial--1.0.0-blue.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/silverassist/wp-github-updater.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-github-updater)

A reusable WordPress plugin updater that handles automatic updates from GitHub releases, public or private. Perfect for WordPress plugins distributed outside the official repository.

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
- 🎨 **Built-in JavaScript** (v1.3.0+): Complete update check UI with no custom code needed
- 🔒 **Private repositories** (v1.4.0+): Reads releases from a private GitHub repository with a token

## Installation

Install via Composer in your WordPress plugin:

```bash
composer require silverassist/wp-github-updater
```

## Installing via Composer (private repository)

SilverAssist packages are installed from their GitHub repositories with a Composer
`vcs` repository, not from Packagist.org. Those repositories can require
authentication, so always configure a token.

1. **Declare the repository** in your project's root `composer.json`. Composer only
   reads `repositories` from the root package, so every SilverAssist package your
   project needs must be listed there, including transitive ones:

   ```json
   {
     "repositories": [
       { "type": "vcs", "url": "https://github.com/SilverAssist/wp-github-updater", "no-api": true }
     ],
     "require": {
       "silverassist/wp-github-updater": "^1.3"
     }
   }
   ```

2. **Authenticate** with a GitHub token that can read the repository:
   - Locally: `composer config --global github-oauth.github.com <token>`
   - CI: set `COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}'` from a secret
     (a GitHub Actions secret or a Bitbucket variable).

   Never commit a token or an `auth.json`. Without a token, Composer hits GitHub's
   anonymous API limit (60 requests per hour per IP) and falls back to an SSH clone.

   Keep `"no-api": true` on every `vcs` entry: it makes Composer read tags with git instead of
   the GitHub API. Without it, one install without a lock file costs about 100 API requests of
   the token's hourly quota (5,000, shared with the token owner's other API usage), which a busy
   CI can exhaust.

3. **Refresh the lock file** if your project commits `composer.lock`: run
   `composer update --lock` after adding `repositories`, so the lock file's
   `content-hash` matches `composer.json`.

This repository's own `composer.json` declares `vcs` repositories for its SilverAssist development dependencies (`coding-standards`), so contributors and CI need the same token.

### Troubleshooting

| Symptom | Cause |
|---------|-------|
| `Failed to clone the git@github.com:SilverAssist/wp-github-updater.git repository, try running in interactive mode...` followed by `Permission denied (publickey)` | The token is missing or has no access to the repository. |
| `remote: Invalid username or token` | The token is invalid or expired. |
| `it could not be found in any version` | The `vcs` entry is missing from the root `composer.json`. |

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
| `requires_php` | string | `'8.2'` | Minimum PHP version |
| `asset_pattern` | string | `'{slug}-v{version}.zip'` | GitHub release asset filename pattern |
| `cache_duration` | int | `43200` (12 hours) | Cache duration in seconds |
| `ajax_action` | string | `'check_plugin_version'` | AJAX action name for manual checks |
| `ajax_nonce` | string | `'plugin_version_check'` | AJAX nonce name |
| `text_domain` | string | `'wp-github-updater'` | WordPress text domain for i18n **(New in 1.1.0)** |
| `custom_temp_dir` | string\|null | `null` | Custom temporary directory path **(New in 1.1.3)** |
| `token_constant` | string | `'SILVER_GITHUB_TOKEN'` | Name of the PHP constant or environment variable that holds the GitHub token for private repositories **(New in 1.4.0)** |

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

## Private Repositories

Since 1.4.0 the updater can read the releases of a **private** GitHub repository. Public
repositories keep working exactly as before, with no token.

### Configure the token

The token is read from a PHP constant first, then from an environment variable, both named by the
`token_constant` option (default `SILVER_GITHUB_TOKEN`). It is never read from the database or from
a settings screen.

```php
// wp-config.php
define( 'SILVER_GITHUB_TOKEN', 'the-token' );
```

Or set `SILVER_GITHUB_TOKEN` in the environment of the PHP process. If your PHP setup does not pass
environment variables to its workers (for example PHP-FPM with `clear_env`), define the constant in
`wp-config.php` from your secret store instead.

The token needs read access to the repository's contents and releases. For a classic personal
access token that is the `repo` scope. Never commit the token to a repository.

To use another name, set the option:

```php
$config = new UpdaterConfig( $pluginFile, 'owner/private-repo', [
    'token_constant' => 'MY_PLUGIN_GITHUB_TOKEN',
] );
```

### How it works

- The token is sent as `Authorization: Bearer` to `api.github.com` only, never to any other host.
- A private release asset is downloaded through its API URL. GitHub answers with a redirect to a
  signed storage URL, which the updater follows **without** the token.
- The release must have a ZIP asset attached, as for public repositories.
- A failed lookup is not cached, so the next check asks again.

### Troubleshooting

When a site stops seeing updates, the PHP error log says why. The token is never written to it.

| Log message | Meaning |
|-------------|---------|
| `GitHub rejected the token (HTTP 401)` | The token is invalid or expired. Replace it |
| `HTTP 403: the token has no access to the repository, or the rate limit was reached` | The token cannot read the repository, or GitHub is rate limiting it |
| `HTTP 403: the rate limit was reached or the repository is private. Define SILVER_GITHUB_TOKEN to authenticate` | No token is configured and GitHub refused the anonymous request |
| `HTTP 404: the release does not exist, or the token has no access to the repository` | Check the repository name, that the release exists, and the token's access |
| `HTTP 404: not found. If the repository is private, define SILVER_GITHUB_TOKEN` | The repository is private and no token is configured |

### Testing against a private repository

The behaviour tests run on the WordPress Test Suite and intercept requests with the
`pre_http_request` filter, so they never reach the network. An opt-in live test checks the real
GitHub API against a private repository that has a release with a ZIP asset:

```bash
WPGU_LIVE_REPO=owner/private-repo WPGU_LIVE_VERSION=1.2.3 SILVER_GITHUB_TOKEN=... \
  vendor/bin/phpunit --testsuite wordpress --group external-http
```

## Manual Version Check

The updater provides AJAX endpoints for manual version checking. Starting with **version 1.3.0**, the package includes a built-in JavaScript solution that eliminates the need for custom scripts in consuming plugins.

### Built-in Check Updates Script (v1.3.0+)

The package now includes a complete JavaScript solution for manual update checks. Simply call `enqueueCheckUpdatesScript()` to get a one-liner that handles everything:

```php
// In your plugin's admin settings page or Settings Hub integration
public function render_update_check_button(): void {
    echo '<button onclick="' . $this->updater->enqueueCheckUpdatesScript() . '">Check Updates</button>';
}
```

**That's it!** No need to:
- Create your own JavaScript file
- Manually enqueue scripts
- Handle `wp_localize_script` calls
- Duplicate admin notice logic
- Manage i18n strings

#### How It Works

The `enqueueCheckUpdatesScript()` method:

1. **Enqueues the shared JavaScript file** (`assets/js/check-updates.js`) - loaded once even if multiple plugins use it
2. **Localizes plugin-specific data** - unique global variable per plugin to avoid conflicts
3. **Returns inline JavaScript** - ready to use in `onclick` attributes or `<script>` tags
4. **Handles all i18n strings** - uses your plugin's text domain automatically
5. **Shows WordPress admin notices** - success, error, warning, and info messages
6. **Redirects on updates** - automatically redirects to Updates page when an update is available

#### Advanced Usage with Custom Strings

You can override default i18n strings if needed:

```php
$customStrings = [
    "checking"        => $this->__("Verifying latest version..."),
    "updateAvailable" => $this->__("New version %s is available! Redirecting..."),
    "upToDate"        => $this->__("Your plugin is up to date!"),
    "checkError"      => $this->__("Could not check for updates. Try again later."),
    "connectError"    => $this->__("Connection error. Check your internet."),
    "configError"     => $this->__("Configuration error occurred."),
];

echo '<button onclick="' . $this->updater->enqueueCheckUpdatesScript($customStrings) . '">Check Updates</button>';
```

#### Multi-Plugin Support

The built-in script works perfectly when multiple plugins using `wp-github-updater` are active on the same page. Each plugin gets its own:

- Unique global variable name (e.g., `wpGithubUpdater_my_plugin`)
- Separate AJAX configuration
- Independent admin notices
- Isolated error handling

**Example**: If you have both `silver-assist-security` and `contact-form-to-api` using the updater, each gets its own check-updates button that works independently without conflicts.

#### Migration from Custom Scripts

**Before** (custom per-plugin implementation):

```php
// ~40 lines of PHP + ~120 lines of JavaScript
public function render_update_check_script(): void {
    wp_enqueue_script('my-plugin-check-updates', plugins_url('js/check-updates.js'), ['jquery'], '1.0.0', true);
    wp_localize_script('my-plugin-check-updates', 'myPluginCheckUpdatesData', [
        'ajaxurl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('my_plugin_nonce'),
        'action'    => 'my_plugin_check_version',
        'updateUrl' => admin_url('update-core.php'),
        'strings'   => [ /* i18n strings */ ],
    ]);
    echo 'myPluginCheckUpdates(); return false;';
}
```

**After** (using built-in script):

```php
// 1 line
public function render_update_check_script(): void {
    echo $this->updater->enqueueCheckUpdatesScript();
}
```

### Legacy Manual AJAX Implementation

If you need more control or are maintaining existing code, you can still implement manual AJAX calls:

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
- GitHub repository with releases (public, or private with a token, see Private Repositories)

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

### PHPUnit Version Policy

**This package uses PHPUnit 9.6.x and MUST remain on this version.**

**Why PHPUnit 9.6?**
- ✅ **WordPress Ecosystem Standard**: Most WordPress projects use PHPUnit 9.6
- ✅ **WordPress Coding Standards Compatible**: [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) uses PHPUnit 9.x
- ✅ **Yoast PHPUnit Polyfills**: Version 4.x supports PHPUnit 7.5-9.x, 11.x, 12.x, but **NOT 10.x**
- ✅ **Consumer Compatibility**: Projects depending on this package expect PHPUnit 9.6

**Do NOT upgrade to:**
- ❌ PHPUnit 10.x (incompatible with Yoast PHPUnit Polyfills 4.x)
- ❌ PHPUnit 11.x or 12.x (breaks compatibility with most WordPress projects)

**Dependabot Configuration:**
The `.github/dependabot.yml` file is configured to automatically ignore PHPUnit major version updates, ensuring the package remains on 9.x.

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