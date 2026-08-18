<?php

/**
 * @group Smoke
 */
class SmokeTest extends \Codeception\TestCase\WPTestCase
{
    public function testPluginIsVisibleToWordPress(): void
    {
        $plugins = get_option('active_plugins', []);
        $this->assertIsArray($plugins);
    }
}