<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

/**
 * Outcome of an atomic slot reservation attempt in DomainThrottleStateRepository.
 */
final class DomainThrottleReservation
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $blockedAttempts = 0,
    ) {
    }
}
