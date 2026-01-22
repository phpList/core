<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Placeholder\BlacklistValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BlacklistValueResolverTest extends TestCase
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

    private function makeUser(string $email = 'user@example.com'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail($email);
        $u->setUniqueId('UID-1');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new BlacklistValueResolver($this->config, $this->urlBuilder, $this->translator);
        $this->assertSame('BLACKLIST', $resolver->name());
    }

    public function testHtmlReturnsAnchorWithTranslatedEscapedLabelAndUrl(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::BlacklistUrl)
            ->willReturn('https://example.com/blacklist.php');

        $rawUrl = 'https://example.com/blacklist.php?email=user%40example.com&x=1';
        $this->urlBuilder->expects($this->once())
            ->method('withEmail')
            ->with('https://example.com/blacklist.php', 'user@example.com')
            ->willReturn($rawUrl);

        // Translator returns a label with characters that require escaping
        $this->translator->method('trans')
            ->with('Unsubscribe')
            ->willReturn('Unsubscribe & more "now" <>');

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
        );

        $resolver = new BlacklistValueResolver($this->config, $this->urlBuilder, $this->translator);
        $result = $resolver($ctx);

        $expectedHref = htmlspecialchars($rawUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expectedLabel = htmlspecialchars('Unsubscribe & more "now" <>', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expected = '<a href="' . $expectedHref . '">' . $expectedLabel . '</a>';

        $this->assertSame($expected, $result);
    }

    public function testTextReturnsPlainUrl(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::BlacklistUrl)
            ->willReturn('https://example.com/blacklist.php');

        $rawUrl = 'https://example.com/blacklist.php?email=user%40example.com';
        $this->urlBuilder->expects($this->once())
            ->method('withEmail')
            ->with('https://example.com/blacklist.php', 'user@example.com')
            ->willReturn($rawUrl);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
        );

        $resolver = new BlacklistValueResolver($this->config, $this->urlBuilder, $this->translator);
        $this->assertSame($rawUrl, $resolver($ctx));
    }
}
