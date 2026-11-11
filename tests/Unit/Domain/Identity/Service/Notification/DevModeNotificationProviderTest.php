<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\Notification\DevModeNotificationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class DevModeNotificationProviderTest extends TestCase
{
    private function provider(string|bool $dev, string $email): DevModeNotificationProvider
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new DevModeNotificationProvider($translator, $dev, $email);
    }

    private function admin(bool $super): Administrator
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('isSuperUser')->willReturn($super);

        return $admin;
    }

    public function testDisabledProducesNothing(): void
    {
        self::assertSame([], $this->provider('0', 'dev@example.com')->getNotifications($this->admin(true)));
        self::assertSame([], $this->provider(false, 'dev@example.com')->getNotifications($this->admin(true)));
    }

    public function testEnabledShowsRedirectEmail(): void
    {
        $result = $this->provider('1', 'dev@example.com')->getNotifications($this->admin(true));

        self::assertCount(1, $result);
        self::assertSame('dev_mode', $result[0]->code);
        self::assertSame('info', $result[0]->level);
        self::assertSame(['email' => 'dev@example.com'], $result[0]->context);
    }

    public function testEnabledWithoutEmail(): void
    {
        $result = $this->provider(true, '')->getNotifications($this->admin(true));

        self::assertSame('dev_mode_without_email', $result[0]->code);
    }

    public function testNonSuperUserGetsNothing(): void
    {
        self::assertSame([], $this->provider('1', 'dev@example.com')->getNotifications($this->admin(false)));
    }
}
