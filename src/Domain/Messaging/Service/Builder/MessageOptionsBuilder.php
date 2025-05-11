<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;

class MessageOptionsBuilder
{
    public function build(object $dto): MessageOptions
    {
        if (!$dto instanceof MessageOptionsDto) {
            throw new InvalidArgumentException('Invalid request dto type: ' . get_class($dto));
        }

        return new MessageOptions(
            $dto->fromField ?? '',
            $dto->toField ?? '',
            $dto->replyTo ?? '',
            $dto->userSelection,
            null,
        );
    }
}
