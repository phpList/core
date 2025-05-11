<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto\Message;

class MessageMetadataDto
{
    public function __construct(
        public readonly string $status,
    ) {
    }
}
