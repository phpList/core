<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto\Message;

class MessageOptionsDto
{
    public function __construct(
        public readonly string $fromField,
        public readonly ?string $toField = null,
        public readonly ?string $replyTo = null,
        public readonly ?string $userSelection = null,
    ) {
    }
}
