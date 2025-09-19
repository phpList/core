<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Messaging\Exception\InvalidDtoTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;

class MessageOptionsBuilder
{
    public function build(object $dto): MessageOptions
    {
        if (!$dto instanceof MessageOptionsDto) {
            throw new InvalidDtoTypeException(get_debug_type($dto));
        }

        return new MessageOptions(
            fromField: $dto->fromField ?? '',
            toField: $dto->toField ?? '',
            replyTo: $dto->replyTo ?? '',
            userSelection: $dto->userSelection,
            rssTemplate: null,
        );
    }
}
