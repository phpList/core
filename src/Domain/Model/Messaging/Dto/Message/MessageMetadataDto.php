<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Messaging\Dto\Message;

class MessageMetadataDto
{
    public function __construct(
        public readonly string $status,
    ) {
    }
}
