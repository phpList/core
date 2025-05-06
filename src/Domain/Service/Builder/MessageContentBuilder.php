<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Model\Messaging\Message\MessageContent;

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
