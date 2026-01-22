<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ContactUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContactUrlValueResolverTest extends TestCase
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
        $u->setUniqueId('UID-1');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new ContactUrlValueResolver($this->config);
        $this->assertSame('CONTACTURL', $resolver->name());
    }

    public function testHtmlEscapesUrl(): void
    {
        $raw = 'https://example.com/vcard.php?a=1&b=2&x=<tag>"\'';
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
        );

        $resolver = new ContactUrlValueResolver($this->config);
        $result = $resolver($ctx);

        // Match implementation defaults of htmlspecialchars
        $this->assertSame(htmlspecialchars($raw), $result);
    }

    public function testTextReturnsPlainUrl(): void
    {
        $raw = 'https://example.com/vcard.php?a=1&b=2';
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
        );

        $resolver = new ContactUrlValueResolver($this->config);
        $this->assertSame($raw, $resolver($ctx));
    }

    public function testReturnsEmptyStringWhenConfigNullForHtml(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn(null);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Html,
        );

        $resolver = new ContactUrlValueResolver($this->config);
        $this->assertSame('', $resolver($ctx));
    }

    public function testReturnsEmptyStringWhenConfigNullForText(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::VCardUrl)
            ->willReturn(null);

        $ctx = new PlaceholderContext(
            user: $this->makeUser(),
            format: OutputFormat::Text,
        );

        $resolver = new ContactUrlValueResolver($this->config);
        $this->assertSame('', $resolver($ctx));
    }
}
