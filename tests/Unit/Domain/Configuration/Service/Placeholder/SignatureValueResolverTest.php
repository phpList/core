<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SignatureValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureValueResolverTest extends TestCase
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
        $u->setUniqueId('UID-SIG');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new SignatureValueResolver($this->config);
        $this->assertSame('SIGNATURE', $resolver->name());
    }

    public function testHtmlReturnsPoweredByTextWhenTextCreditsTrue(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PoweredByText)
            ->willReturn('Powered by phpList');

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);
        $resolver = new SignatureValueResolver($this->config, true);

        $this->assertSame('Powered by phpList', $resolver($ctx));
    }

    public function testHtmlReturnsEmptyWhenPoweredByTextNullAndTextCreditsTrue(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PoweredByText)
            ->willReturn(null);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);
        $resolver = new SignatureValueResolver($this->config, true);

        $this->assertSame('', $resolver($ctx));
    }

    public function testHtmlReplacesImageSrcWhenTextCreditsFalse(): void
    {
        $html = '<img alt="" src="https://cdn.example.com/assets/power-phplist.png" class="x">';
        $this->config->method('getValue')
            ->with(ConfigOption::PoweredByImage)
            ->willReturn($html);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);
        $resolver = new SignatureValueResolver($this->config, false);

        $out = $resolver($ctx);
        $this->assertStringContainsString('src="powerphplist.png"', $out);
    }

    public function testHtmlReturnsEmptyWhenPoweredByImageNull(): void
    {
        $this->config->method('getValue')
            ->with(ConfigOption::PoweredByImage)
            ->willReturn(null);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);
        $resolver = new SignatureValueResolver($this->config, false);

        $this->assertSame('', $resolver($ctx));
    }

    public function testTextReturnsFixedSignature(): void
    {
        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $resolver = new SignatureValueResolver($this->config, false);

        $this->assertSame("\n\n-- powered by phpList, www.phplist.com --\n\n", $resolver($ctx));
    }
}
