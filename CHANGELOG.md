# Changelog

## [1.1.1] - 2025-08-14
### Changed
- **License Migration**: Updated from GPL v2.0+ to PolyForm Noncommercial 1.0.0
- **License References**: Updated all references in composer.json, README.md, source files, and GitHub Actions workflow
- **License Documentation**: Updated license badges and documentation to reflect noncommercial licensing

## [1.1.0] - 2025-08-12
### Added
- **Configurable text domain support**: New `text_domain` option in `UpdaterConfig` constructor for internationalization flexibility
- **Translation wrapper methods**: Added `__()` and `esc_html__()` methods for package-aware translations
- **Translatable user-facing messages**: All changelog and error messages now support internationalization
- **Centralized HTTP header management**: Added `getApiHeaders()` and `getDownloadHeaders()` methods for consistent API communication
- **Comprehensive PHP coding standards**: Implemented phpcs.xml with WordPress and PSR-12 standards enforcement
- **String quotation standardization**: All strings now consistently use double quotes as per project standards
- **Enhanced type declarations**: Full PHP 8+ type hint coverage with union types and nullable parameters
- **Enhanced PHPDoc documentation**: Comprehensive descriptions and `@since` annotations throughout codebase
- **GitHub Actions workflow**: Automated release creation when tags are pushed
- **Automated testing in CI**: PHPUnit and PHPCS validation in release pipeline
- **Release documentation**: Automated generation of release notes from CHANGELOG.md
- **Package validation**: Automated structure validation and version consistency checks

### Changed
- **Improved internationalization architecture**: Text domain now configurable per consuming plugin instead of hardcoded
- **Centralized translation system**: All user-facing strings now use configurable text domain with fallback support
- **Refactored HTTP request configurations**: Eliminated code duplication through centralized header management patterns
- **Code quality enforcement**: Added automated coding standards checking with phpcs and WordPress security rules
- **Documentation standards**: Enhanced PHPDoc blocks with complete parameter and return type documentation
- **Updated User-Agent headers**: Now include version information (WP-GitHub-Updater/1.1.0)

### Fixed
- **Backward compatibility**: Existing code without `text_domain` specification continues working with `wp-github-updater` fallback
- **String consistency**: Eliminated mixed quote usage throughout codebase for improved maintainability
- **Security compliance**: Enhanced input sanitization and output escaping validation
- **GitHub API request reliability**: Improved through consistent header usage
- **Download stability**: Optimized headers for GitHub asset downloads

### Technical Improvements
- Updated User-Agent headers to version 1.1.0
- Added Composer dev dependencies: `wp-coding-standards/wpcs` and `slevomat/coding-standard`
- Implemented comprehensive test coverage for new translation features
- Enhanced error handling with proper WordPress i18n integration
- Improved code maintainability through centralized header management patterns

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
