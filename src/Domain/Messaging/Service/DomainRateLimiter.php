<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

/**
 * Limits how many sends go to any single recipient domain within a rolling time window. Unlike
 * SendRateLimiter, this never sleeps: it just reports whether a domain is over quota right
 * now, so the caller can defer that one recipient to a later run instead of blocking the
 * whole batch on one busy domain. State is kept in memory only (not seeded from history)
 */
class DomainRateLimiter
{
    /** @var array<string, array{start: float, sent: int}> */
    private array $buckets = [];

    public function __construct(
        private readonly bool $enabled = false,
        private readonly int $domainBatchSize = 1,
        private readonly int $domainBatchPeriod = 120,
    ) {
    }

    /**
     * Call before attempting to send to $email. Returns false if that recipient's domain
     * has already hit its quota for the current window and the send should be deferred.
     */
    public function canSendTo(string $email): bool
    {
        if (!$this->enabled || $this->domainBatchSize <= 0 || $this->domainBatchPeriod <= 0) {
            return true;
        }

        $domain = $this->extractDomain($email);
        if ($domain === null) {
            return true;
        }

        return $this->currentBucket($domain)['sent'] < $this->domainBatchSize;
    }

    /**
     * Call once a send to $email has been attempted, to count it against that domain's quota.
     */
    public function recordSend(string $email): void
    {
        if (!$this->enabled) {
            return;
        }

        $domain = $this->extractDomain($email);
        if ($domain === null) {
            return;
        }

        $bucket = $this->currentBucket($domain);
        $bucket['sent']++;
        $this->buckets[$domain] = $bucket;
    }

    /** @return array{start: float, sent: int} */
    private function currentBucket(string $domain): array
    {
        $now = microtime(true);
        $bucket = $this->buckets[$domain] ?? ['start' => $now, 'sent' => 0];

        if ($now - $bucket['start'] >= $this->domainBatchPeriod) {
            $bucket = ['start' => $now, 'sent' => 0];
        }

        $this->buckets[$domain] = $bucket;

        return $bucket;
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
