<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;

class MessageFormatBuilder
{
    public function build(object $dto): MessageFormat
    {
        if (!$dto instanceof MessageFormatDto) {
            throw new InvalidArgumentException('Invalid request dto type: ' . get_class($dto));
        }

        return new MessageFormat(
            $dto->htmlFormated,
            $dto->sendFormat,
            $dto->formatOptions
        );
    }
}
