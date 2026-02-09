<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ForwardValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForwardValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    private function makeUser(string $uid = 'UID-F'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new ForwardValueResolver($this->config, $this->translator);
        $this->assertSame('FORWARD', $resolver->name());
    }

    public function testHtmlReturnsLinkWithEscapedHrefAndLabelNoQuery(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php');
        $this->translator
            ->method('trans')
            ->with('This link')
            ->willReturn('Click & share "now" <>');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-1'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 77,
        );

        $resolver = new ForwardValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $expectedHref = 'https://example.com/forward.php?uid=U-1&amp;mid=77';
        $expectedLabel = htmlspecialchars('Click & share "now" <>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a> ', $out);
    }

    public function testHtmlReturnsLinkWithEscapedHrefAndLabelWithExistingQuery(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php?a=1');
        $this->translator
            ->method('trans')
            ->with('This link')
            ->willReturn('This <&>');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-2'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 5,
        );

        $resolver = new ForwardValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $expectedHref = 'https://example.com/forward.php?a=1&amp;uid=U-2&amp;mid=5';
        $expectedLabel = htmlspecialchars('This <&>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a> ', $out);
        $this->assertSame(
            'https://example.com/forward.php?a=1&uid=U-2&mid=5',
            html_entity_decode($expectedHref)
        );
    }

    public function testTextReturnsRawUrlWithTrailingSpace(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-3'),
            format: OutputFormat::Text,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 9,
        );

        $resolver = new ForwardValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $this->assertSame('https://example.com/forward.php?uid=U-3&mid=9 ', $out);
    }

    public function testTextWithExistingQueryHasAmpersandAndTrailingSpace(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php?a=1');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-4'),
            format: OutputFormat::Text,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 11,
        );

        $resolver = new ForwardValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $this->assertSame('https://example.com/forward.php?a=1&uid=U-4&mid=11 ', $out);
    }
}
