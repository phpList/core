<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Placeholder\JumpoffUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class JumpoffUrlValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private LegacyUrlBuilder&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
    }

    private function makeUser(string $uid = 'UID-JOU'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new JumpoffUrlValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('JUMPOFFURL', $resolver->name());
    }

    public function testHtmlReturnsEmptyString(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn('https://example.com/unsub.php');

        $this->urlBuilder->expects($this->once())
            ->method('withUid')
            ->with('https://example.com/unsub.php', 'UH-1')
            ->willReturn('https://example.com/unsub.php?uid=UH-1');

        $ctx = new PlaceholderContext(user: $this->makeUser('UH-1'), format: OutputFormat::Html);
        $resolver = new JumpoffUrlValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('', $resolver($ctx));
    }

    public function testTextReturnsPlainUrlWithJoParam(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn('https://example.com/unsub.php?a=1');

        $this->urlBuilder->expects($this->once())
            ->method('withUid')
            ->with('https://example.com/unsub.php?a=1', 'U-T1')
            ->willReturn('https://example.com/unsub.php?a=1&uid=U-T1');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-T1'), format: OutputFormat::Text);
        $resolver = new JumpoffUrlValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('https://example.com/unsub.php?a=1&uid=U-T1&jo=1', $resolver($ctx));
    }
}
