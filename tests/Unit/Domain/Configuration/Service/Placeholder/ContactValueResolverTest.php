<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ContactValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    private function makeUser(): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId('UID-C');
        return $u;
    }

    public function testPatternMatchesBothContactForms(): void
    {
        $resolver = new ContactValueResolver($this->config, $this->translator);

        $pattern = $resolver->pattern();
        $this->assertSame(1, preg_match($pattern, '[CONTACT]'));
        $this->assertSame(1, preg_match($pattern, '[Contact:123]'));
    }

    public function testHtmlReturnsAnchorWithEscapedUrlAndLabel(): void
    {
        $rawUrl = 'https://example.com/vcard.php?a=1&b=2&x=<tag>"\'';
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn($rawUrl);

        $this->translator->method('trans')
            ->with('Add us to your address book')
            ->willReturn('Add & keep in "book" <>');

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
        );

        $resolver = new ContactValueResolver($this->config, $this->translator);

        // simulate regex matches (index 1 is optional number, can be missing)
        $matches = ['[CONTACT]', null];

        $result = $resolver($ctx, $matches);

        $expectedHref = htmlspecialchars($rawUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expectedText = htmlspecialchars('Add & keep in "book" <>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expected = sprintf('<a href="%s">%s</a>', $expectedHref, $expectedText);

        $this->assertSame($expected, $result);
    }

    public function testTextReturnsLabelColonUrlWhenLabelNonEmpty(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn('https://example.com/vcard.php');

        $this->translator->method('trans')
            ->with('Add us to your address book')
            ->willReturn('Add us to your address book');

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
        );

        $resolver = new ContactValueResolver($this->config, $this->translator);
        $out = $resolver($ctx, ['[CONTACT]']);

        $this->assertSame('Add us to your address book: https://example.com/vcard.php', $out);
    }

    public function testTextReturnsJustUrlWhenLabelEmpty(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn('https://example.com/vcard.php?x=1');

        $this->translator->method('trans')
            ->with('Add us to your address book')
            ->willReturn('');

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
        );

        $resolver = new ContactValueResolver($this->config, $this->translator);
        $out = $resolver($ctx, ['[CONTACT:9]', '9']);

        $this->assertSame('https://example.com/vcard.php?x=1', $out);
    }
}
