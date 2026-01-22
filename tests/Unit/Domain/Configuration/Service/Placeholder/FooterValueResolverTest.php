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

    private function makeUser(): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId('UID-FTR');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new FooterValueResolver($this->config, false);
        $this->assertSame('FOOTER', $resolver->name());
    }

    public function testReturnsConfigForwardFooterByDefault(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardFooter)
            ->willReturn('Default footer');

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);

        $resolver = new FooterValueResolver($this->config, false);
        $this->assertSame('Default footer', $resolver($ctx));
    }

    public function testReturnsEmptyStringWhenConfigNull(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ForwardFooter)
            ->willReturn(null);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);

        $resolver = new FooterValueResolver($this->config, false);
        $this->assertSame('', $resolver($ctx));
    }

    public function testReturnsDtoFooterWhenForwardAlternativeContentEnabledAndDtoPresent(): void
    {
        $dto = new MessagePrecacheDto();
        // with backslashes
        $dto->footer = 'A\\B\\C';

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
            messagePrecacheDto: $dto,
        );

        // When alternative content flag is on, config should be ignored and dto footer used (with stripslashes)
        $resolver = new FooterValueResolver($this->config, true);
        $this->assertSame('ABC', $resolver($ctx));
    }
}
