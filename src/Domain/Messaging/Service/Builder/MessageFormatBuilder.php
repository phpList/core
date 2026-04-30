<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Messaging\Model\Dto\MessageDtoInterface;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;

class MessageFormatBuilder
{
    public function build(MessageDtoInterface $dto): MessageFormat
    {
        $htmlFormatted = strip_tags($dto->getContent()->text) !== $dto->getContent()->text;

        return new MessageFormat(
            htmlFormatted: $htmlFormatted,
            sendFormat: $dto->getFormat()->sendFormat,
        );
    }
}
