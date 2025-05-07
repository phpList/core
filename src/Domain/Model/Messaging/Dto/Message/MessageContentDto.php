<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Dto\Message;

class MessageContentDto
{
    public function __construct(
        public readonly string $subject,
        public readonly string $text,
        public readonly string $textMessage,
        public readonly string $footer,
    ) {
    }
}
