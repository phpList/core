<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Service\MessageStatusUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageStatusUpdaterTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private MessageStatusUpdater $updater;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->updater = new MessageStatusUpdater($this->entityManager);
    }

    public function testUpdateSetsSendStartOnlyOnceWhenTransitioningToInProcess(): void
    {
        $metadata = $this->createMock(MessageMetadata::class);
        $metadata->method('getSendStart')->willReturn(null);
        $metadata->expects($this->once())->method('setSendStart');
        $metadata->expects($this->once())->method('setStatus')->with(MessageStatus::InProcess);

        $message = $this->createMock(Message::class);
        $message->method('getMetadata')->willReturn($metadata);

        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update($message, MessageStatus::InProcess);
    }

    public function testUpdateDoesNotOverwriteExistingSendStart(): void
    {
        $metadata = $this->createMock(MessageMetadata::class);
        $metadata->method('getSendStart')->willReturn(new DateTime());
        $metadata->expects($this->never())->method('setSendStart');

        $message = $this->createMock(Message::class);
        $message->method('getMetadata')->willReturn($metadata);

        $this->updater->update($message, MessageStatus::InProcess);
    }

    public function testUpdateSetsSentTimestampWhenTransitioningToSent(): void
    {
        $metadata = $this->createMock(MessageMetadata::class);
        $metadata->expects($this->once())->method('setSent');
        $metadata->expects($this->once())->method('setStatus')->with(MessageStatus::Sent);

        $message = $this->createMock(Message::class);
        $message->method('getMetadata')->willReturn($metadata);

        $this->updater->update($message, MessageStatus::Sent);
    }

    public function testUpdateDoesNotTouchTimestampsForOtherStatuses(): void
    {
        $metadata = $this->createMock(MessageMetadata::class);
        $metadata->expects($this->never())->method('setSendStart');
        $metadata->expects($this->never())->method('setSent');
        $metadata->expects($this->once())->method('setStatus')->with(MessageStatus::Suspended);

        $message = $this->createMock(Message::class);
        $message->method('getMetadata')->willReturn($metadata);

        $this->updater->update($message, MessageStatus::Suspended);
    }
}
