<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ForwardUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ForwardUrlValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
    }

    private function makeUser(string $uid = 'U1'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new ForwardUrlValueResolver($this->config);
        $this->assertSame('FORWARDURL', $resolver->name());
    }

    public function testHtmlWhenBaseHasNoQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('UID-42'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 5,
        );

        $resolver = new ForwardUrlValueResolver($this->config);
        $out = $resolver($ctx);

        $this->assertSame('https://example.com/forward.php?uid=UID-42&amp;mid=5', $out);
    }

    public function testHtmlWhenBaseHasExistingQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php?a=1');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-7'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 15,
        );

        $resolver = new ForwardUrlValueResolver($this->config);
        $out = $resolver($ctx);

        $this->assertSame('https://example.com/forward.php?a=1&amp;uid=U-7&amp;mid=15', $out);
        // Raw decode should match with & between params
        $this->assertSame('https://example.com/forward.php?a=1&uid=U-7&mid=15', html_entity_decode($out));
    }

    public function testTextWhenBaseHasNoQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-T'),
            format: OutputFormat::Text,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 2,
        );

        $resolver = new ForwardUrlValueResolver($this->config);
        $out = $resolver($ctx);

        $this->assertSame('https://example.com/forward.php?uid=U-T&mid=2', $out);
    }

    public function testTextWhenBaseHasExistingQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php?x=9');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-Z'),
            format: OutputFormat::Text,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 88,
        );

        $resolver = new ForwardUrlValueResolver($this->config);
        $out = $resolver($ctx);

        $this->assertSame('https://example.com/forward.php?x=9&uid=U-Z&mid=88', $out);
    }
}
