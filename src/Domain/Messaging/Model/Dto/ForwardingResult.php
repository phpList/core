<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

final class ForwardingResult
{
    /**
     * @param ForwardingRecipientResult[] $recipients
     */
    public function __construct(
        public readonly int $totalRequested,
        public readonly int $totalSent,
        public readonly int $totalFailed,
        public readonly int $totalAlreadySent,
        public readonly array $recipients,
    ) {
    }
}
