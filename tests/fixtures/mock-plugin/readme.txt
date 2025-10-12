=== Mock Plugin for WP GitHub Updater Tests ===
Contributors: silverassist
Tags: testing, github, updater
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

A mock WordPress plugin used for testing the WP GitHub Updater package.

== Description ==

This is a test fixture plugin that demonstrates integration with the SilverAssist/WpGithubUpdater package.

It is used exclusively for automated testing and should not be used in production environments.

== Installation ==

This plugin is for testing purposes only and should not be installed on production sites.

For testing:
1. Install WordPress Test Suite using the provided install-wp-tests.sh script
2. The plugin will be automatically loaded in the test environment
3. Run PHPUnit tests with the WordPress test suite

== Changelog ==

= 1.0.0 =
* Initial release for testing purposes
* Demonstrates WP GitHub Updater integration
* Includes admin interface for manual testing
