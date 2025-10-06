<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Provider;

use PhpList\Core\Domain\Configuration\Service\Provider\DefaultConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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

    public function testHasReturnsTrueForKnownKey(): void
    {
        $this->assertTrue($this->provider->has('admin_address'));
    }

    public function testGetReturnsArrayShapeForKnownKey(): void
    {
        $item = $this->provider->get('admin_address');

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

    public function testGetReturnsProvidedDefaultWhenUnknownKey(): void
    {
        $fallback = ['value' => 'X', 'type' => 'text'];
        $this->assertSame($fallback, $this->provider->get('does_not_exist', $fallback));
    }

    public function testRemoteProcessingSecretIsRandomHexOfExpectedLength(): void
    {
        $item = $this->provider->get('remote_processing_secret');
        $this->assertIsArray($item);
        $this->assertArrayHasKey('value', $item);

        $val = (string) $item['value'];
        // bin2hex(random_bytes(10)) => 20 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{20}$/i', $val);
    }

    public function testSubscribeUrlDefaultsToHttpAndApiV2Path(): void
    {
        $item = $this->provider->get('subscribeurl');
        $this->assertIsArray($item);
        $url = (string) $item['value'];

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringContainsString('[WEBSITE]', $url);
        $this->assertStringContainsString('/api/v2/?p=subscribe', $url);
    }

    public function testUnsubscribeUrlDefaults(): void
    {
        $item = $this->provider->get('unsubscribeurl');
        $url = (string) $item['value'];

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringContainsString('/api/v2/?p=unsubscribe', $url);
    }

    public function testTranslatorIsUsedOnlyOnFirstInit(): void
    {
        $this->translator
            ->expects($this->atLeastOnce())
            ->method('trans')
            ->willReturnArgument(0);
        $this->provider->get('admin_address');

        // Subsequent calls should not trigger init again
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->never())
            ->method('trans');

        $reflection = new ReflectionClass($this->provider);
        $prop = $reflection->getProperty('translator');

        $prop->setValue($this->provider, $translator);
        $this->provider->get('unsubscribeurl');
        $this->provider->has('pageheader');
    }

    public function testKnownKeysHaveReasonableTypes(): void
    {
        $keys = [
            'admin_address'           => 'email',
            'organisation_name'       => 'text',
            'organisation_logo'       => 'image',
            'date_format'             => 'text',
            'rc_notification'         => 'boolean',
            'notify_admin_login'      => 'boolean',
            'message_from_address'    => 'email',
            'message_from_name'       => 'text',
            'message_replyto_address' => 'email',
        ];

        foreach ($keys as $key => $type) {
            $item = $this->provider->get($key);
            $this->assertIsArray($item, 'Item should be an array. Key: ' . $key);
            $this->assertSame($type, $item['type'] ?? null, $key .': should have type ' . $type);
        }
    }
}
