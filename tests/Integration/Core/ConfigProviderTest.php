<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Core;

use PhpList\Core\Core\ConfigProvider;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testReturnsConfigValueIfExists(): void
    {
        $provider = new ConfigProvider([
            'site_name' => 'phpList',
            'debug' => true,
        ]);

        $this->assertSame('phpList', $provider->get('site_name'));
        $this->assertTrue($provider->get('debug'));
    }

    public function testReturnsDefaultIfKeyMissing(): void
    {
        $provider = new ConfigProvider([
            'site_name' => 'phpList',
        ]);

        $this->assertNull($provider->get('nonexistent'));
        $this->assertSame('default', $provider->get('nonexistent', 'default'));
    }

    public function testReturnsAllConfig(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $provider = new ConfigProvider($data);

        $this->assertSame($data, $provider->all());
    }
}
