<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;

class MessageContentBuilder
{
    public function build(object $dto): MessageContent
    {
        if (!$dto instanceof MessageContentDto) {
            throw new InvalidArgumentException('Invalid request dto type: ' . get_class($dto));
        }

        return new MessageContent(
            $dto->subject,
            $dto->text,
            $dto->textMessage,
            $dto->footer
        );
    }
}
