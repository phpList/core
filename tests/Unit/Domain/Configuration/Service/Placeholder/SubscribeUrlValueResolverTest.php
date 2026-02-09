<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SubscribeUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubscribeUrlValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
    }

    private function makeUser(): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId('UID-SUB');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new SubscribeUrlValueResolver($this->config);
        $this->assertSame('SUBSCRIBEURL', $resolver->name());
    }

    public function testHtmlEscapesUrl(): void
    {
        $raw = 'https://example.com/sub.php?a=1&b=2&x=<tag>"\'';
        $this->config->method('getValue')
            ->with(ConfigOption::SubscribeUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);

        $resolver = new SubscribeUrlValueResolver($this->config);
        $this->assertSame(htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $resolver($ctx));
    }

    public function testTextReturnsPlainUrl(): void
    {
        $raw = 'https://example.com/sub.php?a=1&b=2';
        $this->config->method('getValue')
            ->with(ConfigOption::SubscribeUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $resolver = new SubscribeUrlValueResolver($this->config);
        $this->assertSame($raw, $resolver($ctx));
    }

    public function testReturnsEmptyStringWhenConfigNull(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::SubscribeUrl)
            ->willReturn(null);

        $resolver = new SubscribeUrlValueResolver($this->config);
        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html)));
        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text)));
    }
}
