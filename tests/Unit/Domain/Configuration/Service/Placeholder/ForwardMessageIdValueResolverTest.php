<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ForwardMessageIdValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForwardMessageIdValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    private function makeUser(string $uid = 'U-FWD'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testPatternMatchesBothForms(): void
    {
        $resolver = new ForwardMessageIdValueResolver($this->config, $this->translator);
        $pattern = $resolver->pattern();

        $this->assertSame(1, preg_match($pattern, '[FORWARD:123]'));
        $this->assertSame(1, preg_match($pattern, '[FORWARD:123:Share]'));
    }

    public function testHtmlWithDefaultTranslatedLabelAndNoQuery(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php');
        $this->translator
            ->method('trans')
            ->with('This link')
            ->willReturn('Click & go');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-99'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
        );

        $matches = ['[FORWARD:77]', '77'];

        $resolver = new ForwardMessageIdValueResolver($this->config, $this->translator);
        $out = $resolver($ctx, $matches);

        $this->assertSame(
            '<a href="https://example.com/forward.php?uid=U-99&amp;mid=77">'
            . htmlspecialchars('Click & go', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</a>',
            $out
        );
    }

    public function testHtmlWithCustomLabelAndExistingQuery(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php?a=1');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-A'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
        );

        $matches = ['[FORWARD:15:Share & enjoy]', '15:Share & enjoy'];

        $resolver = new ForwardMessageIdValueResolver($this->config, $this->translator);
        $out = $resolver($ctx, $matches);

        $expectedHref = 'https://example.com/forward.php?a=1&amp;uid=U-A&amp;mid=15';
        $expectedLabel = htmlspecialchars('Share & enjoy', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a>', $out);
    }

    public function testTextWithDefaultTranslatedLabel(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php');
        $this->translator
            ->method('trans')
            ->with('This link')
            ->willReturn('Open');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-TX'), format: OutputFormat::Text);
        $matches = ['[FORWARD:3]', '3'];

        $resolver = new ForwardMessageIdValueResolver($this->config, $this->translator);
        $out = $resolver($ctx, $matches);

        $this->assertSame('Open https://example.com/forward.php?uid=U-TX&mid=3', $out);
    }

    public function testTextWithCustomLabelAndExistingQuery(): void
    {
        $this->config
            ->method('getValue')
            ->with(ConfigOption::ForwardUrl)
            ->willReturn('https://example.com/forward.php?x=9');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-XY'), format: OutputFormat::Text);
        $matches = ['[FORWARD:44:Share it]', '44:Share it'];

        $resolver = new ForwardMessageIdValueResolver($this->config, $this->translator);
        $out = $resolver($ctx, $matches);

        $this->assertSame('Share it https://example.com/forward.php?x=9&uid=U-XY&mid=44', $out);
    }

    public function testEmptyOrWhitespaceIdReturnsEmptyString(): void
    {
        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $matches = ['[FORWARD: ]', ' '];

        $resolver = new ForwardMessageIdValueResolver($this->config, $this->translator);
        $this->assertSame('', $resolver($ctx, $matches));
    }
}
