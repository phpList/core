<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common;

use PhpList\Core\Domain\Common\OnceCacheGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class OnceCacheGuardTest extends TestCase
{
    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
    }

    public function testFirstTimeReturnsTrueAndSetsKeyWithTtl(): void
    {
        $key = 'once:key:123';
        $ttl = 60;

        $this->cache->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($key, true, $ttl)
            ->willReturn(true);

        $guard = new OnceCacheGuard($this->cache);

        $this->assertTrue($guard->firstTime($key, $ttl));
    }

    public function testFirstTimeReturnsFalseWhenKeyAlreadyPresent(): void
    {
        $key = 'once:key:present';

        $this->cache->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(true);

        $this->cache->expects($this->never())
            ->method('set');

        $guard = new OnceCacheGuard($this->cache);

        $this->assertFalse($guard->firstTime($key, 10));
    }

    public function testFirstTimeIgnoresSetFailureAndStillReturnsTrueOnFirstCall(): void
    {
        $key = 'once:key:set-fails';
        $ttl = 5;

        $this->cache->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(false);

        // Even if underlying cache set returns false, guard should return true.
        $this->cache->expects($this->once())
            ->method('set')
            ->with($key, true, $ttl)
            ->willReturn(false);

        $guard = new OnceCacheGuard($this->cache);

        $this->assertTrue($guard->firstTime($key, $ttl));
    }
}
