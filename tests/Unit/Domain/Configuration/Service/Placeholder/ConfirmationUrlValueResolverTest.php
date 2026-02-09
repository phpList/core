<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ConfirmationUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmationUrlValueResolverTest extends TestCase
{
    private ConfigProvider&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
    }

    private function makeUser(string $uid = 'UID-1'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new ConfirmationUrlValueResolver($this->config);
        $this->assertSame('CONFIRMATIONURL', $resolver->name());
    }

    public function testHtmlWhenBaseHasNoQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ConfirmationUrl)
            ->willReturn('https://example.com/confirm.php');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-42'),
            format: OutputFormat::Html,
        );

        $resolver = new ConfirmationUrlValueResolver($this->config);
        $result = $resolver($ctx);

        $this->assertSame('https://example.com/confirm.php?uid=U-42', $result);
    }

    public function testHtmlWhenBaseHasExistingQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ConfirmationUrl)
            ->willReturn('https://example.com/confirm.php?a=1');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('UIDX'),
            format: OutputFormat::Html,
        );

        $resolver = new ConfirmationUrlValueResolver($this->config);
        $result = $resolver($ctx);

        $this->assertSame('https://example.com/confirm.php?a=1&amp;uid=UIDX', $result);
        // Ensure it decodes to the right raw URL
        $this->assertSame('https://example.com/confirm.php?a=1&uid=UIDX', html_entity_decode($result));
    }

    public function testTextWhenBaseHasNoQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ConfirmationUrl)
            ->willReturn('https://example.com/confirm.php');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('U-7'),
            format: OutputFormat::Text,
        );

        $resolver = new ConfirmationUrlValueResolver($this->config);
        $this->assertSame('https://example.com/confirm.php?uid=U-7', $resolver($ctx));
    }

    public function testTextWhenBaseHasExistingQuery(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::ConfirmationUrl)
            ->willReturn('https://example.com/confirm.php?x=9');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('UU-1'),
            format: OutputFormat::Text,
        );

        $resolver = new ConfirmationUrlValueResolver($this->config);
        $this->assertSame('https://example.com/confirm.php?x=9&uid=UU-1', $resolver($ctx));
    }
}
