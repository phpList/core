<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Limits the total processing time of a long-running operation.
 */
class MaxProcessTimeLimiter
{
    private float $startedAt = 0.0;
    private int $maxSeconds;

    public function __construct(private readonly LoggerInterface $logger, ?int $maxSeconds = null)
    {
        $this->maxSeconds = $maxSeconds ?? 600;
    }

    public function start(): void
    {
        $this->startedAt = microtime(true);
    }

    public function shouldStop(?OutputInterface $output = null): bool
    {
        if ($this->maxSeconds <= 0) {
            return false;
        }
        if ($this->startedAt <= 0.0) {
            $this->start();
        }
        $elapsed = microtime(true) - $this->startedAt;
        if ($elapsed >= $this->maxSeconds) {
            $this->logger->warning(sprintf('Reached max processing time of %d seconds', $this->maxSeconds));
            $output?->writeln('Reached max processing time; stopping cleanly.');

            return true;
        }

        return false;
    }
}
