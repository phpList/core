<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

final class ForwardingRecipientResult
{
    public function __construct(
        public readonly string $email,
        public readonly string $status,
        public ?string $reason = null,
    ) {
    }
}
