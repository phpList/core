<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Provider;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\DefaultConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \PhpList\Core\Domain\Configuration\Service\Provider\DefaultConfigProvider
 */
final class DefaultConfigProviderTest extends TestCase
{
    private TranslatorInterface|MockObject $translator;
    private DefaultConfigProvider $provider;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->provider = new DefaultConfigProvider($this->translator);
    }

    public function testWebsiteAndDomainDefaultsComeFromConfiguredWebsite(): void
    {
        $provider = new DefaultConfigProvider($this->translator, 'www.example.com');

        $this->assertSame('www.example.com', $provider->get(ConfigOption::Website)['value']);
        $this->assertSame('example.com', $provider->get(ConfigOption::Domain)['value']);
    }

    public function testHasReturnsTrueForKnownKey(): void
    {
        $this->assertTrue($this->provider->has(ConfigOption::AdminAddress));
    }

    public function testGetReturnsArrayShapeForKnownKey(): void
    {
        $item = $this->provider->get(ConfigOption::AdminAddress);

        $this->assertIsArray($item);
        $this->assertArrayHasKey('value', $item);
        $this->assertArrayHasKey('description', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('category', $item);

        // basic sanity check
        $this->assertSame('email', $item['type']);
        $this->assertSame('general', $item['category']);
        $this->assertStringContainsString('[DOMAIN]', (string) $item['value']);
    }

    public function testRemoteProcessingSecretIsRandomHexOfExpectedLength(): void
    {
        $item = $this->provider->get(ConfigOption::RemoteProcessingSecret);
        $this->assertIsArray($item);
        $this->assertArrayHasKey('value', $item);

        $val = (string) $item['value'];
        // bin2hex(random_bytes(10)) => 20 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{20}$/i', $val);
    }

    public function testSubscribeUrlDefaultsToHttpAndApiV2Path(): void
    {
        $item = $this->provider->get(ConfigOption::SubscribeUrl);
        $this->assertIsArray($item);
        $url = (string) $item['value'];

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringContainsString('[WEBSITE]', $url);
        $this->assertStringContainsString('/api/v2/?p=subscribe', $url);
    }

    public function testUnsubscribeUrlDefaults(): void
    {
        $item = $this->provider->get(ConfigOption::UnsubscribeUrl);
        $url = (string) $item['value'];

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringContainsString('/api/v2/?p=unsubscribe', $url);
    }

    public function testTranslatorIsUsedOnlyOnFirstInit(): void
    {
        $calls = 0;
        $this->translator
            ->method('trans')
            ->willReturnCallback(function (string $id) use (&$calls): string {
                $calls++;

                return $id;
            });
        $this->provider->get(ConfigOption::AdminAddress);
        $callsAfterInit = $calls;
        $this->assertGreaterThan(0, $callsAfterInit);

        // Subsequent calls should not trigger init again
        $this->provider->get(ConfigOption::UnsubscribeUrl);
        $this->provider->has(ConfigOption::BlacklistUrl);

        $this->assertSame($callsAfterInit, $calls);
    }

    public function testKnownKeysHaveReasonableTypes(): void
    {
        $keys = [
            'admin_address'           => 'email',
            'organisation_name'       => 'text',
            'organisation_logo'       => 'image',
            'notify_admin_login'      => 'boolean',
            'message_from_address'    => 'email',
            'message_from_name'       => 'text',
            'message_replyto_address' => 'email',
        ];

        foreach ($keys as $key => $type) {
            $item = $this->provider->get(ConfigOption::from($key));
            $this->assertIsArray($item, 'Item should be an array. Key: ' . $key);
            $this->assertSame($type, $item['type'] ?? null, $key .': should have type ' . $type);
        }
    }
}
