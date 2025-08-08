<?php

namespace SilverAssist\WpGithubUpdater\Tests;

use PHPUnit\Framework\TestCase;
use SilverAssist\WpGithubUpdater\UpdaterConfig;

class UpdaterConfigTest extends TestCase
{
    public function testBasicConfiguration()
    {
        $config = new UpdaterConfig('/path/to/plugin.php', 'owner/repo');

        $this->assertEquals('/path/to/plugin.php', $config->pluginFile);
        $this->assertEquals('owner/repo', $config->githubRepo);
        $this->assertEquals('6.0', $config->requiresWordPress);
        $this->assertEquals('8.0', $config->requiresPHP);
        $this->assertEquals('{slug}-v{version}.zip', $config->assetPattern);
    }

    public function testCustomConfiguration()
    {
        $options = [
            'plugin_name' => 'Test Plugin',
            'plugin_description' => 'A test plugin',
            'plugin_author' => 'Test Author',
            'requires_wordpress' => '6.2',
            'requires_php' => '8.1',
            'asset_pattern' => 'custom-{version}.zip',
            'cache_duration' => 3600,
            'ajax_action' => 'custom_check',
            'ajax_nonce' => 'custom_nonce'
        ];

        $config = new UpdaterConfig('/path/to/plugin.php', 'owner/repo', $options);

        $this->assertEquals('Test Plugin', $config->pluginName);
        $this->assertEquals('A test plugin', $config->pluginDescription);
        $this->assertEquals('Test Author', $config->pluginAuthor);
        $this->assertEquals('6.2', $config->requiresWordPress);
        $this->assertEquals('8.1', $config->requiresPHP);
        $this->assertEquals('custom-{version}.zip', $config->assetPattern);
        $this->assertEquals(3600, $config->cacheDuration);
        $this->assertEquals('custom_check', $config->ajaxAction);
        $this->assertEquals('custom_nonce', $config->ajaxNonce);
    }
}
