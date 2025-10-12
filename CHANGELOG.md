# Changelog

## [1.2.1] - 2025-10-11

### Changed
- **PHP Version Requirement**: Lowered minimum PHP version from 8.3 to 8.2 for broader compatibility
- **Documentation Updates**: Updated all documentation, examples, and tests to reflect PHP 8.2 requirement
- **Workflow Updates**: Updated GitHub Actions workflow to reflect PHP 8.2 requirement

### Testing
- **51 Tests Passing**: All tests verified with PHP 8.2 compatibility
- **Test Fixtures Updated**: Updated all test fixtures and expectations for PHP 8.2

## [1.2.0] - 2025-10-10

### Changed
- **Documentation Consolidation**: Centralized all documentation into README.md, CHANGELOG.md, and .github/copilot-instructions.md
- **Removed Separate Documentation Files**: Eliminated `docs/` directory to maintain simpler, more maintainable documentation structure
- **Testing Documentation**: Moved comprehensive testing guide to README.md Development section
- **Troubleshooting Guide**: Integrated troubleshooting information directly into README.md

### Testing
- **51 Tests, 130 Assertions**: Complete test suite with 100% pass rate
- **Real GitHub API Integration Tests**: 9 tests making actual HTTP requests to production repositories
- **Test Coverage**: Unit tests (3), Integration tests (22), WordPress tests (26)
- **Performance Verification**: Caching performance tests confirm < 10ms for cached API calls

## [1.1.5] - 2025-10-10
### Fixed
- **PCLZIP_ERR_MISSING_FILE (-4) Resolution**: Complete rewrite of `upgrader_pre_download` filter to properly handle all download scenarios
- **Download Filter Return Values**: Fixed critical issue where filter could return invalid types causing WordPress to fail with PCLZIP errors
- **Better Plugin Detection**: Added robust verification to ensure filter only intercepts downloads for the correct plugin
- **Enhanced Error Handling**: Comprehensive error messages for all failure points in the download process
- **File Verification**: Added multiple validation checks (file size, readability, existence) before returning downloaded file to WordPress

### Changed
- **Stricter Filter Logic**: `maybeFixDownload()` now returns `false` to let WordPress handle downloads that aren't for our plugin
- **Safety Checks**: Added verification of `hook_extra` data to ensure we only process downloads for our specific plugin
- **Improved Documentation**: Enhanced PHPDoc comments explaining critical return value requirements for WordPress compatibility
- **Download Process**: Better handling of HTTP response codes and empty responses with descriptive error messages

### Technical Improvements
- **Return Type Enforcement**: Strict enforcement of `string|WP_Error|false` return types (never `true` or other types)
- **Multi-line Conditionals**: Improved code formatting to meet WordPress Coding Standards (120 character line limit)
- **Defensive Programming**: Added early returns for edge cases where previous filters have already handled the download
- **Minimum File Size Check**: Validates downloaded file is at least 100 bytes before considering it valid

## [1.1.4] - 2025-08-29
### Added
- **WordPress Admin Notices**: Integrated admin notification system that displays update availability after manual version checks
- **Dismissible Update Notices**: Users can dismiss update notifications with built-in AJAX functionality
- **Admin Notice Management**: New `showUpdateNotice()` method creates WordPress-compliant admin notices with proper styling
- **AJAX Notice Dismissal**: New `dismissUpdateNotice()` AJAX handler for seamless notice management
- **Transient-Based Notifications**: Update notices persist for the same duration as version cache (configurable via `cache_duration`)

### Changed
- **Improved Manual Version Checks**: Enhanced `manualVersionCheck()` method now sets admin notices for immediate user feedback
- **Code Refactoring**: Centralized update availability logic using `isUpdateAvailable()` method to eliminate code duplication
- **Better WordPress Integration**: Manual version checks now properly clear WordPress update transients for immediate admin interface updates
- **Enhanced User Experience**: Update checks provide both AJAX responses and persistent admin notifications

### Fixed
- **WordPress Admin Sync**: Manual version checks now immediately reflect in WordPress admin plugins page
- **Transient Cache Management**: Proper clearing of both plugin-specific and WordPress update caches
- **Admin Interface Updates**: Resolved disconnect between manual checks and WordPress admin display

### Technical Improvements
- **DRY Principle**: Replaced duplicate version comparison logic with centralized `isUpdateAvailable()` method calls
- **AJAX Security**: Enhanced nonce verification and sanitization for all AJAX endpoints
- **WordPress Standards**: All admin notices follow WordPress UI/UX guidelines with proper escaping and styling
- **JavaScript Integration**: Inline JavaScript for notice dismissal with jQuery compatibility

### Documentation
- **API Documentation**: Added comprehensive Public API Methods section to README
- **Integration Examples**: Updated all examples to demonstrate new admin notice features
- **Configuration Guide**: Enhanced advanced configuration examples with new capabilities
- **Code Examples**: Programmatic version checking examples for developers

## [1.1.3] - 2025-08-29
### Added
- **Enhanced Temporary File Handling**: Implemented multiple fallback strategies for temporary file creation to resolve `PCLZIP_ERR_MISSING_FILE (-4)` errors
- **Custom Temporary Directory Support**: New `custom_temp_dir` configuration option in UpdaterConfig for specifying alternative temporary directories
- **Automatic Directory Creation**: The updater now attempts to create temporary directories if they don't exist
- **Comprehensive File Verification**: Added file existence and readability checks after download to prevent installation failures

### Changed
- **Improved Download Reliability**: Enhanced `maybeFixDownload()` method with better error handling and multiple fallback strategies
- **Robust Temporary File Strategy**: Six-tier fallback system for temporary file creation:
  1. Custom temporary directory (if configured)
  2. WordPress uploads directory
  3. WP_CONTENT_DIR/temp (auto-created)
  4. WP_TEMP_DIR (if defined in wp-config.php)
  5. System temporary directory
  6. Manual file creation as last resort

### Fixed
- **PCLZIP Error Resolution**: Addresses `PCLZIP_ERR_MISSING_FILE (-4)` errors caused by restrictive /tmp directory permissions
- **File Write Verification**: Added byte-level verification to ensure complete file downloads
- **Permission Issues**: Better handling of directory permission problems during plugin updates

### Documentation
- **Integration Examples**: Added examples for handling PCLZIP errors in integration guide
- **WordPress Configuration**: Documented wp-config.php approach for setting custom temporary directories
- **Troubleshooting Guide**: Comprehensive examples for different temporary directory configuration strategies

## [1.1.2] - 2025-08-19
### Changed
- **API Accessibility**: Changed `getLatestVersion()` method visibility from `private` to `public` to allow external access from consuming plugins

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
