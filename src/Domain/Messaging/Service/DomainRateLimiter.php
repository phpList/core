<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Dto\DomainThrottleResult;
use PhpList\Core\Domain\Messaging\Repository\DomainThrottleStateRepository;
use Psr\Log\LoggerInterface;

/**
 * Limits how many sends go to any single recipient domain within a fixed time window.
 * State is persisted via DomainThrottleStateRepository so the quota is shared across
 * concurrent queue-processing workers rather than each keeping its own count. Unlike
 * SendRateLimiter, this never blocks the whole batch: it just reports whether a domain
 * is over quota right now, so the caller can defer that one recipient to a later run
 * instead of stalling on one busy domain.
 */
class DomainRateLimiter
{
    /**
     * Matches phpList3's threshold for triggering auto-throttle backoff: skip a run of
     * blocked attempts before introducing extra delay, so a handful of early blocks
     * (normal while a window fills up) don't immediately trigger backoff.
     */
    private const AUTO_THROTTLE_ATTEMPT_THRESHOLD = 25;

    public function __construct(
        private readonly DomainThrottleStateRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = false,
        private readonly int $domainBatchSize = 1,
        private readonly int $domainBatchPeriod = 120,
        private readonly bool $autoThrottle = false,
    ) {
    }

    /**
     * Call before sending to $email. Atomically reserves a send slot for that recipient's
     * domain when quota allows; when quota is exhausted, records the blocked attempt and,
     * if DOMAIN_AUTO_THROTTLE is enabled and blocked attempts have piled up, sleeps for a
     * short backoff before returning.
     */
    public function attemptSend(string $email): DomainThrottleResult
    {
        if (!$this->enabled || $this->domainBatchSize <= 0 || $this->domainBatchPeriod <= 0) {
            return new DomainThrottleResult(allowed: true, domain: null);
        }

        $domain = $this->extractDomain($email);
        if ($domain === null) {
            return new DomainThrottleResult(allowed: true, domain: null);
        }

        $windowStart = intdiv(time(), $this->domainBatchPeriod) * $this->domainBatchPeriod;
        $reservation = $this->repository->tryReserveSlot($domain, $windowStart, $this->domainBatchSize);

        if ($reservation->allowed) {
            return new DomainThrottleResult(allowed: true, domain: $domain);
        }

        $this->logger->info('Send blocked by domain throttle', [
            'domain' => $domain,
            'blocked_attempts' => $reservation->blockedAttempts,
            'domain_batch_size' => $this->domainBatchSize,
            'domain_batch_period' => $this->domainBatchPeriod,
        ]);

        return $this->applyAutoThrottleIfDue($domain, $windowStart, $reservation->blockedAttempts);
    }

    private function applyAutoThrottleIfDue(
        string $domain,
        int $windowStart,
        int $blockedAttempts
    ): DomainThrottleResult {
        if (!$this->autoThrottle || $blockedAttempts <= self::AUTO_THROTTLE_ATTEMPT_THRESHOLD) {
            return new DomainThrottleResult(allowed: false, domain: $domain, blockedAttempts: $blockedAttempts);
        }

        // Reset the trigger counter so it takes another full run of blocked attempts
        // before backoff fires again for this domain/window.
        $this->repository->resetBlockedCount($domain, $windowStart);
        $delaySeconds = max(1, intdiv($this->domainBatchPeriod, max(1, $this->domainBatchSize * 4)));

        $this->logger->info('Introducing extra delay to reduce domain throttle failures', [
            'domain' => $domain,
            'delay_seconds' => $delaySeconds,
        ]);
        sleep($delaySeconds);

        return new DomainThrottleResult(
            allowed: false,
            domain: $domain,
            blockedAttempts: $blockedAttempts,
            backoffApplied: true,
            backoffSeconds: $delaySeconds,
        );
    }

    private function extractDomain(string $email): ?string
    {
        $atPosition = strrpos($email, '@');
        if ($atPosition === false) {
            return null;
        }

        return strtolower(substr($email, $atPosition + 1));
    }
}
