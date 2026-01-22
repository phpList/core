<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SubscribeValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SubscribeValueResolverTest extends TestCase
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
        $u->setUniqueId('UID-SV');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new SubscribeValueResolver($this->config, $this->translator);
        $this->assertSame('SUBSCRIBE', $resolver->name());
    }

    public function testHtmlReturnsAnchorWithEscapedHrefAndLabel(): void
    {
        $rawUrl = 'https://example.com/sub.php?a=1&x=<tag>"\'';
        $this->config->method('getValue')
            ->with(ConfigOption::SubscribeUrl)
            ->willReturn($rawUrl);

        $this->translator->method('trans')
            ->with('This link')
            ->willReturn('Click & join "now" <>');

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);

        $resolver = new SubscribeValueResolver($this->config, $this->translator);
        $out = $resolver($ctx);

        $expectedHref = htmlspecialchars($rawUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expectedLabel = htmlspecialchars('Click & join "now" <>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->assertSame('<a href="' . $expectedHref . '">' . $expectedLabel . '</a>', $out);
    }

    public function testTextReturnsPlainUrl(): void
    {
        $raw = 'https://example.com/sub.php?a=1&b=2';
        $this->config->method('getValue')
            ->with(ConfigOption::SubscribeUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $resolver = new SubscribeValueResolver($this->config, $this->translator);
        $this->assertSame($raw, $resolver($ctx));
    }

    public function testReturnsEmptyStringWhenConfigNull(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::SubscribeUrl)
            ->willReturn(null);

        $resolver = new SubscribeValueResolver($this->config, $this->translator);

        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html)));
        $this->assertSame('', $resolver(new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text)));
    }
}
