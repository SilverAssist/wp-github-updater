# WordPress GitHub Updater

[![Latest Version on Packagist](https://img.shields.io/packagist/v/silverassist/wp-github-updater.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-github-updater)
[![Software License](https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/silverassist/wp-github-updater.svg?style=flat-square)](https://packagist.org/packages/silverassist/wp-github-updater)

A reusable WordPress plugin updater that handles automatic updates from public GitHub releases. Perfect for WordPress plugins distributed outside the official repository.

## Features

- 🔄 **Automatic Updates**: Seamlessly integrates with WordPress update system
- 🎯 **GitHub Integration**: Fetches releases directly from GitHub API
- ⚡ **Caching**: Built-in transient caching for performance
- 🛡️ **Security**: AJAX nonce verification and capability checks
- 🔧 **Configurable**: Flexible configuration options
- 📦 **Easy Integration**: Simple Composer installation

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
        'requires_php' => '8.0',
        'asset_pattern' => '{slug}-v{version}.zip', // GitHub release asset filename
        'cache_duration' => 12 * 3600, // 12 hours in seconds
        'ajax_action' => 'my_plugin_check_version',
        'ajax_nonce' => 'my_plugin_version_nonce'
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
| `requires_php` | string | `'8.0'` | Minimum PHP version |
| `asset_pattern` | string | `'{slug}-v{version}.zip'` | GitHub release asset filename pattern |
| `cache_duration` | int | `43200` (12 hours) | Cache duration in seconds |
| `ajax_action` | string | `'check_plugin_version'` | AJAX action name for manual checks |
| `ajax_nonce` | string | `'plugin_version_check'` | AJAX nonce name |

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
 * Requires PHP: 8.0
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

## Requirements

- PHP 8.0 or higher
- WordPress 6.0 or higher
- Composer for dependency management
- Public GitHub repository with releases

## Development

### Running Tests

```bash
composer test
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

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later). Please see [License File](LICENSE.md) for more information.

## Credits

- [Silver Assist](https://github.com/SilverAssist)
- [All Contributors](../../contributors)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security

If you discover any security related issues, please email security@silverassist.com instead of using the issue tracker.

## Support

- **Documentation**: This README and code comments
- **Issues**: [GitHub Issues](https://github.com/SilverAssist/wp-github-updater/issues)
- **Discussions**: [GitHub Discussions](https://github.com/SilverAssist/wp-github-updater/discussions)
