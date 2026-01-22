<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common;

use Psr\SimpleCache\CacheInterface;

class OnceCacheGuard
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * Returns true if this key has NOT been seen recently, and records it.
     */
    public function firstTime(string $key, int $ttlSeconds): bool
    {
        if ($this->cache->has($key)) {
            return false;
        }
        // mark as seen
        $this->cache->set($key, true, $ttlSeconds);

        return true;
    }
}
