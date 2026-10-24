<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

use Doctrine\ORM\Mapping as ORM;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Messaging\Repository\DomainThrottleStateRepository;

/**
 * Per-domain send counters for a single fixed throttle window, persisted so that
 * DomainRateLimiter enforces DOMAIN_BATCH_SIZE/DOMAIN_BATCH_PERIOD consistently across
 * concurrent queue-processing workers instead of each worker keeping its own count.
 * Rows are read/written exclusively via DomainThrottleStateRepository's atomic
 * UPDATE/INSERT statements, not through the entity manager's persist/flush.
 */
#[ORM\Entity(repositoryClass: DomainThrottleStateRepository::class)]
#[ORM\Table(name: 'domain_throttle')]
class DomainThrottleState implements DomainModel
{
    #[ORM\Id]
    #[ORM\Column(name: 'domain', type: 'string', length: 255)]
    private string $domain;

    #[ORM\Column(name: 'window_start', type: 'integer')]
    private int $windowStart;

    #[ORM\Column(name: 'sent_count', type: 'integer')]
    private int $sentCount;

    #[ORM\Column(name: 'blocked_count', type: 'integer')]
    private int $blockedCount;

    public function __construct(string $domain, int $windowStart, int $sentCount = 0, int $blockedCount = 0)
    {
        $this->domain = $domain;
        $this->windowStart = $windowStart;
        $this->sentCount = $sentCount;
        $this->blockedCount = $blockedCount;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getWindowStart(): int
    {
        return $this->windowStart;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function getBlockedCount(): int
    {
        return $this->blockedCount;
    }
}
