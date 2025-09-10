<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto\Message;

use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;

class MessageMetadataDto
{
    public function __construct(
        public readonly MessageStatus $status,
    ) {
    }
}
