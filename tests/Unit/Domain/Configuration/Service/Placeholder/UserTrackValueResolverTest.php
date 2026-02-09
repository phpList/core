<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\UserTrackValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserTrackValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
    }

    private function makeUser(string $uid = 'U-42'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new UserTrackValueResolver($this->config, 'https://api.example');
        $this->assertSame('USERTRACK', $resolver->name());
    }

    public function testReturnsEmptyForTextFormat(): void
    {
        $resolver = new UserTrackValueResolver($this->config, 'https://api.example');
        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $this->assertSame('', $resolver($ctx));
    }

    public function testHtmlUsesConfigDomainWhenAvailable(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::Domain)
            ->willReturn('example.com');

        $resolver = new UserTrackValueResolver($this->config, 'https://api.example');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('UID-XYZ'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 99,
        );

        $result = $resolver($ctx);

        $expected = '<img src="example.com/ut.php?u=UID-XYZ&amp;m=99" width="1" height="1" border="0" alt="" />';
        // Normalize double quotes for comparison
        $this->assertSame($expected, $result);
    }

    public function testHtmlFallsBackToRestApiDomainWhenConfigMissing(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::Domain)
            ->willReturn(null);

        $resolver = new UserTrackValueResolver($this->config, 'https://api.example');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U1'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 7,
        );

        $result = $resolver($ctx);

        $expected = '<img src="https://api.example/ut.php?u=U1&amp;m=7" width="1" height="1" border="0" alt="" />';
        $this->assertSame($expected, $result);
    }
}
