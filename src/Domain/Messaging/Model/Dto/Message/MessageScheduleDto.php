<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto\Message;

class MessageScheduleDto
{
    public function __construct(
        public readonly string $embargo,
        public readonly ?int $repeatInterval = null,
        public readonly ?string $repeatUntil = null,
        public readonly ?int $requeueInterval = null,
        public readonly ?string $requeueUntil = null,
    ) {
    }
}
