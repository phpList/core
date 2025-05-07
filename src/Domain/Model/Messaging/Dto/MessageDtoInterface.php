<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Dto;

use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageScheduleDto;

interface MessageDtoInterface
{
    public function getContent(): MessageContentDto;
    public function getFormat(): MessageFormatDto;
    public function getMetadata(): MessageMetadataDto;
    public function getOptions(): MessageOptionsDto;
    public function getSchedule(): MessageScheduleDto;
    public function getTemplateId(): ?int;
}
