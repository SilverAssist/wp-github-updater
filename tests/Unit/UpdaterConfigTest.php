<?php

namespace SilverAssist\WpGithubUpdater\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

class UpdaterConfigTest extends TestCase
{
    private static string $testPluginFile;

    /**
     * Resolve the shared test plugin fixture path once for the whole class.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$testPluginFile = dirname(__DIR__) . "/fixtures/test-plugin.php";
    }

    /**
     * Test that UpdaterConfig applies its documented defaults.
     *
     * @return void
     */
    public function testBasicConfiguration(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo");

        $this->assertEquals(self::$testPluginFile, $config->pluginFile);
        $this->assertEquals("owner/repo", $config->githubRepo);
        $this->assertEquals("6.0", $config->requiresWordPress);
        $this->assertEquals("8.2", $config->requiresPHP);
        $this->assertEquals("{slug}-v{version}.zip", $config->assetPattern);
        $this->assertEquals("wp-github-updater", $config->textDomain);
    }

    /**
     * Test that UpdaterConfig applies caller-supplied option overrides.
     *
     * @return void
     */
    public function testCustomConfiguration(): void
    {
        $options = [
            "plugin_name" => "Test Plugin",
            "plugin_description" => "A test plugin",
            "plugin_author" => "Test Author",
            "requires_wordpress" => "6.2",
            "requires_php" => "8.1",
            "asset_pattern" => "custom-{version}.zip",
            "cache_duration" => 3600,
            "ajax_action" => "custom_check",
            "ajax_nonce" => "custom_nonce",
            "text_domain" => "my-custom-plugin"
        ];

        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", $options);

        $this->assertEquals("Test Plugin", $config->pluginName);
        $this->assertEquals("A test plugin", $config->pluginDescription);
        $this->assertEquals("Test Author", $config->pluginAuthor);
        $this->assertEquals("6.2", $config->requiresWordPress);
        $this->assertEquals("8.1", $config->requiresPHP);
        $this->assertEquals("custom-{version}.zip", $config->assetPattern);
        $this->assertEquals(3600, $config->cacheDuration);
        $this->assertEquals("custom_check", $config->ajaxAction);
        $this->assertEquals("custom_nonce", $config->ajaxNonce);
        $this->assertEquals("my-custom-plugin", $config->textDomain);
    }

    /**
     * Test that UpdaterConfig's translation helper methods exist and
     * return strings.
     *
     * @return void
     */
    public function testTranslationMethods(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
            "text_domain" => "test-domain"
        ]);

        // These methods would normally call WordPress i18n functions
        // We"re just testing they exist and return strings for now
        $this->assertIsString($config->__("Test string"));
        $this->assertIsString($config->esc_html__("Test string"));
    }

    /**
     * Test that the token option defaults to the documented constant name.
     *
     * @return void
     */
    public function testTokenConstantDefaultsToSilverGithubToken(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo");

        $this->assertSame("SILVER_GITHUB_TOKEN", $config->tokenConstant);
    }

    /**
     * Test that the token constant name can be overridden.
     *
     * @return void
     */
    public function testTokenConstantCanBeOverridden(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", ["token_constant" => "MY_TOKEN"]);

        $this->assertSame("MY_TOKEN", $config->tokenConstant);
    }

    /**
     * Test that no token is returned when neither a constant nor an environment variable is set.
     *
     * @return void
     */
    public function testNoTokenWhenNothingIsConfigured(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
            "token_constant" => "WPGU_TEST_UNSET_TOKEN",
        ]);

        $this->assertNull($config->getGithubToken());
    }

    /**
     * Test that the token is read from an environment variable.
     *
     * @return void
     */
    public function testTokenIsReadFromTheEnvironment(): void
    {
        putenv("WPGU_TEST_ENV_TOKEN=env-value");
        try {
            $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
                "token_constant" => "WPGU_TEST_ENV_TOKEN",
            ]);

            $this->assertSame("env-value", $config->getGithubToken());
        } finally {
            putenv("WPGU_TEST_ENV_TOKEN");
        }
    }

    /**
     * Test that a PHP constant takes precedence over an environment variable of the same name.
     *
     * @return void
     */
    public function testConstantWinsOverTheEnvironment(): void
    {
        if (!defined("WPGU_TEST_CONST_TOKEN")) {
            define("WPGU_TEST_CONST_TOKEN", "constant-value");
        }
        putenv("WPGU_TEST_CONST_TOKEN=env-value");
        try {
            $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
                "token_constant" => "WPGU_TEST_CONST_TOKEN",
            ]);

            $this->assertSame("constant-value", $config->getGithubToken());
        } finally {
            putenv("WPGU_TEST_CONST_TOKEN");
        }
    }

    /**
     * Test that surrounding whitespace is trimmed and an empty value counts as no token.
     *
     * @return void
     */
    public function testTokenIsTrimmedAndEmptyMeansNone(): void
    {
        putenv("WPGU_TEST_TRIM_TOKEN=  padded  ");
        try {
            $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", [
                "token_constant" => "WPGU_TEST_TRIM_TOKEN",
            ]);
            $this->assertSame("padded", $config->getGithubToken());

            putenv("WPGU_TEST_TRIM_TOKEN=   ");
            $this->assertNull($config->getGithubToken());
        } finally {
            putenv("WPGU_TEST_TRIM_TOKEN");
        }
    }

    /**
     * Test that an empty constant name disables the token lookup.
     *
     * @return void
     */
    public function testEmptyConstantNameDisablesTheToken(): void
    {
        $config = new UpdaterConfig(self::$testPluginFile, "owner/repo", ["token_constant" => ""]);

        $this->assertNull($config->getGithubToken());
    }
}
