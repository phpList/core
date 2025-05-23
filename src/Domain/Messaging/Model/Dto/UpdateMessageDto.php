<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;

class UpdateMessageDto implements MessageDtoInterface
{
    public function __construct(
        public readonly int $messageId,
        public readonly MessageContentDto $content,
        public readonly MessageFormatDto $format,
        public readonly MessageMetadataDto $metadata,
        public readonly MessageOptionsDto $options,
        public readonly MessageScheduleDto $schedule,
        public readonly ?int $templateId = null,
    ) {
    }

    public function getContent(): MessageContentDto
    {
        return $this->content;
    }

    public function getFormat(): MessageFormatDto
    {
        return $this->format;
    }

    public function getMetadata(): MessageMetadataDto
    {
        return $this->metadata;
    }

    public function getOptions(): MessageOptionsDto
    {
        return $this->options;
    }

    public function getSchedule(): MessageScheduleDto
    {
        return $this->schedule;
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }
}
