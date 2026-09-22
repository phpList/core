<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

/**
 * Outcome of DomainRateLimiter::attemptSend() for a single recipient.
 */
final class DomainThrottleResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $domain,
        public readonly int $blockedAttempts = 0,
        public readonly bool $backoffApplied = false,
        public readonly int $backoffSeconds = 0,
    ) {
    }
}
