<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\FooterValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FooterValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
    }

    private function makeUser(string $email = 'user@example.com', string $uid = 'UID-1'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail($email);
        $u->setUniqueId($uid);
        return $u;
    }

    private function makeDto(string $textFooter = 'TEXT_FOOT', string $htmlFooter = 'HTML_FOOT', string $footer = ''): MessagePrecacheDto
    {
        $dto = new MessagePrecacheDto();
        $dto->textFooter = $textFooter;
        $dto->htmlFooter = $htmlFooter;
        $dto->footer = $footer;
        return $dto;
    }

    public function testName(): void
    {
        $resolver = new FooterValueResolver($this->config, false);
        $this->assertSame('FOOTER', $resolver->name());
    }

    public function testReturnsDtoFooterWhenNotForwarded_Text(): void
    {
        $resolver = new FooterValueResolver($this->config, false);
        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
            messagePrecacheDto: $this->makeDto('TF', 'HF')
        );

        $this->assertSame('TF', $resolver($ctx));
    }

    public function testReturnsDtoFooterWhenNotForwarded_Html(): void
    {
        $resolver = new FooterValueResolver($this->config, false);
        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
            messagePrecacheDto: $this->makeDto('TF', 'HF')
        );

        $this->assertSame('HF', $resolver($ctx));
    }

    public function testForwardedAlternativeUsesStripslashesFooter(): void
    {
        // footer contains escaped quotes/backslashes, should be unescaped by stripslashes
        $raw = "It\\'s \\\"fine\\\" \\ path";
        $dto = $this->makeDto('TF', 'HF', $raw);

        $resolver = new FooterValueResolver($this->config, true);
        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
            messagePrecacheDto: $dto,
            forwardedBy: (new Subscriber())->setEmail('fwd@example.com'),
        );
        $this->assertSame(stripslashes($raw), $resolver($ctx));
    }

    public function testForwardedUsesConfigForwardFooterWhenFlagFalse(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardFooter)
            ->willReturn('Forward footer set by config');

        $resolver = new FooterValueResolver($this->config, false);
        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
            messagePrecacheDto: $this->makeDto('TF', 'HF', 'Alt'),
            forwardedBy: (new Subscriber())->setEmail('fwd@example.com'),
        );

        $this->assertSame('Forward footer set by config', $resolver($ctx));
    }

    public function testForwardedFallsBackToEmptyWhenConfigNull(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardFooter)
            ->willReturn(null);

        $resolver = new FooterValueResolver($this->config, false);
        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
            messagePrecacheDto: $this->makeDto('TF', 'HF', 'Alt'),
            forwardedBy: (new Subscriber())->setEmail('fwd@example.com'),
        );

        $this->assertSame('', $resolver($ctx));
    }
}
