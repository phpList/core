<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\Notification\AttachmentRepositoryNotificationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttachmentRepositoryNotificationProviderTest extends TestCase
{
    private function provider(string $path): AttachmentRepositoryNotificationProvider
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new AttachmentRepositoryNotificationProvider($translator, $path);
    }

    private function admin(bool $super): Administrator
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('isSuperUser')->willReturn($super);

        return $admin;
    }

    public function testWritableDirectoryProducesNothing(): void
    {
        self::assertSame([], $this->provider(sys_get_temp_dir())->getNotifications($this->admin(true)));
    }

    public function testMissingDirectoryProducesWarning(): void
    {
        $result = $this->provider('/nonexistent/attachments')->getNotifications($this->admin(true));

        self::assertCount(1, $result);
        self::assertSame('attachment_repository_not_writable', $result[0]->code);
        self::assertSame('warning', $result[0]->level);
    }

    public function testNonSuperUserGetsNothing(): void
    {
        self::assertSame([], $this->provider('/nonexistent')->getNotifications($this->admin(false)));
    }
}
