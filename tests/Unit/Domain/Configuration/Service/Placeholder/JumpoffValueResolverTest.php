<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Placeholder\JumpoffValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class JumpoffValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private LegacyUrlBuilder&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
    }

    private function makeUser(string $uid = 'UID-JO'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new JumpoffValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('JUMPOFF', $resolver->name());
    }

    public function testHtmlReturnsEmptyStringButBuildsUrlWithUid(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn('https://example.com/unsubscribe.php');

        // Even though HTML returns empty string, implementation builds URL first
        $this->urlBuilder->expects($this->once())
            ->method('withUid')
            ->with('https://example.com/unsubscribe.php', 'UID-H')
            ->willReturn('https://example.com/unsubscribe.php?uid=UID-H');

        $ctx = new PlaceholderContext(user: $this->makeUser('UID-H'), format: OutputFormat::Html);
        $resolver = new JumpoffValueResolver($this->config, $this->urlBuilder);

        $this->assertSame('', $resolver($ctx));
    }

    public function testTextReturnsPlainUrlWithUidAndJoParamWhenNoExistingQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn('https://example.com/unsubscribe.php');

        $this->urlBuilder->expects($this->once())
            ->method('withUid')
            ->with('https://example.com/unsubscribe.php', 'U-1')
            ->willReturn('https://example.com/unsubscribe.php?uid=U-1');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-1'), format: OutputFormat::Text);
        $resolver = new JumpoffValueResolver($this->config, $this->urlBuilder);

        $this->assertSame('https://example.com/unsubscribe.php?uid=U-1&jo=1', $resolver($ctx));
    }

    public function testTextReturnsPlainUrlWithUidAndJoParamWhenExistingQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn('https://example.com/unsubscribe.php?a=1');

        $this->urlBuilder->expects($this->once())
            ->method('withUid')
            ->with('https://example.com/unsubscribe.php?a=1', 'U-2')
            ->willReturn('https://example.com/unsubscribe.php?a=1&uid=U-2');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-2'), format: OutputFormat::Text);
        $resolver = new JumpoffValueResolver($this->config, $this->urlBuilder);

        $this->assertSame('https://example.com/unsubscribe.php?a=1&uid=U-2&jo=1', $resolver($ctx));
    }
}
