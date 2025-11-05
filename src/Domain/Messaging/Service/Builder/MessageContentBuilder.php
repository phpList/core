<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Messaging\Exception\InvalidDtoTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;

class MessageContentBuilder
{
    public function build(object $dto): MessageContent
    {
        if (!$dto instanceof MessageContentDto) {
            throw new InvalidDtoTypeException(get_debug_type($dto));
        }

        return new MessageContent(
            subject: $dto->subject,
            text: $dto->text,
            textMessage: $dto->textMessage,
            footer: $dto->footer
        );
    }
}
