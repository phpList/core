<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\UnsubscribeUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UnsubscribeUrlValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private LegacyUrlBuilder&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
    }

    private function makeUser(string $uid = 'UID-UNSUB'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new UnsubscribeUrlValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('UNSUBSCRIBEURL', $resolver->name());
    }

    public function testHtmlEscapesBuiltUrl(): void
    {
        $base = 'https://example.com/unsub.php?a=1&x=<tag>';
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn($base);

        $built = $base . '&uid=UID-UNSUB';

        $this->urlBuilder
            ->expects($this->once())
            ->method('withUid')
            ->with($base, 'UID-UNSUB')
            ->willReturn($built);

        $ctx = new PlaceholderContext(user: $this->makeUser('UID-UNSUB'), format: OutputFormat::Html);

        $resolver = new UnsubscribeUrlValueResolver($this->config, $this->urlBuilder);
        $result = $resolver($ctx);

        $this->assertSame(htmlspecialchars($built, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $result);
    }

    public function testTextReturnsBuiltUrl(): void
    {
        $base = 'https://example.com/unsub.php?a=1';
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn($base);

        $this->urlBuilder
            ->expects($this->once())
            ->method('withUid')
            ->with($base, 'U-TXT')
            ->willReturn('https://example.com/unsub.php?a=1&uid=U-TXT');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-TXT'), format: OutputFormat::Text);

        $resolver = new UnsubscribeUrlValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('https://example.com/unsub.php?a=1&uid=U-TXT', $resolver($ctx));
    }

    public function testReturnsEmptyStringWhenConfigNullOrEmpty(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn(null);

        $resolver = new UnsubscribeUrlValueResolver($this->config, $this->urlBuilder);

        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html)));
        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text)));
    }
}
