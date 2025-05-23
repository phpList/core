<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;

interface MessageDtoInterface
{
    public function getContent(): MessageContentDto;
    public function getFormat(): MessageFormatDto;
    public function getMetadata(): MessageMetadataDto;
    public function getOptions(): MessageOptionsDto;
    public function getSchedule(): MessageScheduleDto;
    public function getTemplateId(): ?int;
}
