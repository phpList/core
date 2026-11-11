<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service\Notification;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\Notification\QueuedCampaignsNotificationProvider;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class QueuedCampaignsNotificationProviderTest extends TestCase
{
    private MessageRepository&MockObject $repository;
    private QueuedCampaignsNotificationProvider $subject;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MessageRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $params = []) => strtr($id, $params)
        );
        $this->subject = new QueuedCampaignsNotificationProvider($this->repository, $translator);
    }

    private function admin(bool $super): Administrator
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('isSuperUser')->willReturn($super);

        return $admin;
    }

    public function testNonSuperUserGetsNothing(): void
    {
        $this->repository->expects(self::never())->method('countQueued');

        self::assertSame([], $this->subject->getNotifications($this->admin(false)));
    }

    public function testEmptyQueueGetsNothing(): void
    {
        $this->repository->method('countQueued')->willReturn(0);

        self::assertSame([], $this->subject->getNotifications($this->admin(true)));
    }

    public function testQueuedCampaignsProduceInfoNotification(): void
    {
        $this->repository->method('countQueued')->willReturn(3);

        $result = $this->subject->getNotifications($this->admin(true));

        self::assertCount(1, $result);
        self::assertSame('campaigns_in_queue', $result[0]->code);
        self::assertSame('info', $result[0]->level);
        self::assertSame(['count' => 3], $result[0]->context);
        self::assertStringContainsString('3 campaign(s)', $result[0]->message);
    }
}
