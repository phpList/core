<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\UnsubscribeValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UnsubscribeValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private LegacyUrlBuilder&MockObject $urlBuilder;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    private function makeUser(string $uid = 'UID-U'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new UnsubscribeValueResolver($this->config, $this->urlBuilder, $this->translator);
        $this->assertSame('UNSUBSCRIBE', $resolver->name());
    }

    public function testHtmlReturnsAnchorWithEscapedHrefAndLabel(): void
    {
        $base = 'https://example.com/unsub.php?a=1&x=<tag>';
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn($base);

        $built = $base . '&uid=UID-H';
        $this->urlBuilder
            ->expects($this->once())
            ->method('withUid')
            ->with($base, 'UID-H')
            ->willReturn($built);

        $this->translator
            ->method('trans')
            ->with('Unsubscribe')
            ->willReturn('Unsubscribe & confirm "now" <>');

        $ctx = new PlaceholderContext(user: $this->makeUser('UID-H'), format: OutputFormat::Html);

        $resolver = new UnsubscribeValueResolver($this->config, $this->urlBuilder, $this->translator);
        $out = $resolver($ctx);

        $expectedHref = htmlspecialchars($built, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expectedLabel = htmlspecialchars('Unsubscribe & confirm "now" <>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a>', $out);
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
            ->with($base, 'UID-TXT')
            ->willReturn('https://example.com/unsub.php?a=1&uid=UID-TXT');

        $ctx = new PlaceholderContext(user: $this->makeUser('UID-TXT'), format: OutputFormat::Text);

        $resolver = new UnsubscribeValueResolver($this->config, $this->urlBuilder, $this->translator);
        $this->assertSame('https://example.com/unsub.php?a=1&uid=UID-TXT', $resolver($ctx));
    }

    public function testForwardedByUsesBlacklistUrl(): void
    {
        $unsubscribeBase = 'https://example.com/unsub.php';
        $blacklistBase = 'https://example.com/black.php';

        $this->config->method('getValue')
            ->willReturnMap(
                [
                [ConfigOption::UnsubscribeUrl, $unsubscribeBase],
                [ConfigOption::BlacklistUrl, $blacklistBase],
                ]
            );

        $this->urlBuilder
            ->expects($this->once())
            ->method('withUid')
            ->with($blacklistBase, 'forwarded')
            ->willReturn($blacklistBase . '?uid=forwarded');

        $this->translator
            ->method('trans')
            ->with('Unsubscribe')
            ->willReturn('Unsubscribe');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('UID-FWD'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: (new Subscriber())->setEmail('someone@example.com'),
        );

        $resolver = new UnsubscribeValueResolver($this->config, $this->urlBuilder, $this->translator);
        $out = $resolver($ctx);

        $this->assertStringContainsString(
            'href="'
            . htmlspecialchars($blacklistBase . '?uid=forwarded', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '"',
            $out
        );
    }

    public function testReturnsEmptyStringWhenBaseMissing(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::UnsubscribeUrl)
            ->willReturn(null);

        $resolver = new UnsubscribeValueResolver($this->config, $this->urlBuilder, $this->translator);
        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text)));
        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html)));
    }
}
