<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Messaging\Exception\InvalidDtoTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;

class MessageContentBuilder
{
    public function __construct(private readonly Html2Text $html2Text)
    {
    }

    public function build(object $dto): MessageContent
    {
        if (!$dto instanceof MessageContentDto) {
            throw new InvalidDtoTypeException(get_debug_type($dto));
        }

        $textMessage = strip_tags($dto->text) !== $dto->text
            ? ($this->html2Text)($dto->text)
            : $dto->text;

        return new MessageContent(
            subject: $dto->subject,
            text: $dto->text,
            textMessage: $textMessage,
            footer: $dto->footer
        );
    }
}
