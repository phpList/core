<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PreferencesUrlValueResolver;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PreferencesUrlValueResolverTest extends TestCase
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
        $u->setUniqueId('UID-PREF');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new PreferencesUrlValueResolver($this->config);
        $this->assertSame('PREFERENCESURL', $resolver->name());
    }

    public function testTextUrlWithUidAppended(): void
    {
        $raw = 'https://example.com/prefs.php';
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);

        $resolver = new PreferencesUrlValueResolver($this->config);
        $this->assertSame($raw . '?uid=UID-PREF', $resolver($ctx));
    }

    public function testTextUrlUsesAmpersandWhenQueryPresent(): void
    {
        $raw = 'https://example.com/prefs.php?a=1';
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);

        $resolver = new PreferencesUrlValueResolver($this->config);
        $this->assertSame($raw . '&uid=UID-PREF', $resolver($ctx));
    }

    public function testHtmlEscapesUrlAndAppendsUid(): void
    {
        $raw = 'https://e.com/prefs.php?a=1&x=<tag>"\'';
        $this->config->method('getValue')
            ->with(ConfigOption::PreferencesUrl)
            ->willReturn($raw);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);

        $resolver = new PreferencesUrlValueResolver($this->config);
        $result = $resolver($ctx);

        $this->assertSame(
            sprintf('%s%suid=%s', htmlspecialchars($raw), htmlspecialchars('&'), 'UID-PREF'),
            $result
        );
    }
}
