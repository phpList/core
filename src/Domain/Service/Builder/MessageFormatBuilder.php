<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Model\Messaging\Message\MessageFormat;

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
