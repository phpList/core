<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\Notification\MailerDisabledNotificationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailerDisabledNotificationProviderTest extends TestCase
{
    private function provider(string $dsn): MailerDisabledNotificationProvider
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new MailerDisabledNotificationProvider($translator, $dsn);
    }

    private function admin(bool $super): Administrator
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('isSuperUser')->willReturn($super);

        return $admin;
    }

    /** @dataProvider nullDsns */
    public function testNullTransportProducesWarning(string $dsn): void
    {
        $result = $this->provider($dsn)->getNotifications($this->admin(true));

        self::assertCount(1, $result);
        self::assertSame('mailer_disabled', $result[0]->code);
        self::assertSame('warning', $result[0]->level);
    }

    public static function nullDsns(): array
    {
        return [['null://null'], ['NULL://default'], ['null+smtp://x']];
    }

    /** @dataProvider realDsns */
    public function testRealTransportProducesNothing(string $dsn): void
    {
        self::assertSame([], $this->provider($dsn)->getNotifications($this->admin(true)));
    }

    public static function realDsns(): array
    {
        return [['smtp://user:null@host:25'], ['sendgrid://KEY@default'], ['native://default'], ['']];
    }

    public function testNonSuperUserGetsNothing(): void
    {
        self::assertSame([], $this->provider('null://null')->getNotifications($this->admin(false)));
    }
}
