# Changelog

## [1.0.1] - 2025-08-07
### Added
- Markdown to HTML parser for changelog display
- Support for headers (#, ##, ###, ####), bold text (**text**), italic text (*text*), inline code (`code`), lists (- item), and links ([text](url))
- Improved changelog readability in WordPress plugin update modal

### Changed
- Enhanced changelog formatting from raw markdown to formatted HTML

## [1.0.0] - 2025-08-07
### Added

- Initial release
- WordPress plugin GitHub updater functionality
- Configurable updater with UpdaterConfig class
- Automatic update integration with WordPress
- Manual AJAX version checking
- Changelog fetching from GitHub releases
- Transient caching for performance
- PSR-4 autoloading
- Comprehensive documentation
- AJAX nonce verification
- WordPress capability checks
