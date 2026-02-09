<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PreferencesValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PreferencesValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    private function makeUser(string $uid = 'UID-PREV'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new PreferencesValueResolver($this->config, $this->translator);
        $this->assertSame('PREFERENCES', $resolver->name());
    }

    public function testHtmlReturnsAnchorWithEscapedHrefAndLabelNoQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn('https://example.com/prefs.php');
        $this->translator->method('trans')
            ->with('This link')
            ->willReturn('Click & manage "prefs" <>');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-1'), format: OutputFormat::Html);

        $resolver = new PreferencesValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $expectedHref = htmlspecialchars('https://example.com/prefs.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . htmlspecialchars('?')
            . 'uid=U-1';
        $expectedLabel = htmlspecialchars('Click & manage "prefs" <>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a> ', $out);
    }

    public function testHtmlReturnsAnchorWithAmpersandWhenQueryPresent(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn('https://example.com/prefs.php?a=1');
        $this->translator->method('trans')
            ->with('This link')
            ->willReturn('Go to prefs');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-2'), format: OutputFormat::Html);

        $resolver = new PreferencesValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $expectedHref = htmlspecialchars('https://example.com/prefs.php?a=1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . htmlspecialchars('&')
            . 'uid=U-2';
        $expectedLabel = htmlspecialchars('Go to prefs', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a> ', $out);
        $this->assertSame('https://example.com/prefs.php?a=1&amp;uid=U-2', $expectedHref);
    }

    public function testTextReturnsUrlWithUidNoQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn('https://example.com/prefs.php');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-3'), format: OutputFormat::Text);
        $resolver = new PreferencesValueResolver($this->config, $this->translator);

        $this->assertSame('https://example.com/prefs.php?uid=U-3', $resolver($ctx));
    }

    public function testTextReturnsUrlWithUidWhenQueryPresent(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn('https://example.com/prefs.php?a=1');

        $ctx = new PlaceholderContext(user: $this->makeUser('U-4'), format: OutputFormat::Text);
        $resolver = new PreferencesValueResolver($this->config, $this->translator);

        $this->assertSame('https://example.com/prefs.php?a=1&uid=U-4', $resolver($ctx));
    }
}
