<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Exception\InvalidContextTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\CreateMessageDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageContext;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageContentBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageFormatBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageOptionsBuilder;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageScheduleBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageBuilderTest extends TestCase
{
    private MessageFormatBuilder&MockObject $formatBuilder;
    private MessageScheduleBuilder&MockObject $scheduleBuilder;
    private MessageContentBuilder&MockObject $contentBuilder;
    private MessageOptionsBuilder&MockObject $optionsBuilder;
    private MessageBuilder $builder;

    protected function setUp(): void
    {
        $templateRepository = $this->createMock(TemplateRepository::class);
        $this->formatBuilder = $this->createMock(MessageFormatBuilder::class);
        $this->scheduleBuilder = $this->createMock(MessageScheduleBuilder::class);
        $this->contentBuilder = $this->createMock(MessageContentBuilder::class);
        $this->optionsBuilder = $this->createMock(MessageOptionsBuilder::class);

        $this->builder = new MessageBuilder(
            templateRepository: $templateRepository,
            messageFormatBuilder: $this->formatBuilder,
            messageScheduleBuilder: $this->scheduleBuilder,
            messageContentBuilder: $this->contentBuilder,
            messageOptionsBuilder: $this->optionsBuilder
        );
    }

    private function createRequest(): CreateMessageDto
    {
        return new CreateMessageDto(
            content: new MessageContentDto(
                subject: '',
                text: '',
                textMessage: '',
                footer: ''
            ),
            format: new MessageFormatDto(
                htmlFormated: false,
                sendFormat: 'text',
                formatOptions: []
            ),
            metadata: new MessageMetadataDto(
                status: Message\MessageStatus::Draft
            ),
            options: new MessageOptionsDto(
                fromField: '',
                toField: null,
                replyTo: null,
                userSelection: null
            ),
            schedule: new MessageScheduleDto(
                embargo: '',
                repeatInterval: null,
                repeatUntil: null,
                requeueInterval: null,
                requeueUntil: null
            ),
            templateId: 0
        );
    }

    private function mockBuildCalls(CreateMessageDto $createMessageDto): void
    {
        $this->formatBuilder->expects($this->once())
            ->method('build')
            ->with($createMessageDto->format)
            ->willReturn($this->createMock(Message\MessageFormat::class));

        $this->scheduleBuilder->expects($this->once())
            ->method('build')
            ->with($createMessageDto->schedule)
            ->willReturn($this->createMock(MessageSchedule::class));

        $this->contentBuilder->expects($this->once())
            ->method('build')
            ->with($createMessageDto->content)
            ->willReturn($this->createMock(MessageContent::class));

        $this->optionsBuilder->expects($this->once())
            ->method('build')
            ->with($createMessageDto->options)
            ->willReturn($this->createMock(Message\MessageOptions::class));
    }

    public function testBuildsNewMessage(): void
    {
        $request = $this->createRequest();
        $admin = $this->createMock(Administrator::class);
        $context = new MessageContext($admin);

        $this->mockBuildCalls($request);

        $this->builder->build(createMessageDto: $request, context: $context);
    }

    public function testThrowsExceptionOnInvalidContext(): void
    {
        $this->expectException(InvalidContextTypeException::class);

        $this->builder->build($this->createMock(CreateMessageDto::class), new \stdClass());
    }

    public function testUpdatesExistingMessage(): void
    {
        $request = $this->createRequest();
        $admin = $this->createMock(Administrator::class);
        $existingMessage = $this->createMock(Message::class);
        $context = new MessageContext($admin, $existingMessage);

        $this->mockBuildCalls($request);

        $existingMessage
            ->expects($this->once())
            ->method('setFormat')
            ->with($this->isInstanceOf(Message\MessageFormat::class));
        $existingMessage
            ->expects($this->once())
            ->method('setSchedule')
            ->with($this->isInstanceOf(MessageSchedule::class));
        $existingMessage
            ->expects($this->once())
            ->method('setContent')
            ->with($this->isInstanceOf(MessageContent::class));
        $existingMessage
            ->expects($this->once())
            ->method('setOptions')
            ->with($this->isInstanceOf(Message\MessageOptions::class));
        $existingMessage->expects($this->once())->method('setTemplate')->with(null);

        $result = $this->builder->build($request, $context);

        $this->assertSame($existingMessage, $result);
    }
}
