<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Dto\CreateMessageDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Messaging\Model\Dto\UpdateMessageDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageBuilder;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageManager;
use PHPUnit\Framework\TestCase;

class MessageManagerTest extends TestCase
{
    public function testCreateMessageReturnsPersistedMessage(): void
    {
        $messageRepository = $this->createMock(MessageRepository::class);
        $messageBuilder = $this->createMock(MessageBuilder::class);
        $manager = new MessageManager($messageRepository, $messageBuilder);

        $format = new MessageFormatDto(true, 'html', ['html']);
        $schedule = new MessageScheduleDto(
            embargo: '2025-04-17T09:00:00+00:00',
            repeatInterval: 60 * 24,
            repeatUntil: '2025-04-30T00:00:00+00:00',
            requeueInterval: 60 * 12,
            requeueUntil: '2025-04-20T00:00:00+00:00',
        );
        $metadata = new MessageMetadataDto(Message\MessageStatus::Draft);
        $content = new MessageContentDto('Subject', 'Full text', 'Short text', 'Footer');
        $options = new MessageOptionsDto('from@example.com', 'to@example.com', 'reply@example.com', 'all-users');

        $request = new CreateMessageDto(
            content: $content,
            format: $format,
            metadata: $metadata,
            options: $options,
            schedule: $schedule,
            templateId: 0
        );

        $authUser = $this->createMock(Administrator::class);

        $expectedMessage = $this->createMock(Message::class);
        $expectedContent = $this->createMock(MessageContent::class);
        $expectedMetadata = $this->createMock(Message\MessageMetadata::class);

        $expectedContent->method('getSubject')->willReturn('Subject');
        $expectedMetadata->method('getStatus')->willReturn(Message\MessageStatus::Draft);

        $expectedMessage->method('getContent')->willReturn($expectedContent);
        $expectedMessage->method('getMetadata')->willReturn($expectedMetadata);

        $messageBuilder->expects($this->once())
            ->method('build')
            ->with($request, $this->anything())
            ->willReturn($expectedMessage);

        $messageRepository->expects($this->once())
            ->method('save')
            ->with($expectedMessage);

        $message = $manager->createMessage($request, $authUser);

        $this->assertSame('Subject', $message->getContent()->getSubject());
        $this->assertSame(Message\MessageStatus::Draft, $message->getMetadata()->getStatus());
    }

    public function testUpdateMessageReturnsUpdatedMessage(): void
    {
        $messageRepository = $this->createMock(MessageRepository::class);
        $messageBuilder = $this->createMock(MessageBuilder::class);
        $manager = new MessageManager($messageRepository, $messageBuilder);

        $format = new MessageFormatDto(false, 'text', ['text']);
        $schedule = new MessageScheduleDto(
            embargo: '2025-04-17T09:00:00+00:00',
            repeatInterval: 0,
            repeatUntil: '2025-04-30T00:00:00+00:00',
            requeueInterval: 0,
            requeueUntil: '2025-04-20T00:00:00+00:00',
        );
        $metadata = new MessageMetadataDto(Message\MessageStatus::Draft);
        $content = new MessageContentDto(
            'Updated Subject',
            'Updated Full text',
            'Updated Short text',
            'Updated Footer'
        );
        $options = new MessageOptionsDto(
            'newfrom@example.com',
            'newto@example.com',
            'newreply@example.com',
            'active-users'
        );

        $updateRequest = new UpdateMessageDto(
            messageId: 1,
            content: $content,
            format: $format,
            metadata: $metadata,
            options: $options,
            schedule: $schedule,
            templateId: 2
        );

        $authUser = $this->createMock(Administrator::class);

        $existingMessage = $this->createMock(Message::class);
        $expectedContent = $this->createMock(MessageContent::class);
        $expectedMetadata = $this->createMock(Message\MessageMetadata::class);

        $expectedContent->method('getSubject')->willReturn('Updated Subject');
        $expectedMetadata->method('getStatus')->willReturn(Message\MessageStatus::Draft);

        $existingMessage->method('getContent')->willReturn($expectedContent);
        $existingMessage->method('getMetadata')->willReturn($expectedMetadata);

        $messageBuilder->expects($this->once())
            ->method('build')
            ->with($updateRequest, $this->anything())
            ->willReturn($existingMessage);

        $messageRepository->expects($this->once())
            ->method('save')
            ->with($existingMessage);

        $message = $manager->updateMessage($updateRequest, $existingMessage, $authUser);

        $this->assertSame('Updated Subject', $message->getContent()->getSubject());
        $this->assertSame(Message\MessageStatus::Draft, $message->getMetadata()->getStatus());
    }
}
