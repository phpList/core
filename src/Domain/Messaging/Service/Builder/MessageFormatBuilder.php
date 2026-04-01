<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Messaging\Exception\InvalidDtoTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;

class MessageFormatBuilder
{
    public function build(object $dto): MessageFormat
    {
        if (!$dto instanceof MessageFormatDto) {
            throw new InvalidDtoTypeException(get_debug_type($dto));
        }

        return new MessageFormat(
            htmlFormatted: $dto->htmlFormated,
            sendFormat: $dto->sendFormat,
        );
    }
}
