<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Placeholder\BlacklistUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BlacklistUrlValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private LegacyUrlBuilder&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
    }

    private function makeUser(string $email = 'user@example.com'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail($email);
        $u->setUniqueId('UID-123');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new BlacklistUrlValueResolver($this->config, $this->urlBuilder);
        $this->assertSame('BLACKLISTURL', $resolver->name());
    }

    public function testInvokedForHtmlEscapesUrl(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::BlacklistUrl)
            ->willReturn('https://example.com/blacklist.php');

        $expectedRaw = 'https://example.com/blacklist.php?a=1&b=2&email=user%40example.com';
        $this->urlBuilder->expects($this->once())
            ->method('withEmail')
            ->with('https://example.com/blacklist.php', 'user@example.com')
            ->willReturn($expectedRaw);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 1,
        );

        $resolver = new BlacklistUrlValueResolver($this->config, $this->urlBuilder);
        $result = $resolver($ctx);

        // In HTML, ampersands must be escaped
        $this->assertSame(
            'https://example.com/blacklist.php?a=1&amp;b=2&amp;email=user%40example.com',
            $result
        );
    }

    public function testInvokedForTextReturnsPlainUrl(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::BlacklistUrl)
            ->willReturn('https://example.com/blacklist.php');

        $expectedRaw = 'https://example.com/blacklist.php?email=user%40example.com';
        $this->urlBuilder->expects($this->once())
            ->method('withEmail')
            ->with('https://example.com/blacklist.php', 'user@example.com')
            ->willReturn($expectedRaw);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
        );

        $resolver = new BlacklistUrlValueResolver($this->config, $this->urlBuilder);
        $result = $resolver($ctx);

        $this->assertSame($expectedRaw, $result);
    }
}
